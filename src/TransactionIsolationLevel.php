<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver;

use \Doctrine\DBAL\TransactionIsolationLevel as DoctrineTransactionIsolationLevel;

final class TransactionIsolationLevel
{
    /**
     * Transaction isolation level READ UNCOMMITTED is not supported by Firebird SQL.
     */
    public const READ_UNCOMMITTED = -1;

    /**
     * Transaction isolation level READ COMMITTED.
     * The Read Committed isolation level allows transactions to see only committed changes from other transactions.
     * This is typically used for most routine operations.
     */
    public const READ_COMMITTED = DoctrineTransactionIsolationLevel::READ_COMMITTED;

    /**
     * Transaction isolation level REPEATABLE READ.
     * The Repeatable Read level ensures that once a row is read,
     * it remains unchanged for the duration of the transaction, preventing other transactions from modifying it.
     */
    public const REPEATABLE_READ = DoctrineTransactionIsolationLevel::REPEATABLE_READ;

    /**
     * Transaction isolation level SERIALIZABLE.
     * The Serializable level is the strictest, ensuring that transactions operate as though they are
     * isolated entirely from each other. It’s often used when the highest data consistency is required.
     */
    public const SERIALIZABLE = DoctrineTransactionIsolationLevel::SERIALIZABLE;

    /**
     * Transaction isolation level SNAPSHOT.
     * The Snapshot isolation level (also known as Concurrency) provides a consistent view of the
     * database as it was at the start of the transaction. It’s often used in reporting scenarios.
     */
    public const SNAPSHOT = 5;

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
