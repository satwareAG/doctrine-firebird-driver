<?php

/**
 * PHPStan stubs for php-firebird global functions not in satwareag/php-firebird-stubs.
 *
 * @see https://github.com/satwareAG/php-firebird
 */

declare(strict_types=1);

/**
 * Execute a parameterized query within a specific transaction.
 *
 * Available in php-firebird v7.0.0+.
 *
 * @param mixed $transaction Transaction resource or Transaction object
 * @param mixed $query       Prepared query resource
 * @param mixed ...$params   Query parameters
 *
 * @return mixed Result resource or false on failure
 */
function fbird_query_params_tx(mixed $transaction, mixed $query, mixed ...$params): mixed {}

/**
 * Execute SQL in an autonomous transaction (auto-commit).
 *
 * Executes a SQL statement in a separate autonomous transaction that is
 * automatically committed on success or rolled back on failure.
 *
 * Available in php-firebird v7.0.0+.
 *
 * @param resource     $link_identifier Database connection resource
 * @param string       $sql             SQL statement to execute
 * @param array<mixed> $params          Optional bind parameters
 *
 * @return int|false Number of affected rows or false on failure
 *
 * @since 7.0.0
 */
function fbird_execute_auto(mixed $link_identifier, string $sql, array $params = []): int|false {}
