<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection as FirebirdConnection;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Regression test for GH-22: FB 2.5 connection resource invalidation.
 *
 * After a connection is closed and re-opened, the new connection resource
 * must be valid and queries must succeed. Previously, stale resource handles
 * could cause "invalid connection handle" errors on subsequent queries.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/22
 */
class GH22Test extends FunctionalTestCase
{
    private const TABLE = 'gh22_regression';

    /**
     * After close() + reconnect, the connection resource must be valid.
     */
    public function testConnectionResourceIsValidAfterReconnect(): void
    {
        // Verify initial connection works
        $result = $this->connection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        self::assertNotFalse($result->fetchOne());

        // Close and reopen
        $this->connection->close();
        $this->connection->connect();

        // Connection must be valid after reconnect
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn instanceof FirebirdConnection) {
            self::assertTrue($fbirdConn->isConnectionValid(), 'Connection resource must be valid after reconnect');
        }

        // Query must succeed on the reconnected connection
        $result2 = $this->connection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        self::assertNotFalse($result2->fetchOne());
    }

    /**
     * Schema operations work correctly after connection close/reopen.
     */
    public function testSchemaOperationsAfterReconnect(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::STRING, ['length' => 50]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        // Close and reopen
        $this->connection->close();
        $this->connection->connect();

        // Schema operations must work on the new connection
        $schemaManager = $this->connection->createSchemaManager();
        self::assertTrue($schemaManager->tablesExist([self::TABLE]));
    }

    /**
     * DML operations work correctly after connection close/reopen.
     */
    public function testDmlAfterReconnect(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
        $this->connection->insert(self::TABLE, ['id' => 1]);

        // Close and reopen
        $this->connection->close();
        $this->connection->connect();

        // DML must work on the new connection
        $this->connection->insert(self::TABLE, ['id' => 2]);
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);
        self::assertSame('2', (string) $count);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->markConnectionNotReusable();
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE);

        parent::tearDown();
    }
}
