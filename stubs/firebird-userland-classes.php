<?php

/**
 * PHPStan stubs for php-firebird userland classes (Firebird namespace).
 *
 * These classes are provided by the php-firebird C extension as userland PHP classes
 * (not C-level classes). They are distributed with the extension itself, not in the
 * satwareag/php-firebird-stubs package (which only covers C-level extension symbols).
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

    public function start(): Transaction
    {
    }
}

/**
 * Represents an active Firebird transaction.
 */
class Transaction
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
