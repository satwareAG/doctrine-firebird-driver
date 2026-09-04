<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;

/**
 * Simulates extension builds whose fbird_sqlstate() throws when called
 * outside an active error context (#186).
 */
final class SqlStateProbeException extends Exception
{
    protected static function fetchSqlState(): string|null
    {
        throw new RuntimeException('no active error context');
    }
}
