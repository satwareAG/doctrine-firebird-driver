<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Connection;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function uniqid;

/**
 * Functional tests for Connection::executeAuto().
 *
 * executeAuto() wraps fbird_execute_auto() which runs a SQL statement in an
 * autonomous transaction that is automatically committed on success or rolled
 * back on failure — independent of the DBAL-managed transaction.
 */
class ExecuteAutoTest extends FunctionalTestCase
{
    private string $tableName;

    /**
     * executeAuto() with an INSERT returns true (autonomous transaction committed).
     *
     * NOTE: fbird_execute_auto() cannot be used with SELECT statements — it uses
     * an autonomous transaction that is committed immediately, which would close
     * any cursor. Use executeQuery() for SELECT statements instead.
     */
    public function testExecuteAutoInsertReturnsTrueResult(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $result = $conn->executeAuto(
            'INSERT INTO ' . $this->tableName . " (id, val) VALUES (99, 'probe')",
        );

        self::assertNotFalse($result, 'executeAuto() INSERT should return true, not false');

        // Verify the row was committed
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName . ' WHERE id = 99');
        self::assertSame(1, (int) $count, 'Row inserted via executeAuto() must be committed');
    }

    /**
     * executeAuto() INSERT is committed autonomously — visible even after
     * the DBAL-managed transaction is rolled back.
     */
    public function testExecuteAutoInsertIsAutoCommitted(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        // Insert a row via executeAuto (autonomous transaction — auto-committed)
        $conn->executeAuto(
            'INSERT INTO ' . $this->tableName . " (id, val) VALUES (1, 'auto')",
        );

        // Insert a row via the DBAL-managed transaction, then roll it back
        // The autonomous insert must survive the rollback
        $this->connection->beginTransaction();
        $this->connection->executeStatement(
            'INSERT INTO ' . $this->tableName . " (id, val) VALUES (2, 'dbal')",
        );
        $this->connection->rollBack();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName . ' WHERE id = 1');
        self::assertSame(1, (int) $count, 'Row inserted via executeAuto() must survive DBAL rollback');

        $count2 = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName . ' WHERE id = 2');
        self::assertSame(0, (int) $count2, 'Row inserted via DBAL transaction must be rolled back');
    }

    /**
     * executeAuto() with bind parameters inserts the correct values.
     */
    public function testExecuteAutoWithParams(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $conn->executeAuto(
            'INSERT INTO ' . $this->tableName . ' (id, val) VALUES (?, ?)',
            [42, 'param_value'],
        );

        $val = $this->connection->fetchOne(
            'SELECT val FROM ' . $this->tableName . ' WHERE id = 42',
        );
        self::assertSame('param_value', $val, 'executeAuto() with params must bind values correctly');
    }

    /**
     * executeAuto() with null params array behaves the same as empty params.
     * Uses INSERT (not SELECT) because fbird_execute_auto() cannot be used with SELECT.
     */
    public function testExecuteAutoWithNullParams(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        // null params should be treated as [] internally — use INSERT, not SELECT
        $result = $conn->executeAuto(
            'INSERT INTO ' . $this->tableName . " (id, val) VALUES (100, 'null_params')",
            null,
        );

        self::assertNotFalse($result, 'executeAuto() with null params should succeed');
    }

    /**
     * executeAuto() with invalid SQL throws a Throwable (Firebird\Exception or DriverException).
     * The php-firebird extension may throw Firebird\Exception directly before our wrapper catches it.
     */
    public function testExecuteAutoWithInvalidSqlThrowsException(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $this->expectException(\Throwable::class);
        $conn->executeAuto('THIS IS NOT VALID SQL AT ALL');
    }

    /**
     * Multiple executeAuto() calls are each independently committed.
     */
    public function testMultipleExecuteAutoCallsAreIndependent(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $conn->executeAuto('INSERT INTO ' . $this->tableName . " (id, val) VALUES (1, 'first')");
        $conn->executeAuto('INSERT INTO ' . $this->tableName . " (id, val) VALUES (2, 'second')");
        $conn->executeAuto('INSERT INTO ' . $this->tableName . " (id, val) VALUES (3, 'third')");

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName);
        self::assertSame(3, (int) $count, 'All three autonomous inserts must be committed');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tableName = 'exec_auto_' . uniqid();
        $this->connection->executeStatement(
            'CREATE TABLE ' . $this->tableName . ' (id INT NOT NULL PRIMARY KEY, val VARCHAR(64))',
        );
        $this->getFirebirdConnection()?->commit();
    }

    protected function tearDown(): void
    {
        try {
            $this->dropTableIfExists($this->tableName);
        } catch (Throwable) {
            // Best-effort cleanup
        }

        parent::tearDown();
    }
}
