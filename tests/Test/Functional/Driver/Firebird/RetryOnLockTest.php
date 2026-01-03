<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

class RetryOnLockTest extends FunctionalTestCase
{
    private string $tableName = 'retry_lock_test';

    public function testSelectThenDropFailsWithoutRetry(): void
    {
        self::markTestSkipped('ATTR_DOCTRINE_RETRY_ON_LOCK feature is not yet implemented - only constant defined');

        // 1. Disable Retry option (via new connection params or reflection setAttribute if possible)
        // Since we are inside FunctionalTestCase, creating new connection is best.
        $params                                                               = $this->connection->getParams();
        $params['driverOptions'][FirebirdDriver::ATTR_DOCTRINE_RETRY_ON_LOCK] = false;

        $conn = $this->reConnect($params);

        // 2. Select to hold lock (commit_ret)
        // We fetch one row. The statement uses commit_ret, retaining transaction locks.
        // We intentionally free the statement to verify that the Transaction lock causes the issue,
        // not the active statement handle (which covers SchemaManager usage patterns).
        $stmt   = $conn->prepare('SELECT * FROM ' . $this->tableName);
        $result = $stmt->execute();
        $row    = $result->fetchNumeric();

        // Free stmt/result to ensure only transaction lock remains
        $result->free();
        unset($stmt); // Triggers destructor -> fbird_free_query

        // 3. Try Drop - Should Fail with "object in use" because active transaction holds lock
        try {
            $conn->executeStatement('DROP TABLE ' . $this->tableName);
            self::fail('Expected exception due to lock conflict');
        } catch (Throwable $e) {
            self::assertStringContainsString('is in use', $e->getMessage());
        }

        $conn->close();
    }

    public function testSelectThenDropSucceedsWithRetry(): void
    {
        self::markTestSkipped('ATTR_DOCTRINE_RETRY_ON_LOCK feature is not yet implemented - only constant defined');

        // 1. Enable Retry option
        $params                                                               = $this->connection->getParams();
        $params['driverOptions'][FirebirdDriver::ATTR_DOCTRINE_RETRY_ON_LOCK] = true;

        $conn = $this->reConnect($params);

        // 2. Select to hold lock
        $stmt   = $conn->prepare('SELECT * FROM ' . $this->tableName);
        $result = $stmt->execute();
        $row    = $result->fetchNumeric();

        // Free stmt/result. Lock persists via transaction (commit_ret).
        $result->free();
        unset($stmt);

        // 3. Try Drop - Should Succeed via Retry logic calling forceCommit()
        try {
            // Debug: If manual commit works here, then forceCommit logic works in principle
            // $conn->commit();

            $conn->executeStatement('DROP TABLE ' . $this->tableName);
            self::assertTrue(true, 'Drop succeeded with retry');
        } catch (Throwable $e) {
            self::fail('Drop failed despite retry: ' . $e->getMessage() . ' Code: ' . $e->getCode());
        }

        $conn->close();
    }

    protected function setUp(): void
    {
        // Skip entire test class - ATTR_DOCTRINE_RETRY_ON_LOCK feature is not yet implemented
        self::markTestSkipped('ATTR_DOCTRINE_RETRY_ON_LOCK feature is not yet implemented - only constant defined');
    }

    protected function setUpTestTable(): void
    {
        $this->markConnectionNotReusable();

        // Ensure clean state
        $schemaManager = $this->connection->createSchemaManager();
        try {
            $schemaManager->dropTable($this->tableName);
        } catch (Throwable) {
            // Ignore
        }

        $table = new Table($this->tableName);
        $table->addColumn('id', Types::INTEGER);
        $schemaManager->createTable($table);
        $this->connection->insert($this->tableName, ['id' => 1]);
    }

    protected function tearDown(): void
    {
        // Cleanup
        $schemaManager = $this->connection->createSchemaManager();
        try {
            $schemaManager->dropTable($this->tableName);
        } catch (Throwable) {
            // Ignore
        }

        parent::tearDown();
    }
}
