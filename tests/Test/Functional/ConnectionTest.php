<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use Error;
use PDO;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use Throwable;

class ConnectionTest extends FunctionalTestCase
{
    use VerifyDeprecations;

    private const TABLE = 'connection_test';

    public function testGetWrappedConnection(): void
    {
        self::assertInstanceOf(DriverConnection::class, $this->connection->getWrappedConnection());
    }

    public function testCommitWithRollbackOnlyThrowsException(): void
    {
        $this->connection->beginTransaction();
        $this->connection->setRollbackOnly();

        $this->expectException(ConnectionException::class);
        $this->connection->commit();
    }

    public function testNestingTransactionsWithoutSavepointsIsDeprecated(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test is only supported on platforms that support savepoints.');
        }

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5383');
        $this->connection->setNestTransactionsWithSavepoints(false);
    }

    public function testTransactionNestingBehavior(): void
    {
        $this->createTestTable();

        try {
            $this->connection->beginTransaction();
            self::assertSame(1, $this->connection->getTransactionNestingLevel());

            try {
                $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5383');
                $this->connection->beginTransaction();
                self::assertSame(2, $this->connection->getTransactionNestingLevel());

                $this->connection->insert(self::TABLE, ['id' => 1]);
                self::fail('Expected exception to be thrown because of the unique constraint.');
            } catch (Throwable $e) {
                self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
                $this->connection->rollBack();
                self::assertSame(1, $this->connection->getTransactionNestingLevel());
            }

            self::assertTrue($this->connection->isRollbackOnly());

            $this->connection->commit(); // should throw exception
            self::fail('Transaction commit after failed nested transaction should fail.');
        } catch (ConnectionException) {
            self::assertSame(1, $this->connection->getTransactionNestingLevel());
            $this->connection->rollBack();
            self::assertSame(0, $this->connection->getTransactionNestingLevel());
        }

        $this->connection->beginTransaction();
        $this->connection->close();
        $this->connection->beginTransaction();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionNestingLevelIsResetOnReconnect(): void
    {
        $connection = $this->connection;

        $this->dropTableIfExists('test_nesting');
        $connection->executeQuery('CREATE TABLE test_nesting(test int not null)');

        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        $connection->close(); // connection closed in runtime (for example if lost or another application logic)

        $connection->beginTransaction();
        $connection->executeQuery('insert into test_nesting values (33)');
        $connection->rollBack();

        self::assertSame(0, $connection->fetchOne('select count(*) from test_nesting'));
    }

    public function testTransactionNestingBehaviorWithSavepoints(): void
    {
        $this->createTestTable();

        $this->connection->setNestTransactionsWithSavepoints(true);
        try {
            $this->connection->beginTransaction();
            self::assertSame(1, $this->connection->getTransactionNestingLevel());

            try {
                $this->connection->beginTransaction();
                self::assertSame(2, $this->connection->getTransactionNestingLevel());
                $this->connection->beginTransaction();
                self::assertSame(3, $this->connection->getTransactionNestingLevel());
                self::assertTrue($this->connection->commit());
                self::assertSame(2, $this->connection->getTransactionNestingLevel());

                $this->connection->insert(self::TABLE, ['id' => 1]);
                self::fail('Expected exception to be thrown because of the unique constraint.');
            } catch (Throwable $e) {
                self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
                $this->connection->rollBack();
                self::assertSame(1, $this->connection->getTransactionNestingLevel());
            }

            self::assertFalse($this->connection->isRollbackOnly());
            try {
                $this->connection->setNestTransactionsWithSavepoints(false);
                self::fail('Should not be able to disable savepoints in usage inside a nested open transaction.');
            } catch (ConnectionException) {
                self::assertTrue($this->connection->getNestTransactionsWithSavepoints());
            }

            $this->connection->commit(); // should not throw exception
        } catch (ConnectionException) {
            self::fail('Transaction commit after failed nested transaction should not fail when using savepoints.');
        }
    }

    public function testTransactionNestingBehaviorCantBeChangedInActiveTransaction(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform to support savepoints.');
        }

        $this->connection->beginTransaction();
        $this->expectException(ConnectionException::class);
        $this->connection->setNestTransactionsWithSavepoints(true);
    }

    public function testTransactionIsInactiveAfterConnectionClose(): void
    {
        $this->connection->beginTransaction();
        $this->connection->close();

        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testRollbackSavepointsNotSupportedThrowsException(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsSavepoints()) {
            self::markTestSkipped('This test requires the platform not to support savepoints.');
        }

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Savepoints are not supported by this driver.');

        $this->connection->rollbackSavepoint('foo');
    }

    public function testTransactionBehaviorWithRollback(): void
    {
        $this->createTestTable();

        try {
            $this->connection->beginTransaction();
            self::assertSame(1, $this->connection->getTransactionNestingLevel());

            $this->connection->insert(self::TABLE, ['id' => 1]);
            self::fail('Expected exception to be thrown because of the unique constraint.');
        } catch (Throwable $e) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
            self::assertSame(1, $this->connection->getTransactionNestingLevel());
            $this->connection->rollBack();
            self::assertSame(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionBehaviour(): void
    {
        $this->createTestTable();

        $this->connection->beginTransaction();
        self::assertSame(1, $this->connection->getTransactionNestingLevel());
        $this->connection->insert(self::TABLE, ['id' => 2]);
        $this->connection->commit();
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        $this->connection->close();
    }

    public function testTransactionalWithException(): void
    {
        $this->createTestTable();

        try {
            $this->connection->transactional(static function (Connection $connection): void {
                $connection->insert(self::TABLE, ['id' => 1]);
            });
            self::fail('Expected exception to be thrown because of the unique constraint.');
        } catch (Throwable $e) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $e);
            self::assertSame(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testTransactionalWithThrowable(): void
    {
        try {
            $this->connection->transactional(static function (Connection $conn): void {
                $conn->executeQuery($conn->getDatabasePlatform()->getDummySelectSQL());

                throw new Error('Ooops!');
            });
            self::fail('Expected exception');
        } catch (Error) {
            self::assertSame(0, $this->connection->getTransactionNestingLevel());
        }
    }

    /** @throws Throwable */
    public function testTransactional(): void
    {
        $this->createTestTable();

        $res = $this->connection->transactional(static fn (Connection $connection) => $connection->insert(self::TABLE, ['id' => 2]));

        self::assertSame(1, $res);
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTransactionalReturnValue(): void
    {
        $res = $this->connection->transactional(static fn (): int => 42);

        self::assertSame(42, $res);
    }

    /**
     * Tests that the quote function accepts DBAL and PDO types.
     */
    public function testQuote(): void
    {
        self::assertEquals($this->connection->quote('foo', Types::STRING), $this->connection->quote('foo', ParameterType::STRING));
    }

    public function testConnectWithoutExplicitDatabaseName(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof FirebirdPlatform) {
            self::markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
            $this->connection->getEventManager(),
        );

        self::assertTrue($connection->connect());

        $connection->close();
    }

    public function testDeterminesDatabasePlatformWhenConnectingToNonExistentDatabase(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof FirebirdPlatform) {
            self::markTestSkipped('Platform does not support connecting without database name.');
        }

        $params = $this->connection->getParams();

        $params['dbname'] = 'foo_bar';

        $connection = DriverManager::getConnection(
            $params,
            $this->connection->getConfiguration(),
            $this->connection->getEventManager(),
        );

        self::assertInstanceOf(AbstractPlatform::class, $connection->getDatabasePlatform());
        self::assertSame($params, $connection->getParams());

        $connection->close();
    }

    public function testPersistentConnection(): void
    {
        $this->connection->getDatabasePlatform();

        $params               = TestUtil::getConnectionParams();
        $params['persistent'] = true;

        $connection       = DriverManager::getConnection($params);
        $driverConnection = $connection->getWrappedConnection();

        self::assertTrue($driverConnection->getAttribute(PDO::ATTR_PERSISTENT));
    }

    public function testExceptionOnExecuteStatement(): void
    {
        $this->expectException(DriverException::class);

        $this->connection->executeStatement('foo');
    }

    public function testExceptionOnExecuteQuery(): void
    {
        $this->expectException(DriverException::class);

        $this->connection->executeQuery('foo');
    }

    /**
     * Some drivers do not check the query server-side even though emulated prepared statements are disabled,
     * so an exception is thrown only eventually.
     */
    public function testExceptionOnPrepareAndExecute(): void
    {
        $this->expectException(DriverException::class);

        $this->connection->prepare('foo')->executeStatement();
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    private function createTestTable(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert(self::TABLE, ['id' => 1]);
    }
}
