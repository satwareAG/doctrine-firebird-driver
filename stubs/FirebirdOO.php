<?php

/**
 * php-firebird OO Wrapper Classes (Stubs for PHPStan)
 *
 * @see https://github.com/satwareAG/php-firebird
 */

namespace Firebird;

/**
 * Object-oriented wrapper for Firebird database connections.
 */
class Database
{
    public static function connect(
        string $database,
        ?string $username = null,
        ?string $password = null,
        ?string $charset = null,
        int $buffers = 0,
        int $dialect = 3,
        ?string $role = null
    ): self {}

    public static function pconnect(
        string $database,
        ?string $username = null,
        ?string $password = null,
        ?string $charset = null,
        int $buffers = 0,
        int $dialect = 3,
        ?string $role = null
    ): self {}

    /** @param mixed $resource */
    public static function fromResource(mixed $resource, string $database = ''): self {}

    /** @return mixed */
    public function getResource(): mixed {}
    public function isPersistent(): bool {}
    public function getDatabasePath(): string {}
    public function transaction(): TBuilder {}
    public function beginTransaction(): Transaction {}
    
    /**
     * @param array<int, mixed> $params
     * @return mixed
     */
    public function query(string $sql, array $params = [], int $bindTypes = 0): mixed {}
    
    /**
     * @param mixed $transaction
     * @param array<int, mixed> $params
     * @return mixed
     */
    public function queryWithTransaction(mixed $transaction, string $sql, array $params = []): mixed {}
    
    /** @return mixed */
    public function prepare(string $sql): mixed {}
    
    /**
     * @param mixed $statement
     * @param array<int, mixed> $params
     * @return mixed
     */
    public function execute(mixed $statement, array $params = []): mixed {}
    
    public function affectedRows(): int {}
    public function genId(string $generator, int $increment = 1): int|string {}
    public function close(): bool {}
    public function isConnected(): bool {}
    public static function getLastError(): ?string {}
    public static function getLastErrorCode(): ?int {}
}

/**
 * Object-oriented wrapper for Firebird transactions.
 */
class Transaction
{
    /** @param mixed $connection */
    public static function begin(mixed $connection): self {}
    
    /**
     * @param mixed $resource
     * @param mixed $connection
     */
    public static function fromResource(mixed $resource, mixed $connection): self {}
    
    /** @return mixed */
    public function getResource(): mixed {}
    public function getId(): ?int {}
    
    /** @return array<string, mixed>|null */
    public function getInfo(): ?array {}
    
    public function isActive(): bool {}
    public function commit(): bool {}
    public function commitRetaining(): bool {}
    public function rollback(): bool {}
    public function rollbackRetaining(): bool {}
    public function savepoint(string $name): self {}
    public function rollbackToSavepoint(string $name): self {}
    public function releaseSavepoint(string $name): self {}
    
    /** @return array<string> */
    public function getSavepoints(): array {}
    
    /**
     * @param array<int, mixed> $params
     * @return mixed
     */
    public function query(string $sql, array $params = []): mixed {}
}

/**
 * Fluent transaction parameter builder.
 */
class TBuilder
{
    public static function create(): self {}
    
    /** @param mixed $connection */
    public function connection(mixed $connection): self {}
    
    public function readCommitted(): self {}
    public function serializable(): self {}
    public function concurrency(): self {}
    public function snapshot(): self {}
    public function wait(int $timeout = 0): self {}
    public function noWait(): self {}
    public function readOnly(): self {}
    public function readWrite(): self {}
    public function start(): Transaction {}
}

// ============================================================================
// php-firebird v7.0.0 NEW OO CLASSES
// ============================================================================

/**
 * IBatch API - High-performance bulk operations (Firebird 4.0+)
 *
 * Provides 10-12x INSERT performance improvement by batching rows and
 * reducing network round-trips.
 *
 * Example:
 *   $batch = new Batch($conn, 'INSERT INTO users (name, email) VALUES (?, ?)');
 *   $batch->add('Alice', 'alice@example.com');
 *   $batch->add('Bob', 'bob@example.com');
 *   $result = $batch->execute();
 *   echo "Inserted: " . $result->getSuccessCount();
 *
 * @since php-firebird 7.0.0
 */
class Batch implements \Countable
{
    /**
     * Create a new batch operation.
     *
     * @param mixed $connection Connection resource or Database object
     * @param string $sql Parameterized SQL INSERT statement
     * @param mixed $transaction Optional transaction resource or Transaction object
     */
    public function __construct(mixed $connection, string $sql, mixed $transaction = null) {}
    
    /**
     * Add a row to the batch.
     *
     * @param mixed ...$params Parameter values matching the SQL placeholders
     * @return $this For method chaining
     */
    public function add(mixed ...$params): self {}
    
    /**
     * Create an inline BLOB and return its ID for use in add().
     *
     * @param string $data BLOB data content
     * @param int $type BLOB sub_type (default: FBIRD_TEXT)
     * @return string BLOB ID in "HHHHHHHH:LLLL" format
     */
    public function addBlob(string $data, int $type = \FBIRD_TEXT): string {}
    
    /**
     * Register an existing BLOB ID for use in the next add() call.
     *
     * @param string $blobId Pre-existing BLOB ID
     * @return bool True on success
     */
    public function registerBlob(string $blobId): bool {}
    
    /**
     * Execute all accumulated rows.
     *
     * @return BatchResult Result statistics and per-row error details
     */
    public function execute(): BatchResult {}
    
    /**
     * Cancel the batch without executing.
     *
     * @return bool True on success
     */
    public function cancel(): bool {}
    
    /**
     * Get the number of accumulated rows.
     *
     * @return int Row count
     */
    public function count(): int {}
}

/**
 * Result container for IBatch operations.
 *
 * Contains execution statistics and per-row error details.
 *
 * @since php-firebird 7.0.0
 * @implements \IteratorAggregate<int, BatchError>
 */
class BatchResult implements \Countable, \IteratorAggregate
{
    /**
     * Get the total number of rows processed (success + error).
     */
    public function getTotalProcessed(): int {}
    
    /**
     * Get the number of successfully inserted rows.
     */
    public function getSuccessCount(): int {}
    
    /**
     * Get the number of failed rows.
     */
    public function getErrorCount(): int {}
    
    /**
     * Check if any rows failed.
     */
    public function hasErrors(): bool {}
    
    /**
     * Get iterator over all errors.
     *
     * @return \Traversable<int, BatchError>
     */
    public function getErrors(): \Traversable {}
    
    /**
     * Get iterator over all errors (IteratorAggregate implementation).
     *
     * @return \Traversable<int, BatchError>
     */
    public function getIterator(): \Traversable {}
    
    /**
     * Get total processed count (Countable implementation).
     */
    public function count(): int {}
}

/**
 * Per-row error information from batch operations.
 *
 * Contains the row number, error code, message, and SQLSTATE.
 *
 * @since php-firebird 7.0.0
 */
class BatchError
{
    /**
     * Get the 0-based row number that failed.
     */
    public function getRowNumber(): int {}
    
    /**
     * Get the Firebird error code.
     */
    public function getErrorCode(): int {}
    
    /**
     * Get the error message.
     */
    public function getErrorMessage(): string {}
    
    /**
     * Get the 5-character SQLSTATE code.
     */
    public function getSqlState(): string {}
}

/**
 * Type-safe BLOB identifier value object.
 *
 * BLOBs in Firebird are identified by a quad (two 32-bit integers).
 * This class provides a type-safe wrapper for BLOB IDs in the
 * "HHHHHHHH:LLLL" format (13 characters, colon-separated hex).
 *
 * @since php-firebird 7.0.0
 */
class BlobId implements \Stringable
{
    /**
     * Create from string representation.
     *
     * @param string $id BLOB ID in "HHHHHHHH:LLLL" format
     */
    public function __construct(string $id) {}
    
    /**
     * Create from string representation (static factory).
     *
     * @param string $id BLOB ID in "HHHHHHHH:LLLL" format
     */
    public static function fromString(string $id): self {}
    
    /**
     * Get the high 32-bit part.
     */
    public function getHighPart(): int {}
    
    /**
     * Get the low 32-bit part.
     */
    public function getLowPart(): int {}
    
    /**
     * Get the string representation.
     */
    public function __toString(): string {}
    
    /**
     * Compare with another BlobId.
     */
    public function equals(BlobId $other): bool {}
}

/**
 * Database information structure.
 *
 * Provides read-only access to database metadata and statistics
 * retrieved via fbird_connection_info() or Database::getInfo().
 *
 * @since php-firebird 7.0.0
 */
class DbInfo
{
    /** @param mixed $connection */
    public static function fromConnection(mixed $connection): self {}

    /**
     * Get the Firebird server version string.
     */
    public function getVersion(): string {}
    
    /**
     * Get the On-Disk Structure (ODS) major version.
     */
    public function getOdsVersion(): int {}
    
    /**
     * Get the ODS minor version.
     */
    public function getOdsMinorVersion(): int {}
    
    /**
     * Get the database page size in bytes.
     */
    public function getPageSize(): int {}
    
    /**
     * Get the number of cache buffers.
     */
    public function getNumBuffers(): int {}
    
    /**
     * Get the sweep interval.
     */
    public function getSweepInterval(): int {}
    
    /**
     * Get the database SQL dialect (1, 2, or 3).
     */
    public function getDbSqlDialect(): int {}
    
    /**
     * Convert to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {}
}

/**
 * Exception thrown by Firebird extension.
 *
 * @since php-firebird 7.0.0
 */
class Exception extends \Exception
{
    /** @return string|null */
    public function getSqlState(): ?string {}
}
