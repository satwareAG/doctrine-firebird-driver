<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;

/**
 * Exception thrown when host and dbname parameters are missing.
 *
 * @internal
 *
 * @psalm-immutable
 */
final class HostDbnameRequired extends Exception
{
    public static function invalidPort(): self
    {
        return new self('The "port" parameter must be a valid Port number.');
    }

    public static function new(): self
    {
        return new self('The "host" and "dbname" parameters are required for Connection');
    }
}
