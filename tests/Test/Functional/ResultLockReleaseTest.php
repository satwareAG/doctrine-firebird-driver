<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\TransactionIsolationLevel;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

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

        $this->connection->executeStatement(
            sprintf('CREATE TABLE %s (id INTEGER NOT NULL PRIMARY KEY, val VARCHAR(100))', self::PROBE_TABLE),
        );
        $this->createdTables[] = self::PROBE_TABLE;
    }

    /**
     * A result consumed via fetchOne() (first row fetched, remainder abandoned
     * by DBAL contract) must not keep its cursor open: the following DDL
     * autocommit must not retain system-catalog locks, so a subsequent
     * SERIALIZABLE (SNAPSHOT TABLE STABILITY, no-wait) insert compiling
     * against RDB$RELATION_FIELDS succeeds instead of deadlocking on the
     * connection's own retained locks.
     */
    public function testFetchOneAbandonmentDoesNotRetainSystemCatalogLocks(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        // 1. Zombie creator: multi-row catalog SELECT consumed via fetchOne().
        //    The DBAL wrapper is discarded afterwards; with the driver holding
        //    Statement::$currentResult the cursor stays open (#176).
        $relationName = $this->connection->fetchOne('SELECT RDB$RELATION_NAME FROM RDB$RELATIONS');
        self::assertNotFalse($relationName);

        // 2. DDL via autocommit while the abandoned cursor is (not) alive.
        //    With an open cursor the ext must use commit_ret, which retains
        //    SW locks on RDB$RELATION_FIELDS; with the cursor released the
        //    lazy release path (#588) drops them at once.
        $this->connection->executeStatement(
            sprintf('CREATE TABLE %s_ddl (id INTEGER)', self::PROBE_TABLE),
        );
        $this->createdTables[] = self::PROBE_TABLE . '_ddl';

        // 3. Conflict probe: mirror testSetIsolationLevelSerializable. The new
        //    auto-commit transaction after commit() runs SERIALIZABLE and
        //    compiles the INSERT against RDB$RELATION_FIELDS under
        //    SNAPSHOT TABLE STABILITY no-wait locks.
        $exception = null;

        try {
            $fbirdConn->setAttribute(
                FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
                TransactionIsolationLevel::SERIALIZABLE,
            );

            $this->connection->beginTransaction();
            $this->connection->insert(self::PROBE_TABLE, ['id' => 1, 'val' => 'probe']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            // rollback the still-open serializable transaction so tearDown
            // runs against a clean transaction state
            try {
                $this->connection->rollBack();
            } catch (Throwable) {
                // already closed by the failure
            }
        } finally {
            $fbirdConn->setAttribute(
                FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
                TransactionIsolationLevel::READ_COMMITTED,
            );
            $this->markConnectionNotReusable();
        }

        self::assertNull(
            $exception,
            'Abandoned fetchOne() result kept its cursor open: the DDL autocommit retained '
            . 'system-catalog locks and the SERIALIZABLE no-wait insert conflicted. '
            . ($exception !== null ? $exception->getMessage() : ''),
        );

        unset($relationName);
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
}
