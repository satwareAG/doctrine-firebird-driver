<?php

/**
 * PHPStan stubs for php-firebird v9.0.0 new functions and constants.
 *
 * These supplement the external satwareag/php-firebird-stubs package until
 * a v9 stubs release is available. All declarations are in the global namespace.
 *
 * New in v9.0.0:
 * - fbird_create_database(): standalone DB creation (replaces deprecated FBIRD_CREATE)
 * - fbird_prepare_ex(): fixed prepare signature (no transaction parameter)
 * - FBIRD_EXCEPTION_MODE_COMPAT: alias for FBIRD_EXCEPTION_MODE_SILENT
 * - fbird_drop_db(): now accepts string DSN (drop by connection string)
 * - fbird_execute(): supports BLOB stream resource as parameter value
 *
 * @see https://github.com/satwareAG/php-firebird/releases/tag/v9.0.0
 */

declare(strict_types=1);

/**
 * Create a new Firebird database (standalone, no prior connection needed).
 *
 * @param string $dsn           Database connection string (host:port:path)
 * @param string $username      Database user (typically SYSDBA)
 * @param string $password      User password
 * @param array<string, mixed> $options Optional page size, charset, etc.
 *
 * @return resource Connection resource on success
 *
 * @throws \Firebird\Exception On failure (when exception mode is enabled)
 */
function fbird_create_database(
    string $dsn,
    string $username,
    string $password,
    array $options = [],
): mixed {
}

/**
 * Prepare a statement with a fixed signature (no transaction parameter).
 *
 * Uses the connection's default transaction instead of requiring an explicit
 * transaction resource.
 *
 * @param resource $connection Firebird connection resource
 * @param string   $sql        SQL statement with ? placeholders
 *
 * @return resource Prepared statement resource
 *
 * @throws \Firebird\Exception On failure (when exception mode is enabled)
 */
function fbird_prepare_ex(
    mixed $connection,
    string $sql,
): mixed {
}

/**
 * Alias for FBIRD_EXCEPTION_MODE_SILENT.
 *
 * Compatibility constant that maps to SILENT mode (functions return false
 * on error instead of throwing).
 */
const FBIRD_EXCEPTION_MODE_COMPAT = 0;