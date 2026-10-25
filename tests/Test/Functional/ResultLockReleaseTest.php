<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\TransactionIsolationLevel;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ConnectionWrapper;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function gc_collect_cycles;
use function sprintf;

/**
 * Functional tests for idle lock release after consumed Results (#176).
 *
 * php-firebird #588 (ext-side #586 fix) releases relation locks retained by
 * commit_ret the moment a transaction's last open cursor closes
 * (open_cursor_count == 0). The driver defeats that mechanism when
 * Statement::$currentResult keeps the last Result of every executed
 * statement referenced: abandoned results (fetchOne + discard, or plain
 * executeQuery + discard) survive as zombie cursors, so DDL autocommits
 * retain system-catalog SW locks and later no-wait transactions conflict
 * on RDB$RELATION_FIELDS (the #578/#586 serializable suite flake).
 *
 * Closes #176. Upstream: php-firebird#586/#588.
 */
class ResultLockReleaseTest extends FunctionalTestCase
{
    private const PROBE_TABLE = 'result_lock_release_probe';

    /**
     * Cross-attachment RED test for #176 (post-#588 transient retention).
     *
     * Attachment A: three fetchOne() one-shots abandon their Results inside the
     * Statement<->Result cycle, then DDL autocommit fires commit_ret with
     * open cursors - retaining system-catalog SW locks (incl.
     * RDB$RELATION_FIELDS) for as long as the zombies live. Verified via
     * fb_lock_print on 2026-08-24: 6 S2@SW locks owned by attachment A
     * (rel ids 1,2,5,6,9,18) during exactly this sequence.
     *
     * Attachment B: independent connection compiling a SERIALIZABLE NOWAIT
     * INSERT against RDB$RELATION_FIELDS must NOT hit the retained locks -
     * with eager result-freeing (#176 direction 1) the cursor count is 0 at
     * autocommit and #588's hard-commit path releases everything.
     */
    public function testFetchOneZombiesDoNotBlockSecondAttachmentSerializable(): void
    {
        // 1. Zombie creator: fetchOne consumes ONE row per call; the abandoned
        //    remainder keeps each cursor open until cycle-GC (#176).
        for ($i = 0; $i < 3; $i++) {
            $relationName = $this->connection->fetchOne('SELECT RDB$RELATION_NAME FROM RDB$RELATIONS');
            self::assertNotFalse($relationName);
        }

        // 2. DDL via autocommit while zombies are alive: with open cursors the
        //    ext retains SW catalog locks; with cursor count 0 the #588
        //    hard-commit path drops everything immediately.
        $this->connection->executeStatement(
            sprintf('CREATE TABLE %s_zddl (id INTEGER)', self::PROBE_TABLE),
        );
        $this->createdTables[] = self::PROBE_TABLE . '_zddl';

        // 3. Victim: second attachment, SERIALIZABLE + NOWAIT (mirrors
        //    TransactionTest::testSetIsolationLevelSerializable, the flake site).
        $victimConnection = DriverManager::getConnection($this->connection->getParams());
        self::assertInstanceOf(ConnectionWrapper::class, $victimConnection);

        $victimDriverConnection = $victimConnection->getFirebirdDriverConnection();
        self::assertNotNull($victimDriverConnection);

        $exception = null;

        try {
            $victimDriverConnection->setAttribute(
                FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
                TransactionIsolationLevel::SERIALIZABLE,
            );
            $victimDriverConnection->setAttribute(
                FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT,
                0, // NOWAIT - conflict must surface instantly, not block
            );

            $victimDriverConnection->beginTransaction();

            $insert = $victimDriverConnection->prepare(
                sprintf('INSERT INTO %s (id) VALUES (?)', self::PROBE_TABLE),
            );
            $insert->bindValue(1, 777, ParameterType::INTEGER);
            $insert->execute();
            $victimDriverConnection->commit();
        } catch (Throwable $exception) {
            try {
                $victimDriverConnection->rollBack();
            } catch (Throwable) {
                // already aborted by the lock conflict
            }
        } finally {
            unset($insert, $victimDriverConnection, $victimConnection);
            gc_collect_cycles();
        }

        self::assertNull(
            $exception,
            'Abandoned fetchOne() results kept their cursors open: the DDL autocommit '
            . 'retained system-catalog SW locks and the SERIALIZABLE NOWAIT insert on the '
            . 'second attachment conflicted (#176 / php-firebird#586). '
            . ($exception?->getMessage() ?? ''),
        );
    }

    public function testConnectionIsUsableAfterwards(): void
    {
        // Sanity companion: shared connection must survive the lock-release
        // probe test above (guards against the probe leaving the attachment
        // in a broken state).
        $count = $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s', self::PROBE_TABLE),
        );
        self::assertSame(0, (int) $count);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Table created through the SchemaManager (introspection-heavy) is
        // fine here: setUp runs BEFORE the zombie is created, and tearDown's
        // gc_collect_cycles() clears any zombies setUp itself produced.
        try {
            $this->connection->executeStatement(sprintf('DROP TABLE %s', self::PROBE_TABLE));
        } catch (Throwable) {
            // table does not exist yet (first run)
        }

        try {
            $this->connection->executeStatement(sprintf('DROP TABLE %s_zddl', self::PROBE_TABLE));
        } catch (Throwable) {
            // leftover guard: a prior RED run may have leaked the DDL probe table
        }

        $this->connection->executeStatement(
            sprintf('CREATE TABLE %s (id INTEGER NOT NULL PRIMARY KEY, val VARCHAR(100))', self::PROBE_TABLE),
        );
        $this->createdTables[] = self::PROBE_TABLE;
    }
}
