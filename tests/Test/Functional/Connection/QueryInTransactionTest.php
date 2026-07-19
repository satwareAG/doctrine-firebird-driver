<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Connection;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function class_exists;
use function uniqid;
use function version_compare;

/**
 * Functional tests for Connection::queryInTransaction().
 *
 * queryInTransaction() wraps fbird_query_params_tx() — a capability unique to
 * php-firebird that executes SQL within a *specific* transaction context.
 *
 * Key use cases:
 * - Audit logging that persists regardless of main transaction outcome (CQRS)
 * - Multi-transaction workflows with different isolation levels
 * - Long-running batches with independent progress tracking
 */
class QueryInTransactionTest extends FunctionalTestCase
{
    private string $tableName;

    /**
     * queryInTransaction() with the active transaction executes a SELECT successfully.
     */
    public function testQueryInTransactionSelectWithActiveTransaction(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $tx = $conn->getActiveTransaction();
        self::assertNotNull($tx, 'Active transaction must exist');

        $result = $conn->queryInTransaction($tx, 'SELECT 1 FROM RDB$DATABASE');

        self::assertNotFalse($result, 'queryInTransaction() SELECT must return a result resource');
    }

    /**
     * queryInTransaction() INSERT within the active transaction is visible
     * to subsequent queries in the same transaction.
     */
    public function testQueryInTransactionInsertIsVisibleInSameTransaction(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $tx = $conn->getActiveTransaction();
        self::assertNotNull($tx, 'Active transaction must exist');

        $conn->queryInTransaction(
            $tx,
            'INSERT INTO ' . $this->tableName . " (id, val) VALUES (1, 'txn_row')",
        );

        // Query in the same transaction — row must be visible
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName . ' WHERE id = 1');
        self::assertSame(1, (int) $count, 'Row inserted via queryInTransaction() must be visible in same transaction');
    }

    /**
     * queryInTransaction() with bind parameters binds values correctly.
     */
    public function testQueryInTransactionWithParams(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $tx = $conn->getActiveTransaction();
        self::assertNotNull($tx, 'Active transaction must exist');

        $conn->queryInTransaction(
            $tx,
            'INSERT INTO ' . $this->tableName . ' (id, val) VALUES (?, ?)',
            [99, 'bound_value'],
        );

        $val = $this->connection->fetchOne(
            'SELECT val FROM ' . $this->tableName . ' WHERE id = 99',
        );
        self::assertSame('bound_value', $val, 'queryInTransaction() must bind parameters correctly');
    }

    /**
     * queryInTransaction() with an independent transaction allows CQRS-style
     * isolation: the independent transaction can be committed independently
     * of the main DBAL transaction.
     *
     * Requires Firebird\TBuilder (php-firebird v7.1+ OO API, Firebird 4.0+).
     */
    public function testQueryInTransactionWithIndependentTransaction(): void
    {
        $this->requireFirebird4OrHigher();

        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        // Create an independent transaction (e.g. for audit logging)
        $auditTx = $conn->createIndependentTransaction()->start();

        $conn->queryInTransaction(
            $auditTx,
            'INSERT INTO ' . $this->tableName . " (id, val) VALUES (10, 'audit')",
        );

        // Commit the independent transaction
        $auditTx->commit();

        // Insert a DBAL-managed row and roll it back — audit row must still be visible
        $this->connection->beginTransaction();
        $this->connection->executeStatement(
            'INSERT INTO ' . $this->tableName . " (id, val) VALUES (11, 'dbal')",
        );
        $this->connection->rollBack();

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->tableName . ' WHERE id = 10',
        );
        self::assertSame(1, (int) $count, 'Row committed in independent transaction must survive DBAL rollback');
    }

    /**
     * queryInTransaction() with invalid SQL throws a Throwable.
     * The php-firebird extension may throw Firebird\Exception directly.
     */
    public function testQueryInTransactionWithInvalidSqlThrowsException(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $tx = $conn->getActiveTransaction();
        self::assertNotNull($tx, 'Active transaction must exist');

        $this->expectException(Throwable::class);
        $conn->queryInTransaction($tx, 'THIS IS NOT VALID SQL AT ALL');
    }

    /**
     * queryInTransaction() with a non-resource transaction throws a DriverException.
     */
    public function testQueryInTransactionWithInvalidTransactionThrowsException(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $this->expectException(DriverException::class);
        // Pass a non-resource value as transaction
        $conn->queryInTransaction('not-a-resource', 'SELECT 1 FROM RDB$DATABASE');
    }

    /**
     * createIndependentTransaction() returns a TBuilder that can start a transaction.
     *
     * Requires Firebird\TBuilder (php-firebird v7.1+ OO API, Firebird 4.0+).
     */
    public function testCreateIndependentTransactionReturnsTBuilder(): void
    {
        $this->requireFirebird4OrHigher();

        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $builder = $conn->createIndependentTransaction();

        self::assertNotNull($builder, 'createIndependentTransaction() must return a TBuilder');

        // Start and immediately commit to verify it works
        $tx = $builder->start();
        self::assertNotNull($tx, 'TBuilder::start() must return a Transaction');
        $tx->commit();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tableName = 'qit_test_' . uniqid();
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

    /**
     * Skip test when Firebird server < 4.0 or TBuilder class unavailable.
     *
     * The php-firebird extension defines TBuilder even when connected to
     * Firebird 3.0, but the server does not support independent transactions,
     * causing "Invalid transaction resource" errors.
     */
    private function requireFirebird4OrHigher(): void
    {
        if (! class_exists('Firebird\TBuilder')) {
            self::markTestSkipped('Firebird\TBuilder requires php-firebird v7.1+ OO API.');
        }

        $version = $this->connection->fetchOne(
            "SELECT rdb\$get_context('SYSTEM', 'ENGINE_VERSION') FROM RDB\$DATABASE",
        );

        if (version_compare((string) $version, '4.0', '>=')) {
            return;
        }

        self::markTestSkipped('Independent transactions require Firebird 4.0+ server (found ' . $version . ').');
    }
}
