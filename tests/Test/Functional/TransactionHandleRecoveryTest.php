<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Schema\Table;
use Firebird\Transaction;
use ReflectionProperty;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection as DriverConnection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\TransactionManager;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function fbird_commit;

/**
 * Regression tests for #187: when the native transaction handle behind the
 * TransactionManager dies (the extension commits/restarts it transparently,
 * e.g. on the #586 idle-lock release or a #540/#566 DDL restart), the driver
 * must heal its own state machine on the next operation.
 *
 * Before the fix, every later request on the same connection failed with
 * -999 ("invalid transaction handle" / "OO API connection/transaction
 * pointers are NULL") and never recovered.
 */
final class TransactionHandleRecoveryTest extends FunctionalTestCase
{
    private const TABLE = 'transaction_handle_recovery';

    public function testAutocommitSurvivesTransactionHandleKilledBehindManagerBack(): void
    {
        $this->createTestTable();
        $transactionManager = $this->getTransactionManager();

        // Kill the native handle of the active transaction behind the manager's
        // back - the exact effect of the extension's transparent commit+restart
        // paths (#586/#540/#566) when their restart step fails.
        $activeTransaction = $transactionManager->getResource();
        self::assertInstanceOf(Transaction::class, $activeTransaction);
        fbird_commit($activeTransaction);

        self::assertTrue($transactionManager->isTransactionValid(), 'Manager still holds the (dead) handle object');

        // "Next request": plain autocommit usage must heal, not fail with -999.
        $this->connection->insert(self::TABLE, ['id' => 1, 'val' => 'after-restart']);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE val = ?',
            ['after-restart'],
        );
        self::assertSame(1, (int) $count);

        // And the connection keeps working across further statements.
        $this->connection->insert(self::TABLE, ['id' => 2, 'val' => 'still-usable']);
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE val = ?',
            ['still-usable'],
        ));
    }

    public function testAutoCommitHealsDeadHandleInsteadOfThrowing999(): void
    {
        $this->createTestTable();
        $transactionManager = $this->getTransactionManager();

        $activeTransaction = $transactionManager->getResource();
        self::assertInstanceOf(Transaction::class, $activeTransaction);
        fbird_commit($activeTransaction);

        // TransactionManager::autoCommit() is the call site reported in #187
        // (commit_ret on a handle whose native transaction is gone). It must
        // restore the autocommit idle cycle instead of surfacing -999 forever.
        $transactionManager->autoCommit();

        // Healed state: a fresh, usable transaction is active again.
        self::assertTrue($transactionManager->isTransactionValid());
        $this->connection->insert(self::TABLE, ['id' => 3, 'val' => 'auto-commit-healed']);
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE val = ?',
            ['auto-commit-healed'],
        ));
    }

    private function createTestTable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (! $schemaManager->tablesExist([self::TABLE])) {
            $table = new Table(self::TABLE);
            $table->addColumn('id', 'integer');
            $table->addColumn('val', 'string', ['length' => 64]);
            $table->setPrimaryKey(['id']);
            $schemaManager->createTable($table);
        }

        $this->connection->executeStatement('DELETE FROM ' . self::TABLE);
    }

    private function getTransactionManager(): TransactionManager
    {
        $driverConnection = $this->getFirebirdConnection();
        self::assertInstanceOf(DriverConnection::class, $driverConnection);

        $property = new ReflectionProperty(DriverConnection::class, 'transactionManager');

        return $property->getValue($driverConnection);
    }
}
