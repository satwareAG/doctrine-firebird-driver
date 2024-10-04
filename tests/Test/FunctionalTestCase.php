<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection as FirebirdConnection;

use function array_merge;

abstract class FunctionalTestCase extends TestCase
{
    protected Connection $connection;

    /**
     * Whether the shared connection could be reused by subsequent tests.
     */
    private bool $isConnectionReusable = true;

    /**
     * Shared connection when a TestCase is run alone (outside of it's functional suite)
     */
    private static Connection|null $sharedConnection = null;

    /**
     * Drops the table with the specified name, if it exists.
     *
     * @throws Exception
     */
    public function dropTableIfExists(string $name): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        try {
            $schemaManager->dropTable($name);
        } catch (DatabaseObjectNotFoundException) {
        }
    }

    /**
     * Drops and creates a new table.
     *
     * @throws Exception
     */
    public function dropAndCreateTable(Table $table): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $platform      = $this->connection->getDatabasePlatform();
        $tableName     = $table->getQuotedName($platform);

        $this->dropTableIfExists($tableName);
        $schemaManager->createTable($table);
    }

    /**
     * @param array<array-key, mixed> $newParams
     *
     * @throws Exception
     *
     * @psalm-import-type OverrideParams from DriverManager
     */
    public function reConnect(array $newParams = []): Connection
    {
        $params = array_merge($this->connection->getParams(), $newParams);

        return DriverManager::getConnection($params);
    }

    public function getFirebirdConnection(): FirebirdConnection|null
    {
        while ($connection = $this->connection->getWrappedConnection()) {
            if ($connection instanceof FirebirdConnection) {
                return $connection;
            }
        }

        return null;
    }

    /**
     * Mark shared connection not reusable for subsequent tests.
     *
     * Should be called by the tests that modify configuration
     * or alter the connection state in another way that may impact other tests.
     */
    protected function markConnectionNotReusable(): void
    {
        $this->isConnectionReusable = false;
    }

    #[Before]
    final protected function connect(): void
    {
        if (! self::$sharedConnection instanceof Connection) {
            self::$sharedConnection = TestUtil::getConnection();
        }

        $this->connection = self::$sharedConnection;
    }

    #[After]
    final protected function disconnect(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        if ($this->isConnectionReusable) {
            return;
        }

        if (self::$sharedConnection instanceof Connection) {
            self::$sharedConnection->close();
            self::$sharedConnection = null;
        }

        // Make sure the connection is no longer available to the test.
        // Otherwise, there is a chance that a teardown method of the test will reconnect
        // (e.g. to drop a table), and then this reopened connection will remain open and attached to the PHPUnit result
        // until the end of the suite leaking connection resources, while subsequent tests will use
        // the newly established shared connection.
        unset($this->connection);

        $this->isConnectionReusable = true;
    }
}
