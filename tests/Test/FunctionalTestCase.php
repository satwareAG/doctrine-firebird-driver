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
use Throwable;

use function array_merge;
use function assert;
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

        // NOTE: We intentionally do NOT call dropTableForce() here.
        // That C-extension function can invalidate the OO API connection
        // pointers on the shared connection, causing "OO API connection/
        // transaction pointers are NULL" on subsequent queries.
        // Instead we rely on the standard schema manager drop with retry.

        // Try up to 3 times with delay to handle "object is in use" errors
        $success = false;
        for ($i = 0; $i < 3; $i++) {
            try {
                // Try rollback first to clear pending failed transaction
                try {
                    $fbirdConnection?->rollBack();
                } catch (Throwable) {
                    try {
                        @$fbirdConnection->commit();
                    } catch (Throwable) {
                        // Ignore commit errors during cleanup
                    }
                }

                // Wait for server to release locks on retry
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
                // Table doesn't exist, which is fine
                $success = true;
                break;
            } catch (Throwable $e) {
                if (! str_contains($e->getMessage(), 'in use') && ! str_contains($e->getMessage(), 'deadlock')) {
                    // Allow "does not exist" errors when existence was unknown
                    if ($existenceUnknown && str_contains($e->getMessage(), 'does not exist')) {
                        $success = true;
                        break;
                    }

                    throw $e;
                }
                // Continue loop if lock/deadlock error
            }
        }

        if (! $success && isset($e)) {
            // "in use" / deadlock errors are non-fatal in tearDown context - the table will be
            // dropped in the next test's setUp() after disconnect() @after has run gc_collect_cycles()
            // and released all cursor locks held by this connection's PHP objects.
            if (str_contains($e->getMessage(), 'in use') || str_contains($e->getMessage(), 'deadlock')) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Drops the sequence with the specified name, if it exists.
     *
     * Firebird does not support DROP SEQUENCE IF EXISTS, so we attempt the drop
     * and suppress "does not exist"/"is not defined" errors. Uses the schema
     * manager for proper identifier quoting. Retries on "in use"/"deadlock"
     * errors (matching dropTableIfExists behavior).
     *
     * @throws Exception If a non-"does not exist"/"in use"/"deadlock" error occurs after retries.
     */
    public function dropSequenceIfExists(string $name): void
    {
        $fbirdConnection = $this->getFirebirdConnection();
        if ($fbirdConnection !== null && ! $fbirdConnection->isConnectionValid()) {
            return;
        }

        $schemaManager = $this->connection->createSchemaManager();

        for ($i = 0; $i < 3; $i++) {
            try {
                try {
                    $fbirdConnection?->rollBack();
                } catch (Throwable) {
                }

                if ($i > 0) {
                    usleep(50000); // 50ms wait
                }

                $schemaManager->dropSequence($name);
                $fbirdConnection?->commit();

                return;
            } catch (Throwable $e) {
                try {
                    $fbirdConnection?->rollBack();
                } catch (Throwable) {
                }

                $msg = $e->getMessage();

                // Suppress "does not exist" / "is not defined" errors - sequence already gone
                if (
                    str_contains($msg, 'does not exist') || str_contains($msg, 'DOES NOT EXIST')
                    || str_contains($msg, 'is not defined') || str_contains($msg, 'IS NOT DEFINED')
                ) {
                    return;
                }

                // Retry on lock/deadlock, otherwise re-throw
                if (! str_contains($msg, 'in use') && ! str_contains($msg, 'deadlock')) {
                    throw $e;
                }
            }
        }

        // Lock/deadlock errors after all retries are non-fatal in cleanup context
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

        // Firebird requires DDL changes to be committed before subsequent DDL
        // can see them. Without this commit, CREATE TABLE may fail with
        // "Table already exists" because the DROP hasn't been finalized yet.
        $fbirdConn = $this->getFirebirdConnection();
        if ($fbirdConn !== null && $fbirdConn->isConnectionValid()) {
            try {
                $fbirdConn->commit();
            } catch (Throwable) {
                // Ignore commit errors - auto-commit may have already committed
            }
        }

        // Retry logic: dropTableIfExists() may silently fail when the table is
        // locked by a lingering connection (it swallows "in use" errors after
        // retries). If createTable() hits "already exists", roll back, run GC,
        // and retry drop+create after a delay.
        try {
            $schemaManager->createTable($table);
        } catch (Throwable $e) {
            if (
                ! str_contains($e->getMessage(), 'already exists')
                && ! str_contains($e->getMessage(), 'ALREADY EXISTS')
            ) {
                throw $e;
            }

            // Release Firebird metadata locks by committing/rolling back and
            // running GC to free any PHP objects holding cursor references.
            // Do NOT call $this->connection->close() here — it invalidates
            // the shared connection's native Firebird pointers and causes
            // "OO API connection/transaction pointers are NULL" on the next
            // query through DBAL middleware.
            if ($fbirdConn !== null && $fbirdConn->isConnectionValid()) {
                try {
                    $fbirdConn->rollBack();
                } catch (Throwable) {
                }
            }

            gc_collect_cycles();
            usleep(500_000); // 500ms — let Firebird server release locks

            $this->dropTableIfExists($tableName);

            if ($fbirdConn !== null && $fbirdConn->isConnectionValid()) {
                try {
                    $fbirdConn->commit();
                } catch (Throwable) {
                }
            }

            $schemaManager->createTable($table);
        }

        $this->createdTables[] = $tableName;
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
        // Traverse DBAL middleware layers to find the underlying FirebirdConnection object.
        // In DBAL 3.x, we must walk the getWrappedConnection() chain to reach the
        // driver-level object that provides isConnectionValid(), dropTableForce(), etc.
        if (! isset($this->connection)) {
            return null;
        }

        $connection = $this->connection;

        // Try to get the driver connection directly if it's already unwrapped
        try {
            $driverConn = $connection->getNativeConnection();
            if ($driverConn instanceof FirebirdConnection) {
                return $driverConn;
            }
        } catch (Throwable) {
            // getNativeConnection might fail or return a resource
        }

        while (method_exists($connection, 'getWrappedConnection')) {
            // @phpstan-ignore-next-line (getWrappedConnection() is deprecated but required to traverse DBAL 3.x middleware to reach FirebirdConnection)
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

    #[Before]
    final protected function connect(): void
    {
        $needNewConnection = ! self::$sharedConnection instanceof Connection;

        // Check if existing shared connection's underlying Firebird connection is still valid
        if (! $needNewConnection) {
            $fbirdConn = null;
            $conn      = self::$sharedConnection;
            while (method_exists($conn, 'getWrappedConnection')) {
                // @phpstan-ignore-next-line (getWrappedConnection() is deprecated but required to traverse DBAL 3.x middleware to reach FirebirdConnection)
                $conn = $conn->getWrappedConnection();
                if ($conn instanceof FirebirdConnection) {
                    $fbirdConn = $conn;
                    break;
                }
            }

            if ($fbirdConn !== null && ! $fbirdConn->isConnectionValid()) {
                // Connection resource is invalid - need to reconnect.
                // Use graceful null assignment instead of close() to avoid
                // triggering __destruct() which calls fbird_close() on the
                // shared native resource, invalidating it for other objects.
                self::$sharedConnection = null;
                TestUtil::resetSharedConnection();
                $needNewConnection = true;
            }
        }

        // Verify the connection can actually execute queries. The Firebird
        // extension's OO API pointers may become NULL after rollBack() sequences
        // even though isConnectionValid() still returns true.
        if (! $needNewConnection && self::$sharedConnection instanceof Connection) {
            try {
                self::$sharedConnection->executeQuery('SELECT 1 FROM RDB$DATABASE');
            } catch (Throwable) {
                try {
                    self::$sharedConnection->close();
                } catch (Throwable) {
                }

                self::$sharedConnection = null;
                TestUtil::resetSharedConnection();
                $needNewConnection = true;
            }
        }

        if ($needNewConnection) {
            self::$sharedConnection = TestUtil::getConnection();
        }

        assert(self::$sharedConnection instanceof Connection);
        $this->connection = self::$sharedConnection;
    }

    #[After]
    final protected function disconnect(): void
    {
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

            // Free PHP cursor/result objects before attempting DDL.
            // Firebird holds metadata locks until all PHP objects referencing
            // the table's result sets are destroyed. Without this, DROP TABLE
            // fails with "object is in use" because cursor references survive.
            gc_collect_cycles();

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
