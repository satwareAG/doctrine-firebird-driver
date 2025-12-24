<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\Deprecations\Deprecation;
use Firebird\Batch;
use Firebird\BatchResult;
use Firebird\Database;
use Firebird\DbInfo;
use Firebird\TBuilder;
use Firebird\Transaction;
use InvalidArgumentException;
use Override;
use PDO;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use UnexpectedValueException;

use function addcslashes;
use function assert;
use function class_exists;
use function defined;
use function fbird_close;
use function fbird_commit;
use function fbird_commit_ret;
use function fbird_connection_info;
use function fbird_drop_table_force;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_execute_auto;
use function fbird_get_limbo_transactions;
use function fbird_kill_attachment;
use function fbird_list_table_blockers;
use function fbird_prepare;
use function fbird_query_params_tx;
use function fbird_reconnect_transaction;
use function fbird_release_savepoint;
use function fbird_rollback;
use function fbird_rollback_savepoint;
use function fbird_savepoint;
use function fbird_trans_start;
use function function_exists;
use function get_resource_type;
use function in_array;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function version_compare;

use const FBIRD_COMMITTED;
use const FBIRD_CONCURRENCY;
use const FBIRD_CONSISTENCY;
use const FBIRD_NOWAIT;
use const FBIRD_REC_VERSION;
use const FBIRD_WAIT;
use const FBIRD_WRITE;

/**
 * Based on https://github.com/helicon-os/doctrine-dbal
 * and Doctrine\DBAL\Driver\OCI8\Connection
 */
final class Connection implements ServerInfoAwareConnection
{
    /**
     * Valid resource types for Firebird connection.
     * Supports both php-interbase (legacy) and php-firebird v7.0.0+ resource type strings.
     */
    private const RESOURCE_TYPES_CONNECTION = [
        'Firebird/InterBase link',    // php-interbase and older php-firebird
        'Firebird link',              // php-firebird v7.0.0+
    ];

    /**
     * Valid resource types for Firebird persistent connection.
     * Supports both php-interbase (legacy) and php-firebird v7.0.0+ resource type strings.
     */
    private const RESOURCE_TYPES_PERSISTENT_CONNECTION = [
        'Firebird/InterBase persistent link',  // php-interbase and older php-firebird
        'Firebird persistent link',            // php-firebird v7.0.0+
    ];

    /**
     * Valid resource types for Firebird transaction.
     * Supports both php-interbase (legacy) and php-firebird v7.0.0+ resource type strings.
     */
    private const RESOURCE_TYPES_TRANSACTION = [
        'Firebird/InterBase transaction',  // php-interbase and older php-firebird
        'Firebird transaction',            // php-firebird v7.0.0+
    ];

    private readonly ExecutionMode $executionMode;

    /**
     * Isolation level used when a transaction is started.
     */
    private int $attrDcTransIsolationLevel = TransactionIsolationLevel::READ_COMMITTED;

    /**
     * Wait timeout used in transactions
     *
     * @var int  Number of seconds to wait.
     */
    private int $attrDcTransWait = 5;

    /**
     * True if auto-commit is enabled
     */
    private bool $attrAutoCommit = true;

    private string|null $connectionInsertColumn = null;

    private int|null $connectionInsertId = null;

    private readonly Parser $parser;

    private int $fbirdTransactionLevel = 0;

    /** @var resource|null */
    private $firebirdActiveTransaction = null;

    /**
     * @param resource|null        $connection
     * @param array<string, mixed> $params
     *
     * @throws Exception
     */
    public function __construct(private $connection, private readonly string $serverVersion, protected bool $isPersistent, private readonly Exception|null $databaseNotFoundException, array $params)
    {
        $this->parser        = new Parser(false);
        $this->executionMode = new ExecutionMode();

        if ($connection !== null) {
            // Enable Exception Mode API if available (php-firebird v7.0.0-rc.6+)
            // This provides PDO::ERRMODE_EXCEPTION-like behavior where Firebird API
            // functions throw Firebird\Exception instead of returning false on errors.
            // Note: This is a GLOBAL setting affecting all Firebird operations in this process.
            // We enable it AFTER connection is established to avoid interfering with database creation.
            if (function_exists('fbird_set_exception_mode') && defined('FBIRD_EXCEPTION_MODE_THROW')) {
                \fbird_set_exception_mode(\FBIRD_EXCEPTION_MODE_THROW);
            }

            $this->firebirdActiveTransaction = $this->createTransaction();
        }

        foreach ($params as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function __destruct()
    {
        $connectionClosable = false;
        if (is_resource($this->connection)) {
            $type = get_resource_type($this->connection);
            if (in_array($type, self::RESOURCE_TYPES_CONNECTION, true)) {
                $connectionClosable = true;
            } elseif (in_array($type, self::RESOURCE_TYPES_PERSISTENT_CONNECTION, true)) {
                $connectionClosable = false;
            } elseif ($type === 'Unknown') {
                $this->connection = null;
            }
        }

        if (is_resource($this->connection) && is_resource($this->firebirdActiveTransaction)) {
            $type = get_resource_type($this->firebirdActiveTransaction);
            if (in_array($type, self::RESOURCE_TYPES_TRANSACTION, true)) {
                // Try commit first, but if it fails (e.g., due to FK constraint locks),
                // fall back to rollback. Suppress warnings since this is cleanup code.
                // The @ operator prevents PHP warnings during destructor cleanup which
                // cannot be reasonably handled at this point.
                if (! @fbird_commit($this->firebirdActiveTransaction)) {
                    // If commit fails, try rollback to clean up gracefully
                    @fbird_rollback($this->firebirdActiveTransaction);
                }
            }

            unset($this->firebirdActiveTransaction);
            $this->firebirdActiveTransaction = null;
        }

        if ($connectionClosable) {
            fbird_close($this->connection);
        }

        unset($this->connection);
        $this->connection = null;
    }

    /** @return resource|null */
    public function getActiveTransaction()
    {
        return $this->firebirdActiveTransaction;
    }

    /**
     * Additionally to the standard driver attributes, the attribute
     * {@link FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL} can be used to control the
     * isolation level used for transactions.
     */
    public function setAttribute(string|int $attribute, mixed $value): void
    {
        switch ($attribute) {
            case FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL:
                $this->attrDcTransIsolationLevel = $value;
                break;
            case FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT:
                $this->attrDcTransWait = $value;
                break;
            case FirebirdDriver::ATTR_AUTOCOMMIT:
                $this->attrAutoCommit = $value;
                break;
        }
    }

    public function getAttribute(string|int $attribute): int|bool|null
    {
        return match ($attribute) {
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL
              => $this->attrDcTransIsolationLevel,
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT
              => $this->attrDcTransWait,
            PDO::ATTR_AUTOCOMMIT
              => $this->attrAutoCommit,
            PDO::ATTR_PERSISTENT
              => $this->isPersistent,
            default => null,
        };
    }

    public function getConnectionInsertColumn(): string|null
    {
        return $this->connectionInsertColumn;
    }

    #[Override]
    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    /**
     * @throws Exception
     * @throws Exception
     * @throws Parser\Exception
     */
    #[Override]
    public function prepare(string $sql): DriverStatement
    {
        if ($this->connection === null && is_object($this->databaseNotFoundException)) {
            throw $this->databaseNotFoundException;
        }

        // Defensive check: validate connection and transaction are still valid
        // PHP Firebird extension 6.2.0 crashes if called with invalid resources
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        if (! $this->isTransactionValid()) {
            throw new DriverException('Transaction is not valid.');
        }

        $visitor = new ConvertParameters();

        $this->parser->parse($sql, $visitor);

        $sql = $visitor->getSQL();

        // Defensive check: ensure SQL is not empty after parameter conversion
        // PHP Firebird extension 6.2.0 crashes with empty/null SQL
        if ($sql === '') {
            throw new DriverException('SQL statement is empty.');
        }

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        // (php-firebird v7.0.0-rc.6+). The @ operator only suppresses warnings, not exceptions.
        try {
            $stmt = @fbird_prepare($this->connection, $this->firebirdActiveTransaction, $sql);

            if ($stmt === false) {
                $this->checkLastApiCall();
            }
        } catch (\Throwable $e) {
            throw DriverException::fromThrowable($e);
        }

        return new Statement(
            $this,
            $stmt,
            $visitor->getParameterMap(),
            $sql,
        );
    }

    public function setConnectionInsertColumn(string|null $column): void
    {
        $this->connectionInsertColumn = $column;
    }

    #[Override]
    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function quote($value, $type = ParameterType::STRING): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException('Given value is not scalar.');
        }

        $value = str_replace("'", "''", (string) $value);

        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }

    #[Override]
    public function exec(string $sql): int
    {
        return $this->prepare($sql)->execute()->rowCount();
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     *
     * @psalm-suppress DocblockTypeContradiction
     */
    #[Override]
    public function lastInsertId($name = null): string|int|false
    {
        if ($name !== null && ! is_string($name)) {
            throw new InvalidArgumentException(sprintf('Argument $name in %s must be null or a string. Found: %s', __FUNCTION__, ValueFormatter::found($name)));
        }

        if ($name === null) {
            return $this->connectionInsertId ?? false;
        }

        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4687',
            'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
        );

        if (str_contains($name, '.')) {
            return $this->connectionInsertId ?? false;
        }

        if (str_starts_with($name, 'SELECT RDB')) {
            $name = $this->query($name)->fetchOne();
        } else {
            $maxGeneratorLength = 31;
            $regex              = '/^\w{1,' . $maxGeneratorLength . '}$/';
            if (preg_match($regex, $name) !== 1) {
                throw new UnexpectedValueException(sprintf(
                    "Expects argument \$name to match regular expression '%s'. Found: %s",
                    $regex,
                    ValueFormatter::found($name),
                ));
            }
        }

        $sql     = 'SELECT GEN_ID(' . $name . ', 0) LAST_VAL FROM RDB$DATABASE';
        $lastVal = $this->query($sql)->fetchOne();

        return $lastVal === 0 ? false : $lastVal;
    }

    public function setLastInsertId(int $id): void
    {
        $this->connectionInsertId = $id;
    }

    #[Override]
    public function beginTransaction(): bool
    {
        if ($this->fbirdTransactionLevel === 0) {
            // as Firebird always generates a transaction, we have to commit everything now.
            if (is_resource($this->firebirdActiveTransaction)) {
                // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
                try {
                    if (! @fbird_commit($this->firebirdActiveTransaction)) {
                        // If implicit commit fails, try rollback to clear state before throwing
                        @fbird_rollback($this->firebirdActiveTransaction);
                        $this->checkLastApiCall();
                    }
                } catch (\Throwable $e) {
                    // Try rollback to clear state, then convert exception
                    try {
                        @fbird_rollback($this->firebirdActiveTransaction);
                    } catch (\Throwable) {
                        // Ignore rollback exception during cleanup
                    }

                    throw DriverException::fromThrowable($e);
                }
            }

            $this->firebirdActiveTransaction = $this->createTransaction();
        } else {
            // Nested transaction: create a savepoint
            $this->createSavepoint($this->getSavepointName($this->fbirdTransactionLevel));
        }

        $this->fbirdTransactionLevel++;
        $this->executionMode->disableAutoCommit();

        return true;
    }

    #[Override]
    public function commit(): bool
    {
        if ($this->fbirdTransactionLevel > 0) {
            $this->fbirdTransactionLevel--;
        }

        if ($this->fbirdTransactionLevel === 0) {
            if (! is_resource($this->firebirdActiveTransaction)) {
                throw new RuntimeException('No active transaction resource.');
            }

            // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
            try {
                if (! @fbird_commit($this->firebirdActiveTransaction)) {
                    // Capture error, attempt rollback cleanup, then throw
                    $lastError = $this->errorInfo();
                    @fbird_rollback($this->firebirdActiveTransaction);

                    if (isset($lastError['code']) && $lastError['code'] !== 0) {
                        throw DriverException::fromErrorInfo($lastError['message'], $lastError['code']);
                    }
                }
            } catch (\Throwable $e) {
                // Try rollback cleanup, then convert exception
                try {
                    @fbird_rollback($this->firebirdActiveTransaction);
                } catch (\Throwable) {
                    // Ignore rollback exception during cleanup
                }

                throw DriverException::fromThrowable($e);
            }

            $this->firebirdActiveTransaction = $this->createTransaction();
            $this->executionMode->enableAutoCommit();
        } else {
            // Nested transaction: release savepoint
            $this->releaseSavepoint($this->getSavepointName($this->fbirdTransactionLevel));
        }

        return true;
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started.
     *
     * @throws RuntimeException|Exception
     */
    public function autoCommit(): void
    {
        if (! $this->executionMode->isAutoCommitEnabled() || $this->fbirdTransactionLevel >= 1) {
            return;
        }

        if (is_resource($this->firebirdActiveTransaction) === false) {
            throw new RuntimeException(sprintf(
                'No active transaction. $this->_fbirdTransactionLevel = %d',
                $this->fbirdTransactionLevel,
            ));
        }

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        try {
            $success = @fbird_commit_ret($this->firebirdActiveTransaction);

            if ($success !== false) {
                return;
            }

            $this->checkLastApiCall();
        } catch (\Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    /**
     * {@inheritdoc)
     *
     * @throws RuntimeException
     */
    #[Override]
    public function rollBack(): bool
    {
        if ($this->fbirdTransactionLevel > 0) {
            $this->fbirdTransactionLevel--;
        }

        if ($this->fbirdTransactionLevel === 0) {
            // Defensive check: if transaction resource is invalid, reset state without attempting rollback
            // This prevents crashes when called on corrupted resources during cleanup
            if (! $this->isTransactionValid()) {
                // If connection is still valid, create a new transaction; otherwise reset to null
                if ($this->isConnectionValid()) {
                    try {
                        $this->firebirdActiveTransaction = $this->createTransaction();
                    } catch (DriverException) {
                        // If we can't create transaction, set to null - connection might be closed
                        $this->firebirdActiveTransaction = null;
                    }
                } else {
                    $this->firebirdActiveTransaction = null;
                }

                $this->executionMode->enableAutoCommit();

                return true;
            }

            // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
            $success   = true;
            $lastError = null;
            try {
                $success = @fbird_rollback($this->firebirdActiveTransaction);

                if (! $success) {
                    // Capture error before resetting state
                    $lastError = $this->errorInfo();
                }
            } catch (\Throwable $e) {
                $success   = false;
                $lastError = ['code' => $e->getCode(), 'message' => $e->getMessage(), 'exception' => $e];
            }

            // Always attempt to restore valid state for next operation
            if ($this->isConnectionValid()) {
                try {
                    $this->firebirdActiveTransaction = $this->createTransaction();
                } catch (DriverException) {
                    // If we can't create transaction, set to null - connection might be closed
                    $this->firebirdActiveTransaction = null;
                }
            } else {
                $this->firebirdActiveTransaction = null;
            }

            $this->executionMode->enableAutoCommit();

            if (! $success && isset($lastError['code']) && $lastError['code'] !== 0) {
                throw DriverException::fromErrorInfo($lastError['message'], $lastError['code']);
            }
        } else {
            // Nested transaction: rollback to savepoint
            $this->rollbackSavepoint($this->getSavepointName($this->fbirdTransactionLevel));
        }

        return true;
    }

    /**
     * Create a new savepoint.
     *
     * @throws DriverException
     */
    public function createSavepoint(string $savepoint): void
    {
        // Use isTransactionValid() to check resource type, not just existence
        // PHP Firebird extension 6.2.0 crashes if called with invalid/Unknown resources
        if (! $this->isTransactionValid()) {
            throw new RuntimeException('No valid transaction resource.');
        }

        assert(is_resource($this->firebirdActiveTransaction));

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        try {
            if (! @fbird_savepoint($this->firebirdActiveTransaction, $savepoint)) {
                $this->checkLastApiCall();

                throw new DriverException(sprintf('Failed to create savepoint "%s"', $savepoint));
            }
        } catch (\Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    /**
     * Release a savepoint.
     *
     * @throws DriverException
     */
    public function releaseSavepoint(string $savepoint): void
    {
        // Use isTransactionValid() to check resource type, not just existence
        // PHP Firebird extension 6.2.0 crashes if called with invalid/Unknown resources
        if (! $this->isTransactionValid()) {
            throw new RuntimeException('No valid transaction resource.');
        }

        assert(is_resource($this->firebirdActiveTransaction));

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        try {
            if (! @fbird_release_savepoint($this->firebirdActiveTransaction, $savepoint)) {
                $this->checkLastApiCall();

                throw new DriverException(sprintf('Failed to release savepoint "%s"', $savepoint));
            }
        } catch (\Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    /**
     * Rollback to a savepoint.
     *
     * @throws DriverException
     */
    public function rollbackSavepoint(string $savepoint): void
    {
        // Use isTransactionValid() to check resource type, not just existence
        // PHP Firebird extension 6.2.0 crashes if called with invalid/Unknown resources
        if (! $this->isTransactionValid()) {
            throw new RuntimeException('No valid transaction resource.');
        }

        assert(is_resource($this->firebirdActiveTransaction));

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        try {
            if (! @fbird_rollback_savepoint($this->firebirdActiveTransaction, $savepoint)) {
                $this->checkLastApiCall();

                throw new DriverException(sprintf('Failed to rollback to savepoint "%s"', $savepoint));
            }
        } catch (\Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function errorInfo(): array
    {
        $errorCode = fbird_errcode();
        if ($errorCode !== false) {
            return [
                'code' => $errorCode,
                'message' => fbird_errmsg(),
            ];
        }

        return [
            'code' => 0,
            'message' => null,
        ];
    }

    /**
     * Checks fbird_error and raises an exception if an error occured
     *
     * @throws DriverException
     */
    public function checkLastApiCall(): void
    {
        $lastError = $this->errorInfo();
        if (! isset($lastError['code']) || $lastError['code'] === 0) {
            return;
        }

        throw DriverException::fromErrorInfo($lastError['message'], $lastError['code']);
    }

    /** @return resource|null */
    public function getNativeConnection()
    {
        return $this->connection;
    }

    /**
     * Check if the connection resource is valid.
     *
     * @return bool True if connection is a valid Firebird resource
     */
    public function isConnectionValid(): bool
    {
        if (! is_resource($this->connection)) {
            return false;
        }

        $type = get_resource_type($this->connection);

        return in_array($type, self::RESOURCE_TYPES_CONNECTION, true)
            || in_array($type, self::RESOURCE_TYPES_PERSISTENT_CONNECTION, true);
    }

    /**
     * Check if the active transaction resource is valid.
     *
     * @return bool True if transaction is a valid Firebird resource
     */
    public function isTransactionValid(): bool
    {
        if (! is_resource($this->firebirdActiveTransaction)) {
            return false;
        }

        return in_array(get_resource_type($this->firebirdActiveTransaction), self::RESOURCE_TYPES_TRANSACTION, true);
    }

    /**
     * List attachments blocking access to a table.
     *
     * Queries the monitoring tables to find active attachments that hold
     * locks on the specified table. Useful for identifying connections
     * blocking DDL operations.
     *
     * @param string $tableName Name of the table to check for blockers
     *
     * @return array<int, array<string, mixed>>|false Array of blocker info or false on error
     */
    public function listTableBlockers(string $tableName): array|false
    {
        if (! is_resource($this->connection)) {
            return false;
        }

        $result = fbird_list_table_blockers($this->connection, $tableName);

        if ($result === false) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * Kill a specific database attachment.
     *
     * Terminates another database connection by attachment ID. Requires SYSDBA
     * privileges or owner rights.
     *
     * @param int $attachmentId The MON$ATTACHMENT_ID of the attachment to kill
     *
     * @throws DriverException
     */
    public function killAttachment(int $attachmentId): bool
    {
        if (! is_resource($this->connection)) {
            throw new DriverException('No active connection.');
        }

        $result = fbird_kill_attachment($this->connection, $attachmentId);

        if ($result === false) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * Force drop a table by killing blocking attachments first.
     *
     * Terminates all blocking attachments and then drops the table.
     * Requires SYSDBA privileges.
     *
     * WARNING: This is a destructive operation that will terminate other
     * sessions and permanently delete the table.
     *
     * @param string $tableName Name of the table to drop
     *
     * @throws DriverException
     */
    public function dropTableForce(string $tableName): bool
    {
        if (! is_resource($this->connection)) {
            throw new DriverException('No active connection.');
        }

        $result = fbird_drop_table_force($this->connection, $tableName);

        if ($result === false) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * Execute SQL in an autonomous transaction (auto-commit).
     *
     * Executes a SQL statement in a separate autonomous transaction that is
     * automatically committed on success or rolled back on failure.
     *
     * @param string            $sql    SQL statement to execute
     * @param array<mixed>|null $params Optional array of bind parameters
     *
     * @return int|false Number of affected rows for DML, or false on failure
     *
     * @throws DriverException
     */
    public function executeAuto(string $sql, array|null $params = null): int|false
    {
        if (! is_resource($this->connection)) {
            throw new DriverException('No active connection.');
        }

        $result = fbird_execute_auto($this->connection, $sql, $params);

        if ($result === false) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * Execute a query within a specific transaction context.
     *
     * UNIQUE TO php-firebird: This method uses fbird_query_params_tx() to execute
     * queries within a specific transaction. No other PHP Firebird driver has this
     * capability.
     *
     * Use cases:
     * - Audit logging that persists regardless of main transaction outcome
     * - CQRS patterns with different isolation levels for reads/writes
     * - Multi-transaction workflows (e.g., long-running batch with progress tracking)
     *
     * @param resource|Transaction    $transaction Transaction resource or OO wrapper
     * @param string                  $sql         SQL statement to execute
     * @param array<int|string,mixed> $params      Optional bind parameters
     *
     * @return mixed Query result resource or affected row count
     *
     * @throws DriverException
     */
    public function queryInTransaction(mixed $transaction, string $sql, array $params = []): mixed
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        // Support both raw resource and OO Transaction wrapper
        $transResource = $transaction instanceof Transaction
            ? $transaction->getResource()
            : $transaction;

        if (! is_resource($transResource)) {
            throw new DriverException('Invalid transaction resource.');
        }

        $result = fbird_query_params_tx($this->connection, $transResource, $sql, $params);

        if ($result === false) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * Create a new independent transaction using the TBuilder fluent API.
     *
     * This allows creating transactions with specific isolation levels and
     * parameters, independent of the DBAL-managed transaction.
     *
     * Example:
     *   $auditTx = $conn->createIndependentTransaction()
     *       ->readCommitted()
     *       ->wait(10)
     *       ->start();
     *   $conn->queryInTransaction($auditTx, 'INSERT INTO audit_log...');
     *   $auditTx->commit();
     *
     * @return TBuilder Transaction builder for fluent configuration
     *
     * @throws DriverException
     */
    public function createIndependentTransaction(): TBuilder
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        return TBuilder::create()->connection($this->connection);
    }

    /**
     * Wrap the native connection in an OO Database wrapper.
     *
     * This provides access to the full php-firebird OO API including:
     * - Transaction builder pattern
     * - BLOB streaming
     * - Database info queries
     *
     * @return Database OO wrapper for the native connection
     *
     * @throws DriverException
     */
    public function getOOWrapper(): Database
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        return Database::fromResource($this->connection);
    }

    // =========================================================================
    // IBatch API - php-firebird v7.0.0+ (Firebird 4.0+)
    // Provides 10-12x performance improvement for bulk INSERT operations
    // =========================================================================

    /**
     * Create a batch operation for efficient bulk INSERTs.
     *
     * The IBatch API (Firebird 4.0+) provides 10-12x performance improvement
     * over individual INSERT statements by batching multiple rows into a
     * single server round-trip.
     *
     * Example:
     *   $batch = $conn->createBatch('INSERT INTO users (name, email) VALUES (?, ?)');
     *   $batch->add(['Alice', 'alice@example.com']);
     *   $batch->add(['Bob', 'bob@example.com']);
     *   $result = $batch->execute();
     *   echo "Inserted: " . $result->count() . " rows";
     *
     * @param string                    $sql         INSERT statement with placeholders
     * @param Transaction|resource|null $transaction Optional transaction (uses active if null)
     *
     * @return Batch Batch object for adding rows and executing
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function createBatch(string $sql, Transaction|null $transaction = null): Batch
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        // IBatch API requires Firebird 4.0+
        if (version_compare($this->serverVersion, '4.0', '<')) {
            throw new DriverException(sprintf(
                'IBatch API requires Firebird 4.0 or later. Current version: %s. ' .
                'Use traditional INSERT loops for Firebird 2.5/3.0.',
                $this->serverVersion,
            ));
        }

        // Use provided transaction or fall back to active transaction
        $transResource = $transaction instanceof Transaction
            ? $transaction->getResource()
            : $this->firebirdActiveTransaction;

        if (! is_resource($transResource)) {
            throw new DriverException('No valid transaction available for batch operation.');
        }

        return new Batch($this->connection, $sql, $transResource);
    }

    /**
     * Execute a batch INSERT with data array (convenience method).
     *
     * High-level convenience wrapper around createBatch() for simple use cases.
     * Automatically creates batch, adds all rows, executes, and returns result.
     *
     * Example:
     *   $result = $conn->executeBatch(
     *       'INSERT INTO users (name, email) VALUES (?, ?)',
     *       [
     *           ['Alice', 'alice@example.com'],
     *           ['Bob', 'bob@example.com'],
     *           ['Charlie', 'charlie@example.com'],
     *       ]
     *   );
     *   echo "Inserted: " . $result->count() . " rows";
     *   foreach ($result->getErrors() as $error) {
     *       echo "Row " . $error->getRow() . " failed: " . $error->getMessage();
     *   }
     *
     * @param string                               $sql         INSERT statement with placeholders
     * @param array<int, array<int|string, mixed>> $rows        Array of row data arrays
     * @param Transaction|null                     $transaction Optional transaction
     *
     * @return BatchResult Result with row counts and any errors
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function executeBatch(string $sql, array $rows, Transaction|null $transaction = null): BatchResult
    {
        $batch = $this->createBatch($sql, $transaction);

        foreach ($rows as $row) {
            $batch->add($row);
        }

        return $batch->execute();
    }

    // =========================================================================
    // Connection Info - php-firebird v7.0.0+
    // =========================================================================

    /**
     * Get connection statistics and information.
     *
     * Returns detailed connection metrics including:
     * - Current reads/writes/fetches
     * - Memory usage
     * - Buffer pool statistics
     * - Transaction statistics
     *
     * Useful for monitoring, diagnostics, and performance tuning.
     *
     * @return DbInfo|array<string, mixed>|false Connection info or false on error
     *
     * @throws DriverException
     */
    public function getConnectionInfo(): DbInfo|array|false
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        // Use OO API if available (php-firebird v7+)
        // Falls back to procedural fbird_connection_info() if OO not available
        if (class_exists(DbInfo::class)) {
            return DbInfo::fromConnection($this->connection);
        }

        // Procedural fallback
        if (function_exists('fbird_connection_info')) {
            return fbird_connection_info($this->connection);
        }

        return false;
    }

    // =========================================================================
    // Limbo Transaction Recovery - php-firebird v7.0.0+
    // For recovering from two-phase commit failures
    // =========================================================================

    /**
     * Get list of limbo (in-doubt) transactions.
     *
     * Limbo transactions occur during two-phase commit failures.
     * This method returns transaction IDs that need manual recovery
     * (commit or rollback decision by DBA).
     *
     * Requires SYSDBA or database owner privileges.
     *
     * @return array<int>|false Array of limbo transaction IDs or false on error
     *
     * @throws DriverException
     */
    public function getLimboTransactions(): array|false
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        if (! function_exists('fbird_get_limbo_transactions')) {
            throw new DriverException(
                'fbird_get_limbo_transactions() requires php-firebird v7.0.0+',
            );
        }

        $result = fbird_get_limbo_transactions($this->connection);

        if ($result === false) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * Reconnect to a limbo transaction for recovery.
     *
     * After reconnecting, the transaction can be committed or rolled back
     * to resolve the limbo state. This is typically used by DBAs to
     * recover from two-phase commit failures.
     *
     * Requires SYSDBA or database owner privileges.
     *
     * @param int $transactionId The limbo transaction ID to reconnect
     *
     * @return resource|false Transaction resource for commit/rollback, or false on error
     *
     * @throws DriverException
     */
    public function reconnectLimboTransaction(int $transactionId): mixed
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        if (! function_exists('fbird_reconnect_transaction')) {
            throw new DriverException(
                'fbird_reconnect_transaction() requires php-firebird v7.0.0+',
            );
        }

        assert(is_resource($this->connection));

        $result = fbird_reconnect_transaction($this->connection, $transactionId);

        if ($result === false) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    private function getSavepointName(int $level): string
    {
        return 'TARGET_SP_' . $level;
    }

    /**
     * @return resource The firebird transaction.
     * @psalm-return resource
     *
     * @throws DriverException
     */
    private function createTransaction()
    {
        if (! is_resource($this->connection) || get_resource_type($this->connection) === 'Unknown') {
            $this->checkLastApiCall();
        }

        $options = ['access_mode' => FBIRD_WRITE];

        switch ($this->attrDcTransIsolationLevel) {
            case TransactionIsolationLevel::READ_UNCOMMITTED:
                $options['isolation'] = FBIRD_COMMITTED | FBIRD_REC_VERSION;
                break;
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

        if ($this->attrDcTransWait === -1) {
            $options['lock_resolution'] = FBIRD_WAIT;
        } elseif ($this->attrDcTransWait === 0) {
            $options['lock_resolution'] = FBIRD_NOWAIT;
        } else {
            $options['lock_resolution'] = FBIRD_WAIT;
            $options['lock_timeout']    = $this->attrDcTransWait;
        }

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        // (php-firebird v7.0.0-rc.6+). The @ operator only suppresses warnings, not exceptions.
        try {
            $result = fbird_trans_start($this->connection, $options);

            if (! is_resource($result)) {
                $this->checkLastApiCall();

                // If checking last API call didn't throw an exception but we don't have a resource, something is wrong
                throw new DriverException('Failed to create transaction');
            }

            return $result;
        } catch (\Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }
}
