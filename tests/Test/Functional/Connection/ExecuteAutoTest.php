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
     * executeAuto() with a SELECT returns a result resource (truthy).
     */
    public function testExecuteAutoSelectReturnsTruthyResult(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $result = $conn->executeAuto('SELECT 1 FROM RDB$DATABASE');

        self::assertNotFalse($result, 'executeAuto() SELECT should return a result resource, not false');
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

        // Roll back the DBAL-managed transaction — should NOT affect the autonomous insert
        $this->connection->rollBack();
        $this->connection->beginTransaction();

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName . ' WHERE id = 1');
        self::assertSame(1, (int) $count, 'Row inserted via executeAuto() must survive DBAL rollback');
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
     */
    public function testExecuteAutoWithNullParams(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        // null params should be treated as [] internally
        $result = $conn->executeAuto('SELECT 1 FROM RDB$DATABASE', null);

        self::assertNotFalse($result, 'executeAuto() with null params should succeed');
    }

    /**
     * executeAuto() with invalid SQL throws a DriverException.
     */
    public function testExecuteAutoWithInvalidSqlThrowsException(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $this->expectException(DriverException::class);
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
