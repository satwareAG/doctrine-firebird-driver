<?php

/**
 * PHPStan stubs for php-firebird userland classes (Firebird namespace).
 *
 * These classes are provided by the php-firebird extension as userland PHP classes
 * distributed with the extension itself (src/Firebird/*.php). The C-level classes
 * (Connection, Transaction, Statement, ResultSet, Blob, Service) are in
 * firebird-classes.php; this file covers the remaining userland helpers.
 *
 * Updated for v10.3.6:
 * - TBuilder: expanded fluent API (readOnly, readWrite, isolationSnapshot, etc.)
 * - TransactionManager: added fromResource() factory, isActive(), getConnection()
 * - Database: added getResource(), query(), execute(), blob helpers, getInfo()
 * - Batch: added fromQuery() factory
 * - BatchResult: implements Countable, IteratorAggregate; added successRate()
 * - BatchError: new immutable value object for per-row batch errors
 * - BlobId: new type-safe BLOB identifier (ISC_QUAD) value object
 * - EventPollerInterface: new interface for event polling strategies
 * - EventPoller: new factory for event pollers
 * - PcntlEventPoller: PCNTL-based event poller
 * - FiberEventPoller: Fiber/AMPHP-based event poller
 * - ProcessEventPoller: Process-based event poller
 *
 * @see https://github.com/satwareAG/php-firebird
 * @version 10.3.6
 */

declare(strict_types=1);

namespace Firebird;

// ============================================================================
// TRANSACTION BUILDER
// ============================================================================

/**
 * Fluent builder for Firebird Transaction Parameter Block (TPB) options.
 *
 * Provides a type-safe, discoverable API for configuring transactions
 * instead of using raw bitmask constants.
 *
 * @since 7.0.0
 * @since 10.0.0 Expanded with full fluent API
 */
final class TBuilder
{
    private function __construct() {}

    /** Create a new TBuilder instance. */
    public static function create(): self {}

    /**
     * Set the connection for this transaction builder.
     * @param mixed $connection Database connection resource or Database instance
     */
    public function connection(mixed $connection): self {}

    /** Check if a connection has been set. */
    public function hasConnection(): bool {}

    // Access mode
    /** Set transaction to read-only mode. */
    public function readOnly(bool $enable = true): self {}
    /** Set transaction to read-write mode. */
    public function readWrite(): self {}

    // Isolation levels
    /** Set READ COMMITTED isolation. */
    public function isolationReadCommitted(): self {}
    /** Set READ COMMITTED with RECORD VERSION. */
    public function isolationReadCommittedRecordVersion(): self {}
    /** Set READ COMMITTED with NO RECORD VERSION. */
    public function isolationReadCommittedNoRecordVersion(): self {}
    /** Set SNAPSHOT (CONCURRENCY) isolation. */
    public function isolationSnapshot(): self {}
    /** Set SNAPSHOT TABLE STABILITY (CONSISTENCY) isolation. */
    public function isolationSnapshotTableStability(): self {}
    /** Set READ CONSISTENCY isolation (Firebird 4.0+). */
    public function isolationReadConsistency(): self {}

    // Lock resolution
    /** Set WAIT lock resolution with optional timeout. */
    public function wait(?int $timeoutSeconds = null): self {}
    /** Set NO WAIT lock resolution. */
    public function noWait(): self {}

    // Table reservations
    /**
     * Reserve a table with specific lock and access mode.
     * @param string $tableName Table name
     * @param int $lockType Lock type (FBIRD_LOCK_SHARED, FBIRD_LOCK_PROTECTED, FBIRD_LOCK_EXCLUSIVE)
     * @param int $accessType Access type (FBIRD_LOCK_READ, FBIRD_LOCK_WRITE)
     */
    public function reserveTable(string $tableName, int $lockType, int $accessType): self {}

    // Build & start
    /**
     * Build the transaction options array compatible with fbird_trans()/fbird_trans_start().
     * @return array<string, mixed>
     */
    public function build(): array {}

    /** Start the transaction. Requires connection() to be called first. */
    public function start(): TransactionManager {}

    // Convenience aliases
    /** @deprecated Use isolation() or named method. Legacy v7 compat. */
    public function isolation(int $level): self {}
    /** @deprecated Use isolationReadCommittedRecordVersion(). */
    public function recordVersion(int $version = \FBIRD_REC_VERSION): self {}
    /** @deprecated Use wait() or noWait(). */
    public function lockResolution(int $resolution): self {}
    /** @deprecated Use readOnly() or readWrite(). */
    public function accessMode(int $mode): self {}
    /** Alias for isolationReadCommitted(). */
    public function readCommitted(): self {}
    /** Alias for isolationSnapshot(). */
    public function snapshot(): self {}
}

// ============================================================================
// TRANSACTION MANAGER
// ============================================================================

/**
 * Represents an active Firebird transaction (userland wrapper).
 *
 * Renamed from Transaction to TransactionManager in v8.0.0 to avoid
 * conflict with the new C-level Firebird\Transaction class.
 *
 * @since 7.0.0
 * @since 8.0.0 Renamed from Transaction
 * @since 10.0.0 Added fromResource() factory
 */
class TransactionManager
{
    private function __construct() {}

    /**
     * Create a TransactionManager from an existing transaction resource.
     * @param mixed $resource Transaction resource
     * @param mixed $connection Connection resource (optional)
     */
    public static function fromResource(mixed $resource, mixed $connection = null): static {}

    /** Get the underlying transaction resource. */
    public function getResource(): mixed {}

    /** Get the connection resource. */
    public function getConnection(): mixed {}

    /** Check if the transaction is still active. */
    public function isActive(): bool {}

    /** Check if the transaction resource is valid. */
    public function isTransactionValid(): bool {}

    /** Commit the transaction. */
    public function commit(): bool {}

    /** Rollback the transaction. */
    public function rollback(): bool {}

    /** Commit and retain the transaction context. */
    public function commitRetaining(): bool {}

    /** Rollback and retain the transaction context. */
    public function rollbackRetaining(): bool {}
}

// ============================================================================
// DATABASE
// ============================================================================

/**
 * OO wrapper for a Firebird database connection.
 *
 * @since 7.0.0
 * @since 10.0.0 Expanded with query/execute/blob helpers
 */
class Database
{
    private function __construct() {}

    /** Create a Database wrapper from an existing connection resource. */
    public static function fromResource(mixed $resource): static {}

    /** Get the underlying connection resource. */
    public function getResource(): mixed {}

    /** Create a new TBuilder for this connection. */
    public function newTransaction(): TBuilder {}

    /**
     * Execute a query and return results.
     * @param string $sql SQL statement
     * @param array<int, mixed> $params Optional bind parameters
     * @return mixed Result resource or affected row count
     */
    public function query(string $sql, array $params = []): mixed {}

    /**
     * Execute a DML statement and return affected row count.
     * @param string $sql SQL statement
     * @param array<int, mixed> $params Optional bind parameters
     */
    public function execute(string $sql, array $params = []): int {}

    /**
     * Create a BLOB and return its ID.
     * @param string $data BLOB content
     */
    public function createBlob(string $data): string {}

    /**
     * Read a BLOB by its ID.
     * @param string $blobId BLOB identifier
     */
    public function readBlob(string $blobId): string {}

    /** Get database information and statistics. */
    public function getInfo(): DbInfo {}

    /** Close the connection. */
    public function close(): bool {}
}

// ============================================================================
// BATCH API (Firebird 4.0+)
// ============================================================================

/**
 * Batch INSERT operation - IBatch API (Firebird 4.0+).
 *
 * @since 7.0.0
 * @since 10.0.0 Added fromQuery() factory
 */
class Batch
{
    private function __construct() {}

    /**
     * Create a Batch from a prepared query resource.
     * @param mixed $query Prepared statement resource
     */
    public static function fromQuery(mixed $query): static {}

    /**
     * Add a row of parameters to the batch.
     * @param array<int|string, mixed> $row
     */
    public function add(array $row): bool {}

    /**
     * Add a row with BLOB data.
     * @param array<int|string, mixed> $row Row data
     * @param array<int, string> $blobColumns Column indices containing BLOB data
     */
    public function addWithBlobs(array $row, array $blobColumns = []): bool {}

    /** Execute the batch and return results. */
    public function execute(): BatchResult {}

    /** Cancel the batch without executing. */
    public function cancel(): bool {}

    /** Get the number of rows added. */
    public function count(): int {}
}

/**
 * Immutable value object for per-row batch execution errors.
 *
 * @since 10.0.0
 */
final class BatchError
{
    /** Get the 0-based row index that caused the error. */
    public function getRow(): int {}

    /** Get the Firebird error code. */
    public function getCode(): int {}

    /** Get the error message. */
    public function getMessage(): string {}

    /** Get the 5-character SQLSTATE code. */
    public function getSqlState(): string {}

    /** Check if this is a constraint violation. */
    public function isConstraintViolation(): bool {}

    /** Check if this is a duplicate key error. */
    public function isDuplicateKey(): bool {}
}

/**
 * Result of a batch INSERT operation.
 *
 * @since 7.0.0
 * @since 10.0.0 Implements Countable, IteratorAggregate; added successRate()
 * @implements \IteratorAggregate<int, BatchError>
 */
class BatchResult implements \Countable, \IteratorAggregate
{
    private function __construct() {}

    /** Get the total number of rows processed. */
    public function count(): int {}

    /** Get the number of successfully inserted rows. */
    public function getSuccessCount(): int {}

    /** Get the number of failed rows. */
    public function getErrorCount(): int {}

    /**
     * Get all per-row errors.
     * @return array<int, BatchError>
     */
    public function getErrors(): array {}

    /** Get the success rate as a float (0.0 to 1.0). */
    public function successRate(): float {}

    /** Check if all rows were inserted successfully. */
    public function isComplete(): bool {}

    /** @return \ArrayIterator<int, BatchError> */
    public function getIterator(): \ArrayIterator {}
}

// ============================================================================
// BLOB ID
// ============================================================================

/**
 * Type-safe BLOB identifier (ISC_QUAD) value object.
 *
 * Supports colon format ("80000001:1") and hex format ("0000000180000001").
 *
 * @since 10.0.0
 */
final class BlobId implements \Stringable
{
    /**
     * Create from a colon-separated BLOB ID string.
     * @param string $id BLOB ID in "high:low" format
     */
    public static function fromString(string $id): self {}

    /**
     * Create from a hex BLOB ID string.
     * @param string $hex 16-character hex string
     */
    public static function fromHex(string $hex): self {}

    /**
     * Create from high and low 32-bit parts.
     */
    public static function fromParts(int $high, int $low): self {}

    /** Get the colon-format string representation. */
    public function toString(): string {}

    /** Get the hex-format string representation. */
    public function toHex(): string {}

    /** Get the high 32-bit part. */
    public function getHigh(): int {}

    /** Get the low 32-bit part. */
    public function getLow(): int {}

    /** Check if this BLOB ID is null (0:0). */
    public function isNull(): bool {}

    public function __toString(): string {}
}

// ============================================================================
// DATABASE INFO
// ============================================================================

/**
 * Database connection information and statistics.
 *
 * @since 7.0.0
 * @since 10.0.0 Expanded with Firebird version detection
 */
class DbInfo
{
    private function __construct() {}

    /** Create from a connection resource. */
    public static function fromConnection(mixed $connection): static {}

    /** @return array<string, mixed> */
    public function toArray(): array {}

    /** Get the Firebird server version string. */
    public function getServerVersion(): string {}

    /** Get the Firebird server major version number. */
    public function getMajorVersion(): int {}

    /** Get the ODS (On-Disk Structure) version. */
    public function getOdsVersion(): int {}

    /** Get total page reads. */
    public function getPageReads(): int {}

    /** Get total page writes. */
    public function getPageWrites(): int {}

    /** Get current memory usage in bytes. */
    public function getCurrentMemory(): int {}

    /** Get maximum memory usage in bytes. */
    public function getMaxMemory(): int {}
}

// ============================================================================
// EVENT POLLING
// ============================================================================

/**
 * Interface for event polling strategies with timeout support.
 *
 * @since 10.0.0
 */
interface EventPollerInterface
{
    /**
     * Poll for events with a timeout.
     * @param mixed $eventHandler Event handler resource
     * @param int $timeoutMs Timeout in milliseconds
     * @return array<string, int>|int|false Event counts, FBIRD_EVENT_TIMEOUT, or false
     */
    public function poll(mixed $eventHandler, int $timeoutMs): mixed;

    /** Check if this polling strategy is available in the current environment. */
    public static function isAvailable(): bool;
}

/**
 * Factory for event pollers with auto-detection of best timeout strategy.
 *
 * @since 10.0.0
 */
final class EventPoller
{
    /** Create the best available event poller for the current environment. */
    public static function create(): EventPollerInterface {}

    /**
     * Create a specific event poller by name.
     * @param string $strategy One of 'pcntl', 'fiber', 'process'
     */
    public static function createStrategy(string $strategy): EventPollerInterface {}
}

/**
 * PCNTL-based event poller (uses pcntl_alarm for timeout).
 * Requires ext-pcntl.
 *
 * @since 10.0.0
 */
final class PcntlEventPoller implements EventPollerInterface
{
    public function poll(mixed $eventHandler, int $timeoutMs): mixed {}
    public static function isAvailable(): bool {}
}

/**
 * Fiber/AMPHP-based event poller.
 * Requires PHP 8.1+ and amphp/amp ^3.0.
 *
 * @since 10.0.0
 */
final class FiberEventPoller implements EventPollerInterface
{
    public function poll(mixed $eventHandler, int $timeoutMs): mixed {}
    public static function isAvailable(): bool {}
}

/**
 * Process-based event poller (forks a child process for timeout).
 * Requires ext-pcntl.
 *
 * @since 10.0.0
 */
final class ProcessEventPoller implements EventPollerInterface
{
    public function poll(mixed $eventHandler, int $timeoutMs): mixed {}
    public static function isAvailable(): bool {}
}
