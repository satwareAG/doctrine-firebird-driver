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
use Firebird\TransactionManager;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use Throwable;
use UnexpectedValueException;

use function assert;
use function class_exists;
use function fbird_close;
use function fbird_commit;
use function fbird_commit_ret;
use function fbird_connection_info;
use function fbird_drop_table_force;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_escape_string;
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
use function fbird_set_exception_mode;
use function fbird_trans_start;
use function file_exists;
use function get_resource_type;
use function in_array;
use function is_dir;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function version_compare;

use const FBIRD_COMMITTED;
use const FBIRD_CONCURRENCY;
use const FBIRD_CONSISTENCY;
use const FBIRD_EXCEPTION_MODE_THROW;
use const FBIRD_NOWAIT;
use const FBIRD_REC_VERSION;
use const FBIRD_WAIT;
use const FBIRD_WRITE;

/**
 * Based on https://github.com/helicon-os/doctrine-dbal
 * and Doctrine\DBAL\Driver\OCI8\Connection
 */
final class Connection implements ServerInfoAwareConnection // @phpstan-ignore-line classImplements.deprecated
{
    /**
     * Valid resource types for Firebird connection.
     * php-firebird v7.0.0+ resource type strings only.
     */
    private const RESOURCE_TYPES_CONNECTION = ['Firebird link'];

    /**
     * Valid resource types for Firebird persistent connection.
     * php-firebird v7.0.0+ resource type strings only.
     */
    private const RESOURCE_TYPES_PERSISTENT_CONNECTION = ['Firebird persistent link'];

    /**
     * Valid resource types for Firebird transaction.
     * php-firebird v7.0.0+ resource type strings only.
     */
    private const RESOURCE_TYPES_TRANSACTION = ['Firebird transaction'];

    /**
     * Firebird error code for "invalid transaction handle".
     * This occurs when trying to use a transaction that was already closed or invalidated.
     */
    private const ER_INVALID_TRANSACTION_HANDLE = 335544332;

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
    private mixed $firebirdActiveTransaction = null;

    /**
     * Load Firebird OO API classes if they are available as PHP files.
     * Some extension releases provide them in /usr/local/lib/php/Firebird.
     */
    private static bool $ooApiLoaded = false;

    /**
     * @param resource|null        $connection
     * @param array<string, mixed> $params
     *
     * @throws Exception
     */
    public function __construct(private $connection, private readonly string $serverVersion, protected bool $isPersistent, private readonly Exception|null $databaseNotFoundException, array $params)
    {
        self::loadOoApi();

        $this->parser        = new Parser(false);
        $this->executionMode = new ExecutionMode();

        if ($connection !== null) {
            // Enable Exception Mode (php-firebird v8.0.0+, guaranteed available)
            // This provides PDO::ERRMODE_EXCEPTION-like behavior where Firebird API
            // functions throw Firebird\Exception instead of returning false on errors.
            // Note: This is a GLOBAL setting affecting all Firebird operations in this process.
            // We enable it AFTER connection is established to avoid interfering with database creation.
            fbird_set_exception_mode(FBIRD_EXCEPTION_MODE_THROW);

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
            if (in_array($type, self::RESOURCE_TYPES_TRANSACTION, true) && $this->fbirdTransactionLevel > 0) {
                // Only attempt commit/rollback if there is an explicit transaction
                // (level > 0). In auto-commit mode (level === 0), the transaction is
                // implicit and fbird_commit()/fbird_rollback() triggers
                // "invalid transaction handle (expecting explicit transaction start)".
                try {
                    fbird_commit($this->firebirdActiveTransaction);
                } catch (Throwable) {
                    try {
                        fbird_rollback($this->firebirdActiveTransaction);
                    } catch (Throwable) {
                    }
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

        try {
            /** @phpstan-ignore arguments.count */
            $stmt = fbird_prepare($this->connection, $this->firebirdActiveTransaction, $sql);
        } catch (Throwable $e) {
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

        return "'" . fbird_escape_string((string) $value) . "'";
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
            if ($this->isTransactionValid()) {
                try {
                    fbird_commit($this->firebirdActiveTransaction);
                } catch (Throwable $e) {
                    // Try rollback to clear state, then convert exception.
                    // Ignore "invalid transaction handle" errors - the transaction is already gone.
                    try {
                        fbird_rollback($this->firebirdActiveTransaction);
                    } catch (Throwable) {
                    }

                    if (! $this->isInvalidTransactionHandle((int) $e->getCode(), $e->getMessage())) {
                        throw DriverException::fromThrowable($e);
                    }
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
            if (! $this->isTransactionValid()) {
                throw new RuntimeException('No active transaction resource.');
            }

            try {
                fbird_commit($this->firebirdActiveTransaction);
            } catch (Throwable $e) {
                try {
                    fbird_rollback($this->firebirdActiveTransaction);
                } catch (Throwable) {
                }

                if (! $this->isInvalidTransactionHandle((int) $e->getCode(), $e->getMessage())) {
                    throw DriverException::fromThrowable($e);
                }
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

        try {
            fbird_commit_ret($this->firebirdActiveTransaction);
        } catch (Throwable $e) {
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

            $rollbackException = null;
            try {
                fbird_rollback($this->firebirdActiveTransaction);
            } catch (Throwable $e) {
                if (! $this->isInvalidTransactionHandle((int) $e->getCode(), $e->getMessage())) {
                    $rollbackException = $e;
                }
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

            if ($rollbackException !== null) {
                throw DriverException::fromThrowable($rollbackException);
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

        try {
            fbird_savepoint($this->firebirdActiveTransaction, $savepoint);
        } catch (Throwable $e) {
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

        try {
            fbird_release_savepoint($this->firebirdActiveTransaction, $savepoint);
        } catch (Throwable $e) {
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

        try {
            fbird_rollback_savepoint($this->firebirdActiveTransaction, $savepoint);
        } catch (Throwable $e) {
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
        if ($this->connection === null) {
            return false;
        }

        if (is_resource($this->connection)) {
            return $this->isResourceTypeValid($this->connection);
        }

        // In DBAL 3.10+, getNativeConnection() might return an object that wraps the resource.
        // If we are called on such an object, we need to unwrap it.
        if (is_object($this->connection) && method_exists($this->connection, 'getNativeConnection')) {
            $native = $this->connection->getNativeConnection();

            return is_resource($native) && $this->isResourceTypeValid($native);
        }

        return false;
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

        try {
            return fbird_list_table_blockers($this->connection, $tableName);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
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

        try {
            return fbird_kill_attachment($this->connection, $attachmentId);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
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

        try {
            return fbird_drop_table_force($this->connection, $tableName);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
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
     * @return resource|int|false Result resource for SELECT, affected-row count for DML, or false on failure
     *
     * @throws DriverException
     */
    public function executeAuto(string $sql, array|null $params = null): mixed
    {
        if (! is_resource($this->connection)) {
            throw new DriverException('No active connection.');
        }

        try {
            return fbird_execute_auto($this->connection, $sql, $params ?? []);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
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
     * @param resource|TransactionManager $transaction Transaction resource or OO wrapper
     * @param string                      $sql         SQL statement to execute
     * @param array<int|string,mixed>     $params      Optional bind parameters
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
        $transResource = $transaction instanceof TransactionManager
            ? $transaction->getResource()
            : $transaction;

        if (! is_resource($transResource)) {
            throw new DriverException('Invalid transaction resource.');
        }

        // isConnectionValid() above guarantees $this->connection is a valid resource.
        assert(is_resource($this->connection));
        try {
            return fbird_query_params_tx($this->connection, $transResource, $sql, $params);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
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
     * @param string                  $sql         INSERT statement with placeholders
     * @param TransactionManager|null $transaction Optional transaction (uses active if null)
     *
     * @return Batch Batch object for adding rows and executing
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function createBatch(string $sql, TransactionManager|null $transaction = null): Batch
    {
        if (! $this->isConnectionValid()) {
            throw new DriverException('Connection is not valid or has been closed.');
        }

        // IBatch API requires Firebird 4.0+
        $version = $this->serverVersion;
        if (preg_match('/(\d+\.\d+)/', $version, $matches) === 1) {
            $version = $matches[1];
        }

        if (version_compare($version, '4.0', '<')) {
            throw new DriverException(sprintf(
                'IBatch API requires Firebird 4.0 or later. Current version: %s. ' .
                'Use traditional INSERT loops for Firebird 2.5/3.0.',
                $this->serverVersion,
            ));
        }

        // Use provided transaction or fall back to active transaction
        $transResource = $transaction instanceof TransactionManager
            ? $transaction->getResource()
            : $this->firebirdActiveTransaction;

        if (! is_resource($transResource)) {
            throw new DriverException('No valid transaction available for batch operation.');
        }

        // Check for static factory method (php-firebird v7.2.0+)
        if (method_exists(Batch::class, 'fromQuery')) {
            try {
                $query = fbird_prepare($this->connection, $transResource, $sql);
            } catch (Throwable $e) {
                throw DriverException::fromThrowable($e);
            }

            return Batch::fromQuery($query);
        }

        /** @phpstan-ignore-next-line */
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
     * @param TransactionManager|null              $transaction Optional transaction
     *
     * @return BatchResult Result with row counts and any errors
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function executeBatch(string $sql, array $rows, TransactionManager|null $transaction = null): BatchResult
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

        if (class_exists(DbInfo::class)) {
            return DbInfo::fromConnection($this->connection);
        }

        return fbird_connection_info($this->connection);
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

        try {
            return fbird_get_limbo_transactions($this->connection);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
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

        assert(is_resource($this->connection));

        try {
            return fbird_reconnect_transaction($this->connection, $transactionId);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    /** @param resource $resource */
    private function isResourceTypeValid($resource): bool
    {
        $type = get_resource_type($resource);

        return in_array($type, self::RESOURCE_TYPES_CONNECTION, true)
            || in_array($type, self::RESOURCE_TYPES_PERSISTENT_CONNECTION, true);
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

        try {
            /** @phpstan-ignore argument.type */
            return fbird_trans_start($this->connection, $options);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    private static function loadOoApi(): void
    {
        if (self::$ooApiLoaded) {
            return;
        }

        self::$ooApiLoaded = true;

        // Try standard install path from Dockerfile and source build
        $paths = [
            '/usr/local/lib/php/Firebird',
            '/usr/lib/php/Firebird',
            '/tmp/php-firebird/src/Firebird', // Source build check
        ];

        $files = [
            'functions.php',
            'EventPollerInterface.php',
            'EventPoller.php',
            'PcntlEventPoller.php',
            'FiberEventPoller.php',
            'ProcessEventPoller.php',
            'BlobId.php',
            'DbInfo.php',
            'Transaction.php',
            'TBuilder.php',
            'Database.php',
            'BatchError.php',
            'BatchResult.php',
            'Batch.php',
        ];

        foreach ($paths as $path) {
            if (! file_exists($path) || ! is_dir($path)) {
                continue;
            }

            foreach ($files as $file) {
                $fullPath = $path . '/' . $file;
                if (! file_exists($fullPath) || is_dir($fullPath)) {
                    continue;
                }

                try {
                    /** @phpstan-ignore-next-line */
                    require_once $fullPath;
                } catch (Throwable) {
                    // Ignore load errors for OO API
                }
            }
        }
    }
}
