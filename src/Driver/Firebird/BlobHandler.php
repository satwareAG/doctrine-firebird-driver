<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

/**
 * Handles Firebird BLOB (LARGE_OBJECT) data conversion.
 *
 * @internal This class is not covered by the backward compatibility promise
 */
final class BlobHandler
{
    /**
     * Converts a BLOB parameter value to a format acceptable by the Firebird API.
     *
     * Since php-firebird v9.0, stream resources are natively supported for BLOB
     * parameters in fbird_execute(). This allows large BLOBs to be sent to the
     * server without loading the entire content into PHP memory.
     *
     * @param mixed $value The parameter value
     * @param int   $type  The DBAL ParameterType (unused - extension handles type natively)
     *
     * @return mixed The converted value (stays a resource for LARGE_OBJECT)
     *
     * @psalm-suppress UnusedParam
     */
    public function toInternalValue(mixed $value, int $type): mixed
    {
        // Extension v9+ natively supports stream resources for BLOBs
        return $value;
    }
}
