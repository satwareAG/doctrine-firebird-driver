<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\TransactionIsolationLevel;
use Firebird\Transaction;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Enum\ExecutionMode;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Throwable;

use function fbird_commit;
use function fbird_commit_ret;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_release_savepoint;
use function fbird_rollback;
use function fbird_rollback_savepoint;
use function fbird_savepoint;
use function fbird_trans_start;
use function sprintf;
use function str_contains;

use const FBIRD_COMMITTED;
use const FBIRD_CONCURRENCY;
use const FBIRD_CONSISTENCY;
use const FBIRD_NOWAIT;
use const FBIRD_REC_VERSION;
use const FBIRD_WAIT;
use const FBIRD_WRITE;

/**
 * Manages Firebird database transactions, including nested transactions via savepoints.
 *
 * @internal This class is not covered by the backward compatibility promise
 */
final class TransactionManager
{
    /**
     * Firebird error code for "invalid transaction handle".
     */
    private const ER_INVALID_TRANSACTION_HANDLE = 335544332;

    private int $level = 0;

    private int $isolationLevel = TransactionIsolationLevel::READ_COMMITTED;

    /** Number of seconds to wait. */
    private int $waitTimeout = 5;

    private ExecutionMode $executionMode = ExecutionMode::AUTO_COMMIT;

    private Transaction|null $activeTransaction = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getActiveTransaction(): Transaction|null
    {
        return $this->activeTransaction;
    }

    public function setIsolationLevel(int $level): void
    {
        $this->isolationLevel = $level;
    }

    public function getIsolationLevel(): int
    {
        return $this->isolationLevel;
    }

    public function setWaitTimeout(int $timeout): void
    {
        $this->waitTimeout = $timeout;
    }

    public function getWaitTimeout(): int
    {
        return $this->waitTimeout;
    }

    public function getExecutionMode(): ExecutionMode
    {
        return $this->executionMode;
    }

    public function setExecutionMode(ExecutionMode $mode): void
    {
        $this->executionMode = $mode;
    }

    public function getResource(): Transaction|null
    {
        return $this->activeTransaction;
    }

    public function beginTransaction(): bool
    {
        if ($this->level === 0) {
            // As Firebird always generates a transaction, we have to commit everything now.
            if ($this->isTransactionValid()) {
                try {
                    fbird_commit($this->activeTransaction);
                } catch (Throwable $e) {
                    // Try rollback to clear state, then convert exception.
                    // Ignore "invalid transaction handle" errors - the transaction is already gone.
                    try {
                        fbird_rollback($this->activeTransaction);
                    } catch (Throwable) {
                    }

                    if (! $this->isInvalidTransactionHandle((int) $e->getCode(), $e->getMessage())) {
                        throw DriverException::fromThrowable($e);
                    }
                }
            }

            $this->activeTransaction = $this->createTransaction();
        } else {
            // Nested transaction: create a savepoint
            $this->createSavepoint($this->getSavepointName($this->level));
        }

        $this->level++;
        $this->executionMode = ExecutionMode::MANUAL_COMMIT;

        return true;
    }

    public function commit(): bool
    {
        if ($this->level > 0) {
            $this->level--;
        }

        if ($this->level === 0) {
            if (! $this->isTransactionValid()) {
                throw new DriverException('No active transaction resource.');
            }

            try {
                fbird_commit($this->activeTransaction);
            } catch (Throwable $e) {
                try {
                    fbird_rollback($this->activeTransaction);
                } catch (Throwable) {
                }

                if (! $this->isInvalidTransactionHandle((int) $e->getCode(), $e->getMessage())) {
                    throw DriverException::fromThrowable($e);
                }
            }

            $this->activeTransaction = $this->createTransaction();
            $this->executionMode     = ExecutionMode::AUTO_COMMIT;
        } else {
            // Nested transaction: release savepoint
            $this->releaseSavepoint($this->getSavepointName($this->level));
        }

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->level > 0) {
            $this->level--;
        }

        if ($this->level === 0) {
            // Defensive check: if transaction resource is invalid, reset state without attempting rollback
            if (! $this->isTransactionValid()) {
                if ($this->connection->isConnectionValid()) {
                    try {
                        $this->activeTransaction = $this->createTransaction();
                    } catch (DriverException) {
                        $this->activeTransaction = null;
                    }
                } else {
                    $this->activeTransaction = null;
                }

                $this->executionMode = ExecutionMode::AUTO_COMMIT;

                return true;
            }

            $rollbackException = null;
            try {
                fbird_rollback($this->activeTransaction);
            } catch (Throwable $e) {
                if (! $this->isInvalidTransactionHandle((int) $e->getCode(), $e->getMessage())) {
                    $rollbackException = $e;
                }
            }

            // Always attempt to restore valid state for next operation
            if ($this->connection->isConnectionValid()) {
                try {
                    $this->activeTransaction = $this->createTransaction();
                } catch (DriverException) {
                    $this->activeTransaction = null;
                }
            } else {
                $this->activeTransaction = null;
            }

            $this->executionMode = ExecutionMode::AUTO_COMMIT;

            if ($rollbackException !== null) {
                throw DriverException::fromThrowable($rollbackException);
            }
        } else {
            // Nested transaction: rollback to savepoint
            $this->rollbackSavepoint($this->getSavepointName($this->level));
        }

        return true;
    }

    public function autoCommit(): void
    {
        if ($this->executionMode !== ExecutionMode::AUTO_COMMIT || $this->level >= 1) {
            return;
        }

        if (! $this->isTransactionValid()) {
            throw new DriverException(sprintf(
                'No active transaction. level = %d',
                $this->level,
            ));
        }

        try {
            fbird_commit_ret($this->activeTransaction);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    public function createSavepoint(string $savepoint): void
    {
        if (! $this->isTransactionValid()) {
            throw new DriverException('No valid transaction resource.');
        }

        try {
            fbird_savepoint($this->activeTransaction, $savepoint);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    public function releaseSavepoint(string $savepoint): void
    {
        if (! $this->isTransactionValid()) {
            throw new DriverException('No valid transaction resource.');
        }

        try {
            fbird_release_savepoint($this->activeTransaction, $savepoint);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    public function rollbackSavepoint(string $savepoint): void
    {
        if (! $this->isTransactionValid()) {
            throw new DriverException('No valid transaction resource.');
        }

        try {
            fbird_rollback_savepoint($this->activeTransaction, $savepoint);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    /**
     * @psalm-assert-if-true Transaction $this->activeTransaction
     * @phpstan-assert-if-true Transaction $this->activeTransaction
     */
    public function isTransactionValid(): bool
    {
        return $this->activeTransaction instanceof Transaction;
    }

    /** @throws DriverException */
    public function createTransaction(): Transaction
    {
        if (! $this->connection->isConnectionValid()) {
            $this->connection->checkLastApiCall();
        }

        $options = ['access_mode' => FBIRD_WRITE];

        switch ($this->isolationLevel) {
            case TransactionIsolationLevel::READ_UNCOMMITTED:
            case TransactionIsolationLevel::READ_COMMITTED:
                $options['isolation'] = FBIRD_COMMITTED | FBIRD_REC_VERSION;
                break;
            case TransactionIsolationLevel::REPEATABLE_READ:
                $options['isolation'] = FBIRD_CONCURRENCY;
                break;
            case TransactionIsolationLevel::SERIALIZABLE:
                $options['isolation'] = FBIRD_CONSISTENCY;
                break;
        }

        if ($this->waitTimeout === -1) {
            $options['lock_resolution'] = FBIRD_WAIT;
        } elseif ($this->waitTimeout === 0) {
            $options['lock_resolution'] = FBIRD_NOWAIT;
        } else {
            $options['lock_resolution'] = FBIRD_WAIT;
            $options['lock_timeout']    = $this->waitTimeout;
        }

        $conn = $this->connection->getNativeConnection();

        // Validate the native handle before passing to fbird_trans_start().
        // v10+: connection may be a \Firebird\Connection object (M3 migration),
        // not a resource. Both are accepted by fbird_trans_start() via dual-accept.
        if ($conn === null) {
            throw new DriverException(
                'Native connection handle is invalid (closed or destroyed).',
            );
        }

        try {
            $transaction = fbird_trans_start($conn, $options);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }

        if ($transaction === false) {
            throw new DriverException(
                (string) fbird_errmsg(),
                null,
                (int) fbird_errcode(),
            );
        }

        return $transaction;
    }

    public function reset(): void
    {
        $this->activeTransaction = null;
        $this->level             = 0;
    }

    private function isInvalidTransactionHandle(int $code, string $message): bool
    {
        return $code === self::ER_INVALID_TRANSACTION_HANDLE
            || $code === -999
            || str_contains($message, 'invalid transaction handle');
    }

    private function getSavepointName(int $level): string
    {
        return 'TARGET_SP_' . $level;
    }
}
