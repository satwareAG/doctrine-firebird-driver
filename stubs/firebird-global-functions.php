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
 * Note: This is a PHP namespace function in src/Firebird/functions.php,
 * not a C extension function — no official stub is provided upstream.
 *
 * @param mixed $transaction Transaction resource or Transaction object
 * @param mixed $query       Prepared query resource
 * @param mixed ...$params   Query parameters
 *
 * @return mixed Result resource or false on failure
 */
function fbird_query_params_tx(mixed $transaction, mixed $query, mixed ...$params): mixed {}
