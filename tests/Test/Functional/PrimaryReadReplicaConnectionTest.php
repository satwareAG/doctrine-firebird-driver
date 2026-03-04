<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

/**
 * Tests the PrimaryReadReplicaConnection pattern with Firebird.
 *
 * Gap analysis §1.3 — mirrors DBAL 3.10.x Functional/PrimaryReadReplicaConnectionTest.php.
 *
 * NOTE: A true primary/replica split requires two separate Firebird instances.
 * In single-instance CI environments, both primary and replica point to the same
 * database, which validates the connection-switching logic without requiring
 * a real replication setup.
 *
 * Closes #76
 */
class PrimaryReadReplicaConnectionTest extends FunctionalTestCase
{
    private PrimaryReadReplicaConnection $primaryReplicaConnection;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(PrimaryReadReplicaConnection::class)) {
            self::markTestSkipped('PrimaryReadReplicaConnection is not available in this DBAL version.');
        }

        $params = TestUtil::getConnectionParams();

        // In CI/single-instance environments, primary and replica point to the same DB.
        // This validates the connection-switching logic without requiring real replication.
        $connectionParams = [
            'wrapperClass' => PrimaryReadReplicaConnection::class,
            'driver'       => $params['driver'] ?? null,
            'driverClass'  => $params['driverClass'] ?? null,
            'host'         => $params['host'] ?? 'localhost',
            'port'         => $params['port'] ?? 3050,
            'dbname'       => $params['dbname'] ?? null,
            'user'         => $params['user'] ?? null,
            'password'     => $params['password'] ?? null,
            'primary'      => [
                'host'     => $params['host'] ?? 'localhost',
                'port'     => $params['port'] ?? 3050,
                'dbname'   => $params['dbname'] ?? null,
                'user'     => $params['user'] ?? null,
                'password' => $params['password'] ?? null,
            ],
            'replica' => [
                [
                    'host'     => $params['host'] ?? 'localhost',
                    'port'     => $params['port'] ?? 3050,
                    'dbname'   => $params['dbname'] ?? null,
                    'user'     => $params['user'] ?? null,
                    'password' => $params['password'] ?? null,
                ],
            ],
        ];

        // Remove null values to avoid DBAL parameter validation issues
        $connectionParams = array_filter($connectionParams, static fn ($v) => $v !== null);

        /** @var PrimaryReadReplicaConnection $conn */
        $conn = DriverManager::getConnection($connectionParams);

        if (! $conn instanceof PrimaryReadReplicaConnection) {
            self::markTestSkipped('Could not create PrimaryReadReplicaConnection for Firebird.');
        }

        $this->primaryReplicaConnection = $conn;
    }

    protected function tearDown(): void
    {
        if (isset($this->primaryReplicaConnection)) {
            $this->primaryReplicaConnection->close();
        }

        parent::tearDown();
    }

    /**
     * Read queries use the replica connection.
     */
    public function testReplicaGetsUsedForSelectQueries(): void
    {
        // Before any query, no connection is established
        self::assertFalse($this->primaryReplicaConnection->isConnectedToReplica());

        // A SELECT query should connect to the replica
        $this->primaryReplicaConnection->executeQuery('SELECT 1 FROM RDB$DATABASE');

        self::assertTrue($this->primaryReplicaConnection->isConnectedToReplica());
    }

    /**
     * Write queries (INSERT/UPDATE/DELETE/DDL) use the primary connection.
     */
    public function testPrimaryGetsUsedForWriteQueries(): void
    {
        // Ensure we start on replica
        $this->primaryReplicaConnection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        self::assertTrue($this->primaryReplicaConnection->isConnectedToReplica());

        // ensureConnectedToPrimary() switches to primary
        $this->primaryReplicaConnection->ensureConnectedToPrimary();

        self::assertFalse($this->primaryReplicaConnection->isConnectedToReplica());
    }

    /**
     * Connection switches to primary when a write operation is detected.
     */
    public function testSwitchToPrimaryOnWrite(): void
    {
        // Start on replica
        $this->primaryReplicaConnection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        self::assertTrue($this->primaryReplicaConnection->isConnectedToReplica());

        // beginTransaction() forces switch to primary
        $this->primaryReplicaConnection->beginTransaction();

        self::assertFalse($this->primaryReplicaConnection->isConnectedToReplica());

        $this->primaryReplicaConnection->rollBack();
    }
}
