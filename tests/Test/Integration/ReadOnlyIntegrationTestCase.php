<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\Medium;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use Throwable;

#[Medium]
abstract class ReadOnlyIntegrationTestCase extends AbstractIntegrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = TestUtil::getConnection();
        
        // We pass an empty array for configuration as it is currently ignored by installFirebirdDatabase
        // but required by the signature.
        static::installFirebirdDatabase($connection, []);
        
        $connection->close();
    }

    public static function tearDownAfterClass(): void
    {
        $connection = TestUtil::getConnection();
        
        // Drop tables to clean up
        $schemaManager = $connection->createSchemaManager();
        $schema = new Schema();
        // We need to define tables to drop them, or just drop all tables?
        // installFirebirdDatabase defines the schema. We can duplicate the drop logic or extract it.
        // For now, let's just rely on the next test cleaning up or the database being dropped by TestUtil if re-initialized.
        // But TestUtil only re-initializes if not initialized.
        
        // Actually, installFirebirdDatabase drops tables before creating them.
        // So we don't strictly need to drop them here, but it's cleaner.
        // However, duplicating the schema definition here is annoying.
        // Let's skip explicit table drop in tearDownAfterClass for now to avoid code duplication.
        // The next test run (if it uses installFirebirdDatabase) will drop them.
        
        $connection->close();
        
        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        // Ensure connection is established (FunctionalTestCase @before connect() is called before this)
        
        // Initialize EntityManager (without reinstalling database)
        $this->setUpEntityManager();
        
        // Verify Firebird transaction is valid before starting DBAL transaction
        // This handles edge cases where the transaction handle becomes invalid between tests
        // (e.g., due to connection state issues or php-firebird Exception Mode side effects)
        $fbirdConnection = $this->getFirebirdConnection();
        if ($fbirdConnection !== null && ! $fbirdConnection->isTransactionValid()) {
            // Execute a simple query to force the driver to initialize a valid transaction
            // This is a workaround for Firebird's requirement that queries must run within transactions
            try {
                $this->connection->executeQuery('SELECT 1 FROM RDB$DATABASE');
            } catch (Throwable) {
                // If query fails, connection is likely invalid - let beginTransaction fail naturally
            }
        }
        
        // Start transaction to isolate test changes
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        // Rollback transaction to revert any changes
        if ($this->connection->isTransactionActive()) {
            try {
                $this->connection->rollBack();
            } catch (Throwable) {
                // Ignore rollback errors
            }
        }
        
        parent::tearDown();
    }
}