<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Enum;

/**
 * Encapsulates the execution mode that is shared between the connection and its statements.
 *
 * @internal This enum is not covered by the backward compatibility promise
 */
enum ExecutionMode: int
{
    case AUTO_COMMIT   = 1;
    case MANUAL_COMMIT = 0;
}
