<?php

/**
 * PHP Firebird Extension Class Stubs
 *
 * This file provides stub declarations for classes provided by the php-firebird
 * C extension. These are used by static analysis tools and IDEs.
 *
 * @package   php-firebird-stubs
 * @version   10.3.6
 * @author    satware AG <info@satware.com>
 * @copyright 2025 satware AG
 * @license   PHP-3.01 https://www.php.net/license/3_01.txt
 * @link      https://github.com/satwareAG/php-firebird
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Firebird;

if (extension_loaded('fbird')) {
    return;
}

/** Opaque event handle returned by fbird_set_event_handler(). @since 7.0.0 */
final class Event
{
    private function __construct() {}
}

/**
 * Base exception for all Firebird errors (thrown when exception mode is enabled).
 * @since 7.0.0
 */
class Exception extends \RuntimeException
{
    protected string $sqlState = '00000';
    public function getSqlState(): string { return $this->sqlState; }
}

/** Exception for database connection errors. @since 8.0.0 */
class DatabaseException extends Exception {}

/** Exception for transaction errors. @since 8.0.0 */
class TransactionException extends Exception {}

/**
 * Native OOP interface to a Firebird database connection.
 * @since 8.0.0
 */
class Connection
{
    /**
     * @param string $database  Database path or host:path.
     * @param string $username  Database user name.
     * @param string $password  Database password.
     * @param string $charset   Character set (default: UTF8).
     * @param int    $buffers   Cache buffer count.
     * @param int    $dialect   SQL dialect (1 or 3).
     * @param string $role      SQL role name.
     * @param bool   $persistent Use persistent connection.
     * @throws DatabaseException
     */
    public function __construct(
        string $database,
        string $username,
        string $password,
        string $charset = 'UTF8',
        int $buffers = 0,
        int $dialect = 3,
        string $role = '',
        bool $persistent = false
    ) {}

    /** Close the connection. */
    public function close(): bool { return true; }

    /** Check whether the connection is alive. */
    public function isConnected(): bool { return true; }

    /** Ping the server. */
    public function ping(): bool { return true; }

    /**
     * Begin a new transaction.
     * @param int $flags PHP_FBIRD_* transaction flags.
     * @throws TransactionException
     */
    public function beginTransaction(int $flags = 0): Transaction {}

    /**
     * Prepare a SQL statement.
     * @param string           $sql         SQL text.
     * @param Transaction|null $transaction Transaction context.
     * @param int              $dialect     SQL dialect override.
     * @throws Exception
     */
    public function prepare(string $sql, ?Transaction $transaction = null, int $dialect = 3): Statement {}
}

/**
 * Represents an active Firebird transaction.
 * Obtained via Connection::beginTransaction().
 * @since 8.0.0
 */
class Transaction
{
    private function __construct() {}

    /** Commit the transaction. @throws TransactionException */
    public function commit(): bool { return true; }

    /** Roll back the transaction. @throws TransactionException */
    public function rollback(): bool { return true; }

    /** Check whether the transaction is still active. */
    public function isActive(): bool { return true; }
}

/**
 * Represents a prepared Firebird SQL statement.
 * Obtained via Connection::prepare().
 * @since 8.0.0
 */
class Statement
{
    private function __construct() {}

    /**
     * Execute the statement.
     * @param mixed ...$params Bind parameters.
     * @return ResultSet|bool ResultSet for SELECT, true for DML/DDL.
     * @throws Exception
     */
    public function execute(mixed ...$params): ResultSet|bool {}
}

/**
 * Result set of a Firebird SELECT query.
 * Obtained via Statement::execute().
 * @since 8.0.0
 */
class ResultSet
{
    private function __construct() {}

    /**
     * Fetch the next row as an associative array.
     * @return array<string,mixed>|false Row data or false when exhausted.
     */
    public function fetch(): array|false {}

    /** Close the cursor. */
    public function close(): bool { return true; }
}

/**
 * Native OOP interface to Firebird BLOB columns.
 * @since 8.0.0
 */
class Blob
{
    private function __construct() {}

    /**
     * Create a new BLOB for writing.
     * @throws Exception
     */
    public static function create(Connection $connection, Transaction $transaction): static {}

    /**
     * Open an existing BLOB for reading.
     * @param string $blobId BLOB quad identifier.
     * @throws Exception
     */
    public static function open(Connection $connection, Transaction $transaction, string $blobId): static {}

    /** Write data to the BLOB. @throws Exception */
    public function write(string $data): bool { return true; }

    /**
     * Read a segment from the BLOB.
     * @return string|false Data or false at end.
     */
    public function read(int $length = 8192): string|false {}

    /** Close the BLOB handle. */
    public function close(): bool { return true; }

    /** Get the BLOB identifier string. */
    public function getId(): string { return ''; }
}

/**
 * Native OOP interface to the Firebird Services API.
 * @since 8.0.0
 */
class Service
{
    /**
     * Attach to the Firebird Services Manager.
     * @throws Exception
     */
    public function __construct(
        string $host = 'localhost',
        string $username = 'SYSDBA',
        string $password = 'masterkey'
    ) {}

    /** Detach from the Services Manager. */
    public function detach(): bool { return true; }

    /** Check whether the service handle is attached. */
    public function isAttached(): bool { return true; }

    /**
     * Get the Firebird server version string.
     * @throws Exception
     */
    public function getServerVersion(): string { return ''; }
}
