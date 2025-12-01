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
use function method_exists;
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
        // Early return if connection is not available or closed
        $fbirdConnection = $this->getFirebirdConnection();
        if ($fbirdConnection !== null && ! $fbirdConnection->isConnectionValid()) {
            return;
        }

        $schemaManager = $this->connection->createSchemaManager();

        // Check if table exists before attempting to drop to avoid Firebird warnings
        // Firebird doesn't support DROP TABLE IF EXISTS, so we must check first
        $existenceUnknown = false;
        try {
            if (! $schemaManager->tablesExist([$name])) {
                return;
            }
        } catch (Throwable) {
            // If we can't check existence, proceed with drop attempt but suppress warnings
            $existenceUnknown = true;
        }

        try {
            if ($existenceUnknown) {
                // Suppress warnings when we couldn't verify existence - table may not exist
                @$schemaManager->dropTable($name);
            } else {
                $schemaManager->dropTable($name);
            }

            $fbirdConnection?->commit();
        } catch (DatabaseObjectNotFoundException) {
            // Table doesn't exist, which is fine for dropTableIfExists
        } catch (Throwable $e) {
            // Ignore "does not exist" errors when existence was unknown
            if ($existenceUnknown && str_contains($e->getMessage(), 'does not exist')) {
                return;
            }

            // If table is in use, try to force rollback/commit to release locks and retry
            if (! str_contains($e->getMessage(), 'in use')) {
                throw $e;
            }

            // Optimization: Try to force drop using driver native function to kill blocking attachments
            if ($fbirdConnection !== null) {
                try {
                    // Try to use fbird_drop_table_force if available
                    // We remove quotes if present because the API might expect raw name,
                    // or simply pass as is. Let's pass as is first.
                    // Actually, checking if function exists or method works.
                    // Connection::dropTableForce uses fbird_drop_table_force.

                    // Unquote name for specialized driver call if it starts/ends with quotes
                    // The driver function likely expects the name as used in metadata usually (e.g. UPPERCASE if unquoted)
                    // If $name is quoted "TABLE", we might need to be careful.
                    // But lets try passing it directly.

                    if ($fbirdConnection->dropTableForce($name)) {
                        $fbirdConnection->commit();

                        return;
                    }
                } catch (Throwable) {
                    // Ignore force drop errors and fall back to retry loop
                }
            }

            // Try up to 3 times with delay
            $success = false;
            for ($i = 0; $i < 3; $i++) {
                try {
                    // Try rollback first to clear pending failed transaction
                    try {
                        $fbirdConnection?->rollBack();
                    } catch (Throwable) {
                        try {
                            @$fbirdConnection?->commit();
                        } catch (Throwable) {
                            // Ignore commit errors during cleanup
                        }
                    }

                    // Wait for server to release locks
                    if ($i > 0) {
                        usleep(50000); // 50ms wait
                    }

                    if ($existenceUnknown) {
                        @$schemaManager->dropTable($name);
                    } else {
                        $schemaManager->dropTable($name);
                    }

                    $fbirdConnection?->commit();
                    $success = true;
                    break;
                } catch (DatabaseObjectNotFoundException) {
                    $success = true;
                    break;
                } catch (Throwable $e2) {
                    if (! str_contains($e2->getMessage(), 'in use') && ! str_contains($e2->getMessage(), 'deadlock')) {
                        // Allow "does not exist" errors when existence was unknown
                        if ($existenceUnknown && str_contains($e2->getMessage(), 'does not exist')) {
                            $success = true;
                            break;
                        }

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
        $connection = $this->connection;
        while (method_exists($connection, 'getWrappedConnection')) {
            $connection = $connection->getWrappedConnection();
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

        // Get Firebird connection early to check validity
        $fbirdConnection = $this->getFirebirdConnection();
        $connectionValid = $fbirdConnection === null || $fbirdConnection->isConnectionValid();

        // Only attempt rollback if connection is still valid
        if ($connectionValid) {
            try {
                // Check if transaction allows rollback
                while ($this->connection->isTransactionActive()) {
                    try {
                        $this->connection->rollBack();
                    } catch (Throwable) {
                        // If rollback fails, we can't do much about it.
                        // Breaking the loop prevents infinite loop if nesting level doesn't decrease.
                        break;
                    }
                }
            } catch (Throwable) {
                // Ignore transaction check errors (e.g. if connection closed by test)
            }

            // Ensure any implicit driver-level lock is released (e.g. from auto-commit commit_ret)
            // Use rollBack instead of commit to ensure locks are released even if commit fails
            try {
                @$fbirdConnection?->rollBack();
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
        }

        $this->createdTables = [];

        if ($this->isConnectionReusable) {
            return;
        }

        // Close the current connection (which might be different from shared if test replaced it)
        if (isset($this->connection)) {
            try {
                $this->connection->close();
            } catch (Throwable) {
                // Ignore close errors - connection might already be closed
            }
        }

        // Also close and reset the shared connection reference
        if (self::$sharedConnection instanceof Connection) {
            try {
                self::$sharedConnection->close();
            } catch (Throwable) {
                // Ignore close errors
            }

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
