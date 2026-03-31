<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Enum;

/**
 * Represents the current state of a database transaction.
 *
 * @internal This enum is not covered by the backward compatibility promise
 */
enum TransactionState: int
{
    /** No explicit transaction is active (auto-commit mode). */
    case IDLE = 0;

    /** An explicit transaction has been started. */
    case ACTIVE = 1;

    /** A transaction with at least one savepoint is active. */
    case SAVEPOINT = 2;

    /** A transaction with multiple nested savepoints is active. */
    case NESTED_SAVEPOINT = 3;
}
