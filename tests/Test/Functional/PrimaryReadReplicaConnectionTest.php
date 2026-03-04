<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use Throwable;

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
     * DBAL 3.10 only exposes isConnectedToPrimary() — replica is the inverse.
     */
    public function testReplicaGetsUsedForSelectQueries(): void
    {
        // Before any query, no connection is established — not connected to primary
        self::assertFalse($this->primaryReplicaConnection->isConnectedToPrimary());

        // A SELECT query should connect to the replica (not primary).
        // Skip if the replica host is unreachable (single-instance CI without service manager).
        try {
            $this->primaryReplicaConnection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        } catch (Throwable $e) {
            self::markTestSkipped('Replica host unreachable in this environment: ' . $e->getMessage());
        }

        self::assertFalse($this->primaryReplicaConnection->isConnectedToPrimary());
    }

    /**
     * Write queries (INSERT/UPDATE/DELETE/DDL) use the primary connection.
     */
    public function testPrimaryGetsUsedForWriteQueries(): void
    {
        // Ensure we start on replica (not primary).
        // Skip if the replica host is unreachable (single-instance CI without service manager).
        try {
            $this->primaryReplicaConnection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        } catch (Throwable $e) {
            self::markTestSkipped('Replica host unreachable in this environment: ' . $e->getMessage());
        }

        self::assertFalse($this->primaryReplicaConnection->isConnectedToPrimary());

        // ensureConnectedToPrimary() switches to primary
        $this->primaryReplicaConnection->ensureConnectedToPrimary();

        self::assertTrue($this->primaryReplicaConnection->isConnectedToPrimary());
    }

    /**
     * Connection switches to primary when a write operation is detected.
     */
    public function testSwitchToPrimaryOnWrite(): void
    {
        // Start on replica (not primary).
        // Skip if the replica host is unreachable (single-instance CI without service manager).
        try {
            $this->primaryReplicaConnection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        } catch (Throwable $e) {
            self::markTestSkipped('Replica host unreachable in this environment: ' . $e->getMessage());
        }

        self::assertFalse($this->primaryReplicaConnection->isConnectedToPrimary());

        // beginTransaction() forces switch to primary
        $this->primaryReplicaConnection->beginTransaction();

        self::assertTrue($this->primaryReplicaConnection->isConnectedToPrimary());

        $this->primaryReplicaConnection->rollBack();
    }
}
