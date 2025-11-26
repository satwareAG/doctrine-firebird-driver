<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection as FirebirdConnection;
use Throwable;

use function array_merge;
use function gc_collect_cycles;
use function str_contains;
use function usleep;

abstract class FunctionalTestCase extends TestCase
{
    protected Connection $connection;

    /** @var list<string> */
    protected array $createdTables = [];

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
            $this->getFirebirdConnection()?->commit();
        } catch (DatabaseObjectNotFoundException) {
        } catch (Throwable $e) {
            // If table is in use, try to force rollback/commit to release locks and retry
            if (! str_contains($e->getMessage(), 'in use')) {
                throw $e;
            }

            // Try up to 3 times with delay
            $success = false;
            for ($i = 0; $i < 3; $i++) {
                try {
                    // Try rollback first to clear pending failed transaction
                    try {
                        $this->getFirebirdConnection()?->rollBack();
                    } catch (Throwable) {
                        @$this->getFirebirdConnection()?->commit();
                    }

                    // Wait for server to release locks
                    if ($i > 0) {
                        usleep(50000); // 50ms wait
                    }

                    $schemaManager->dropTable($name);
                    $this->getFirebirdConnection()?->commit();
                    $success = true;
                    break;
                } catch (DatabaseObjectNotFoundException) {
                    $success = true;
                    break;
                } catch (Throwable $e2) {
                    if (! str_contains($e2->getMessage(), 'in use') && ! str_contains($e2->getMessage(), 'deadlock')) {
                        throw $e2;
                    }
                    // Continue loop if lock error
                }
            }

            if (! $success) {
                throw $e;
            }
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
        $this->createdTables[] = $tableName;
        // Explicit commit removed to avoid hangs with Firebird auto-commit behavior
        // $this->getFirebirdConnection()?->commit();
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

    /** @before */
    final protected function connect(): void
    {
        if (! self::$sharedConnection instanceof Connection) {
            self::$sharedConnection = TestUtil::getConnection();
        }

        $this->connection = self::$sharedConnection;
    }

    /** @after */
    final protected function disconnect(): void
    {
        // Attempt to free any lingering statement resources via GC
        gc_collect_cycles();

        while ($this->connection->isTransactionActive()) {
            try {
                $this->connection->rollBack();
            } catch (Throwable) {
                // If rollback fails, we can't do much about it.
                // Breaking the loop prevents infinite loop if nesting level doesn't decrease.
                break;
            }
        }

        // Ensure any implicit driver-level lock is released (e.g. from auto-commit commit_ret)
        // Use rollBack instead of commit to ensure locks are released even if commit fails
        try {
            @$this->getFirebirdConnection()?->rollBack();
        } catch (Throwable) {
            // Ignore rollback errors during cleanup
        }

        foreach ($this->createdTables as $tableName) {
            try {
                $this->dropTableIfExists($tableName);
            } catch (Throwable) {
                // Ignore errors during cleanup
            }
        }

        $this->createdTables = [];

        if ($this->isConnectionReusable) {
            return;
        }

        // Close the current connection (which might be different from shared if test replaced it)
        if (isset($this->connection)) {
            $this->connection->close();
        }

        // Also close and reset the shared connection reference
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
