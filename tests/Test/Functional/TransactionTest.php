<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_change_key_case;

use const CASE_LOWER;

/**
 * Functional tests for Firebird transaction management.
 *
 * Covers:
 * - beginTransaction() / commit() / rollBack()
 * - Savepoints (createSavepoint, releaseSavepoint, rollbackSavepoint)
 * - Isolation levels (READ COMMITTED, REPEATABLE READ, SERIALIZABLE)
 * - Nested transactions via savepoints
 * - Transaction state tracking
 *
 * Closes #65
 */
class TransactionTest extends FunctionalTestCase
{
    private const TABLE = 'transaction_test';

    public function testBeginTransactionAndCommit(): void
    {
        $this->connection->beginTransaction();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());

        $this->connection->insert(self::TABLE, ['id' => 1, 'val' => 'committed']);
        $this->connection->commit();

        self::assertSame(0, $this->connection->getTransactionNestingLevel());

        $row = $this->connection->fetchAssociative(
            'SELECT val FROM ' . self::TABLE . ' WHERE id = 1',
        );
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertSame('committed', $row['val']);
    }

    public function testBeginTransactionAndRollBack(): void
    {
        $this->connection->beginTransaction();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());

        $this->connection->insert(self::TABLE, ['id' => 2, 'val' => 'rolled_back']);
        $this->connection->rollBack();

        self::assertSame(0, $this->connection->getTransactionNestingLevel());

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 2',
        );
        self::assertSame(0, (int) $count);
    }

    public function testTransactionIsInactiveAfterCommit(): void
    {
        $this->connection->beginTransaction();
        self::assertTrue($this->connection->isTransactionActive());

        $this->connection->commit();
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testTransactionIsInactiveAfterRollBack(): void
    {
        $this->connection->beginTransaction();
        self::assertTrue($this->connection->isTransactionActive());

        $this->connection->rollBack();
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testTransactionNestingLevelStartsAtZero(): void
    {
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testMultipleRowsInSingleTransaction(): void
    {
        $this->connection->beginTransaction();

        $this->connection->insert(self::TABLE, ['id' => 10, 'val' => 'row_a']);
        $this->connection->insert(self::TABLE, ['id' => 11, 'val' => 'row_b']);
        $this->connection->insert(self::TABLE, ['id' => 12, 'val' => 'row_c']);

        $this->connection->commit();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);
        self::assertSame(3, (int) $count);
    }

    public function testRollBackUndoesAllRowsInTransaction(): void
    {
        $this->connection->beginTransaction();

        $this->connection->insert(self::TABLE, ['id' => 20, 'val' => 'x']);
        $this->connection->insert(self::TABLE, ['id' => 21, 'val' => 'y']);

        $this->connection->rollBack();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);
        self::assertSame(0, (int) $count);
    }

    /**
     * Savepoints
     */
    public function testSavepointCreateAndRelease(): void
    {
        $this->connection->setNestTransactionsWithSavepoints(true);
        $this->connection->beginTransaction();

        $this->connection->insert(self::TABLE, ['id' => 30, 'val' => 'before_sp']);

        // Nested transaction creates a savepoint
        $this->connection->beginTransaction();
        self::assertSame(2, $this->connection->getTransactionNestingLevel());

        $this->connection->insert(self::TABLE, ['id' => 31, 'val' => 'in_sp']);

        // Release savepoint (inner commit)
        $this->connection->commit();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());

        // Outer commit persists everything
        $this->connection->commit();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);
        self::assertSame(2, (int) $count);
    }

    public function testSavepointRollbackRestoresPartialState(): void
    {
        $this->connection->setNestTransactionsWithSavepoints(true);
        $this->connection->beginTransaction();

        $this->connection->insert(self::TABLE, ['id' => 40, 'val' => 'outer_row']);

        // Nested transaction (savepoint)
        $this->connection->beginTransaction();
        $this->connection->insert(self::TABLE, ['id' => 41, 'val' => 'inner_row']);

        // Roll back inner transaction (to savepoint)
        $this->connection->rollBack();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());

        // Outer row should still be present after outer commit
        $this->connection->commit();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);
        self::assertSame(1, (int) $count);

        $row = $this->connection->fetchAssociative(
            'SELECT val FROM ' . self::TABLE . ' WHERE id = 40',
        );
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertSame('outer_row', $row['val']);
    }

    public function testDriverLevelSavepointCreateAndRelease(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $this->connection->beginTransaction();

        $fbirdConn->createSavepoint('SP_TEST_1');
        $this->connection->insert(self::TABLE, ['id' => 50, 'val' => 'sp_row']);
        $fbirdConn->releaseSavepoint('SP_TEST_1');

        $this->connection->commit();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 50',
        );
        self::assertSame(1, (int) $count);
    }

    public function testDriverLevelSavepointRollback(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $this->connection->beginTransaction();

        $this->connection->insert(self::TABLE, ['id' => 60, 'val' => 'before_sp']);

        $fbirdConn->createSavepoint('SP_ROLLBACK_TEST');
        $this->connection->insert(self::TABLE, ['id' => 61, 'val' => 'after_sp']);

        // Roll back to savepoint — row 61 should disappear
        $fbirdConn->rollbackSavepoint('SP_ROLLBACK_TEST');

        $this->connection->commit();

        $count60 = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 60',
        );
        $count61 = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 61',
        );

        self::assertSame(1, (int) $count60, 'Row before savepoint should be committed');
        self::assertSame(0, (int) $count61, 'Row after savepoint should be rolled back');
    }

    public function testThreeLevelNestedTransactionsWithSavepoints(): void
    {
        $this->connection->setNestTransactionsWithSavepoints(true);

        $this->connection->beginTransaction(); // level 1
        $this->connection->insert(self::TABLE, ['id' => 70, 'val' => 'level1']);

        $this->connection->beginTransaction(); // level 2 (savepoint)
        $this->connection->insert(self::TABLE, ['id' => 71, 'val' => 'level2']);

        $this->connection->beginTransaction(); // level 3 (savepoint)
        $this->connection->insert(self::TABLE, ['id' => 72, 'val' => 'level3']);

        self::assertSame(3, $this->connection->getTransactionNestingLevel());

        $this->connection->commit(); // release level 3 savepoint
        self::assertSame(2, $this->connection->getTransactionNestingLevel());

        $this->connection->rollBack(); // rollback level 2 savepoint (loses 71 and 72)
        self::assertSame(1, $this->connection->getTransactionNestingLevel());

        $this->connection->commit(); // commit level 1 (only 70 persists)

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);
        self::assertSame(1, (int) $count);

        $row = $this->connection->fetchAssociative(
            'SELECT val FROM ' . self::TABLE . ' WHERE id = 70',
        );
        self::assertNotFalse($row);
        $row = array_change_key_case($row, CASE_LOWER);
        self::assertSame('level1', $row['val']);
    }

    /**
     * Isolation levels
     */
    public function testSetIsolationLevelReadCommitted(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $fbirdConn->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            TransactionIsolationLevel::READ_COMMITTED,
        );

        $this->connection->beginTransaction();
        $this->connection->insert(self::TABLE, ['id' => 80, 'val' => 'read_committed']);
        $this->connection->commit();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 80',
        );
        self::assertSame(1, (int) $count);
    }

    public function testSetIsolationLevelRepeatableRead(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $fbirdConn->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            TransactionIsolationLevel::REPEATABLE_READ,
        );

        $this->connection->beginTransaction();
        $this->connection->insert(self::TABLE, ['id' => 90, 'val' => 'repeatable_read']);
        $this->connection->commit();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 90',
        );
        self::assertSame(1, (int) $count);
    }

    public function testSetIsolationLevelSerializable(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $fbirdConn->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            TransactionIsolationLevel::SERIALIZABLE,
        );

        $this->connection->beginTransaction();
        $this->connection->insert(self::TABLE, ['id' => 100, 'val' => 'serializable']);
        $this->connection->commit();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 100',
        );
        self::assertSame(1, (int) $count);
    }

    public function testGetSetTransactionIsolationSQLReadCommitted(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $sql      = $platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED);

        self::assertStringContainsString('READ COMMITTED', $sql);
        self::assertStringContainsString('SET TRANSACTION', $sql);
    }

    public function testGetSetTransactionIsolationSQLRepeatableRead(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $sql      = $platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ);

        self::assertStringContainsString('SNAPSHOT', $sql);
    }

    public function testGetSetTransactionIsolationSQLSerializable(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $sql      = $platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE);

        self::assertStringContainsString('SNAPSHOT TABLE STABILITY', $sql);
    }

    /**
     * Transaction state
     */
    public function testTransactionActiveStateTracking(): void
    {
        self::assertFalse($this->connection->isTransactionActive());

        $this->connection->beginTransaction();
        self::assertTrue($this->connection->isTransactionActive());

        $this->connection->commit();
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testDriverConnectionTransactionValid(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        self::assertTrue($fbirdConn->isConnectionValid());
        self::assertTrue($fbirdConn->isTransactionValid());
    }

    public function testDriverConnectionActiveTransactionResource(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $tx = $fbirdConn->getActiveTransaction();
        self::assertIsResource($tx);
    }

    public function testTransactionalHelperCommitsOnSuccess(): void
    {
        $result = $this->connection->transactional(function () {
            $this->connection->insert(self::TABLE, ['id' => 110, 'val' => 'transactional']);

            return 'ok';
        });

        self::assertSame('ok', $result);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 110',
        );
        self::assertSame(1, (int) $count);
    }

    public function testTransactionalHelperRollsBackOnException(): void
    {
        try {
            $this->connection->transactional(function (): void {
                $this->connection->insert(self::TABLE, ['id' => 120, 'val' => 'will_rollback']);

                throw new RuntimeException('Intentional failure');
            });
        } catch (RuntimeException) {
            // expected
        }

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 120',
        );
        self::assertSame(0, (int) $count);
    }

    /**
     * Auto-commit behaviour
     */
    public function testAutoCommitInsertsAreVisibleWithoutExplicitTransaction(): void
    {
        // Without beginTransaction(), inserts are auto-committed
        $this->connection->insert(self::TABLE, ['id' => 130, 'val' => 'auto_commit']);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = 130',
        );
        self::assertSame(1, (int) $count);
    }

    /**
     * FirebirdConnection attribute access
     */
    public function testGetAttributeIsolationLevel(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $level = $fbirdConn->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL);
        // DBAL4: TransactionIsolationLevel is an enum; getAttribute returns enum case
        self::assertInstanceOf(TransactionIsolationLevel::class, $level);
    }

    public function testGetAttributeTransactionWait(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $wait = $fbirdConn->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT);
        self::assertIsInt($wait);
    }

    public function testSetAndGetIsolationLevelAttribute(): void
    {
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn === null) {
            self::markTestSkipped('Firebird driver connection not available.');
        }

        $fbirdConn->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            TransactionIsolationLevel::SERIALIZABLE,
        );

        $level = $fbirdConn->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL);
        self::assertSame(TransactionIsolationLevel::SERIALIZABLE, $level);

        // Restore default
        $fbirdConn->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            TransactionIsolationLevel::READ_COMMITTED,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::STRING, ['length' => 100, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();

        parent::tearDown();
    }
}
