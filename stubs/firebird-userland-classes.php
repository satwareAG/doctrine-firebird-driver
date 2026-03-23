<?php

/**
 * PHPStan stubs for php-firebird userland classes (Firebird namespace).
 *
 * These classes are provided by the php-firebird extension as userland PHP classes
 * distributed with the extension itself. The satwareag/php-firebird-stubs v8 package
 * covers C-level classes (Connection, Transaction, Statement, ResultSet, Blob, Service);
 * this file covers the remaining userland helpers not yet in the external stubs package.
 *
 * Changes in v8.0.0:
 * - Firebird\Transaction renamed to Firebird\TransactionManager to avoid conflict
 *   with the new C-level Firebird\Transaction class.
 *
 * @see https://github.com/satwareAG/php-firebird
 */

declare(strict_types=1);

namespace Firebird;

/**
 * Transaction builder — fluent API for creating Firebird transactions.
 */
class TBuilder
{
    private function __construct()
    {
    }

    public static function create(): static
    {
    }

    public function connection(mixed $connection): static
    {
    }

    public function isolation(int $level): static
    {
    }

    public function recordVersion(int $version): static
    {
    }

    public function lockResolution(int $resolution): static
    {
    }

    public function accessMode(int $mode): static
    {
    }

    public function start(): TransactionManager
    {
    }
}

/**
 * Represents an active Firebird transaction (userland wrapper).
 *
 * Renamed from Transaction to TransactionManager in v8.0.0 to avoid
 * conflict with the new C-level Firebird\Transaction class.
 */
class TransactionManager
{
    private function __construct()
    {
    }

    public function getResource(): mixed
    {
    }

    public function commit(): bool
    {
    }

    public function rollback(): bool
    {
    }

    public function commitRetaining(): bool
    {
    }
}

/**
 * OO wrapper for a Firebird database connection.
 */
class Database
{
    private function __construct()
    {
    }

    public static function fromResource(mixed $resource): static
    {
    }

    public function newTransaction(): TBuilder
    {
    }

    public function close(): bool
    {
    }
}

/**
 * Batch INSERT operation — IBatch API (Firebird 4.0+).
 */
class Batch
{
    public function __construct(mixed $connection, string $sql, mixed $transResource)
    {
    }

    /** @param array<int|string, mixed> $row */
    public function add(array $row): bool
    {
    }

    public function execute(): BatchResult
    {
    }

    public function cancel(): bool
    {
    }
}

/**
 * Result of a batch INSERT operation.
 */
class BatchResult
{
    private function __construct()
    {
    }

    public function count(): int
    {
    }

    /** @return array<int, mixed> */
    public function getErrors(): array
    {
    }
}

/**
 * Database connection information and statistics.
 */
class DbInfo
{
    private function __construct()
    {
    }

    public static function fromConnection(mixed $connection): static
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
    }
}
