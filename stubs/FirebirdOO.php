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
