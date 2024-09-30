<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use PHPUnit\Framework\Constraint\IsType;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

abstract class AbstractDriverTestCase extends FunctionalTestCase
{
    /**
     * The driver instance under test.
     */
    protected Driver $driver;

    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = $this->driver->connect($params);

        self::assertInstanceOf(DriverConnection::class, $connection);
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        $params = $this->connection->getParams();
        unset($params['dbname']);

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
            $this->connection->getEventManager(),
        );

        self::assertSame(static::getDatabaseNameForConnectionWithoutDatabaseNameParameter(), $connection->getDatabase());
    }

    public function testProvidesAccessToTheNativeConnection(): void
    {
        $nativeConnection = $this->connection->getNativeConnection();

        self::assertThat($nativeConnection, self::logicalOr(
            new IsType(IsType::TYPE_OBJECT),
            new IsType(IsType::TYPE_RESOURCE),
        ));
    }

    protected function setUp(): void
    {
        $this->driver = $this->createDriver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): string|null
    {
        return null;
    }

    abstract protected function createDriver(): Driver;
}
