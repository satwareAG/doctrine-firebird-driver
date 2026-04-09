<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQL\Parser;
use Firebird\Connection as FirebirdConnection;
use Firebird\Database;
use Firebird\DbInfo;
use Firebird\TBuilder;
use InvalidArgumentException;
use PDO;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use Throwable;
use UnexpectedValueException;

use function assert;
use function class_exists;
use function explode;
use function fbird_close;
use function fbird_commit;
use function fbird_connection_info;
use function fbird_drop_table_force;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_escape_string;
use function fbird_execute_auto;
use function fbird_fetch_row;
use function fbird_gen_id;
use function fbird_get_limbo_transactions;
use function fbird_kill_attachment;
use function fbird_last_insert_id;
use function fbird_list_table_blockers;
use function fbird_prepare_ex;
use function fbird_query_params_tx;
use function fbird_reconnect_transaction;
use function fbird_rollback;
use function fbird_set_exception_mode;
use function file_exists;
use function get_resource_id;
use function get_resource_type;
use function in_array;
use function is_array;
use function is_dir;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_match;
use function spl_object_id;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtoupper;
use function trim;
use function version_compare;

use const FBIRD_EXCEPTION_MODE_THROW;

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

    private string|null $connectionInsertColumn = null;

    private int|null $connectionInsertId = null;

    /** Caches the last resolved identity value across dotted-name and null lookups */
    private int|string|null $lastResolvedIdentityId = null;

    private readonly Parser $parser;

    private readonly TransactionManager $transactionManager;

    /**
     * Static registry tracking how many Connection objects reference each native resource.
     * Prevents premature fbird_close() when multiple DBAL layers share the same resource.
     *
     * @var array<int, int> Resource/object ID => reference count
     */
    private static array $resourceRegistry = [];

    /**
     * Load Firebird OO API classes if they are available as PHP files.
     * Some extension releases provide them in /usr/local/lib/php/Firebird.
     */
    private static bool $ooApiLoaded = false;

    /**
     * @param resource|\Firebird\Connection|null $connection
     * @param array<string, mixed>               $params
     *
     * @throws Exception
     */
    public function __construct(
        private $connection,
        private readonly string $serverVersion,
        protected bool $isPersistent,
        private readonly Exception|null $databaseNotFoundException,
        array $params,
    ) {
        self::loadOoApi();

        // Register this Connection object's reference to the native resource
        $this->registerResource();

        $this->parser             = new Parser(false);
        $this->transactionManager = new TransactionManager($this);

        if ($this->connection !== null) {
            // Enable Exception Mode (php-firebird v8.0.0+, guaranteed available)
            fbird_set_exception_mode(FBIRD_EXCEPTION_MODE_THROW);

            $this->transactionManager->beginTransaction();
            // Reset level and mode after initial transaction start
            $this->transactionManager->reset();
            $this->transactionManager->beginTransaction();
            $this->transactionManager->commit(); // Start standard auto-commit cycle
        }

        foreach ($params as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function __destruct()
    {
        // Save resource ID early for registry cleanup (before any nullification)
        $resourceId = $this->getResourceId();

        $connectionClosable = false;
        if ($this->isConnectionValid()) {
            // php-firebird v10.0.0+: Connection objects are always closable (non-persistent)
            if ($this->connection instanceof FirebirdConnection) {
                $connectionClosable = ! $this->isPersistent;
            } else {
                $conn = $this->getNativeConnection();
                assert(is_resource($conn));
                $type = get_resource_type($conn);
                if (in_array($type, self::RESOURCE_TYPES_CONNECTION, true)) {
                    $connectionClosable = true;
                } elseif (in_array($type, self::RESOURCE_TYPES_PERSISTENT_CONNECTION, true)) {
                    $connectionClosable = false;
                }
            }
        }

        // Fallback for "Unknown" resources during shutdown
        /** @psalm-suppress DocblockTypeContradiction */
        if (is_resource($this->connection) && get_resource_type($this->connection) === 'Unknown') {
            $this->connection = null;
        }

        if ($this->isConnectionValid() && $this->isTransactionValid()) {
            $firebirdActiveTransaction = $this->getActiveTransaction();
            if ($this->transactionManager->getLevel() > 0) {
                try {
                    fbird_commit($firebirdActiveTransaction);
                } catch (Throwable) {
                    try {
                        fbird_rollback($firebirdActiveTransaction);
                    } catch (Throwable) {
                    }
                }
            }

            $this->transactionManager->reset();
        }

        // Unregister this Connection's reference to the native resource
        self::unregisterResourceById($resourceId);

        // Only close the native resource if no other Connection objects reference it
        if ($connectionClosable) {
            $remainingRefs = $resourceId !== null
                ? (self::$resourceRegistry[$resourceId] ?? 0)
                : 0;

            if ($remainingRefs === 0) {
                /** @phpstan-ignore argument.type (v10.0.0+: fbird_close() accepts Connection objects) */
                fbird_close($this->connection);
            }
        }

        $this->connection = null;
    }

    /** @return resource|null */
    public function getActiveTransaction()
    {
        return $this->transactionManager->getActiveTransaction();
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
                $this->transactionManager->setIsolationLevel((int) $value);
                break;
            case FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT:
                $this->transactionManager->setWaitTimeout((int) $value);
                break;
            case FirebirdDriver::ATTR_AUTOCOMMIT:
                $this->transactionManager->setExecutionMode(
                    (bool) $value ? Enum\ExecutionMode::AUTO_COMMIT : Enum\ExecutionMode::MANUAL_COMMIT,
                );
                break;
        }
    }

    public function getAttribute(string|int $attribute): int|bool|null
    {
        return match ($attribute) {
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL
              => $this->transactionManager->getIsolationLevel(),
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT
              => $this->transactionManager->getWaitTimeout(),
            PDO::ATTR_AUTOCOMMIT
              => $this->transactionManager->getExecutionMode() === Enum\ExecutionMode::AUTO_COMMIT,
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
     * @throws Parser\Exception
     */
    #[Override]
    public function prepare(string $sql): DriverStatement
    {
        if ($this->connection === null && is_object($this->databaseNotFoundException)) {
            throw $this->databaseNotFoundException;
        }

        // Defensive check: validate connection and transaction are still valid
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
        if ($sql === '') {
            throw new DriverException('SQL statement is empty.');
        }

        try {
            /** @phpstan-ignore arguments.count */
            $stmt = fbird_prepare_ex($this->connection, $sql, $this->transactionManager->getActiveTransaction());
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
     */
    #[Override]
    public function lastInsertId($name = null): string|int|false
    {
        /** @psalm-suppress DocblockTypeContradiction */
        if ($name !== null && ! is_string($name)) {
            throw new InvalidArgumentException(sprintf('Argument $name in %s must be null or a string. Found: %s', __FUNCTION__, ValueFormatter::found($name)));
        }

        if ($name !== null && ! str_contains($name, '.')) {
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

        if (! $this->isConnectionValid()) {
            // Return cached value if connection is null/invalid (Unit Test path)
            return $this->connectionInsertId ?? false;
        }

        if ($name !== null && str_contains($name, '.')) {
            // Dotted names (e.g. '"ALBUM"."id"') come from getIdentitySequenceName() for native identity columns.

            $parts      = explode('.', str_replace('"', '', $name), 2);
            $tableName  = strtoupper($parts[0]);
            $columnName = strtoupper($parts[1] ?? '');

            // Step 1: Look up the internal identity generator name from RDB$RELATION_FIELDS.
            //         Use $this->query() (standard DBAL path) — transaction is valid at this point.
            $genName = null;
            if ($columnName !== '') {
                try {
                    $rdbResult = $this->query(sprintf(
                        'SELECT TRIM(RDB$GENERATOR_NAME) FROM RDB$RELATION_FIELDS'
                        . ' WHERE UPPER(TRIM(RDB$RELATION_NAME)) = \'%s\''
                        . ' AND UPPER(TRIM(RDB$FIELD_NAME)) = \'%s\''
                        . ' AND RDB$GENERATOR_NAME IS NOT NULL',
                        str_replace("'", "''", $tableName),
                        str_replace("'", "''", $columnName),
                    ));
                    $rdbRow    = $rdbResult->fetchNumeric();
                    if ($rdbRow !== false && isset($rdbRow[0]) && is_string($rdbRow[0])) {
                        $tmp = trim($rdbRow[0]);
                        if ($tmp !== '') {
                            $genName = $tmp;
                        }
                    }
                } catch (Throwable) {
                }
            }

            if ($genName !== null) {
                // Step 2a: fbird_gen_id() directly — fastest, no SQL parsing, no transaction overhead.
                try {
                    /** @phpstan-ignore argument.type */
                    $lastVal = fbird_gen_id($genName, 0, $this->connection);
                    if ($lastVal > 0) {
                        $this->lastResolvedIdentityId = $lastVal;

                        return $lastVal;
                    }
                } catch (Throwable) {
                }

                // Step 2b: SQL GEN_ID via autonomous transaction — bypasses active transaction context.
                //          php-firebird v10+ returns Firebird\Result objects; v7 returns resources.
                try {
                    $autoResult = $this->executeAuto(sprintf(
                        'SELECT GEN_ID("%s", 0) FROM RDB$DATABASE',
                        str_replace('"', '""', $genName),
                    ));
                    if (is_resource($autoResult) || is_object($autoResult)) {
                        $autoRow = fbird_fetch_row($autoResult);
                        if (is_array($autoRow) && isset($autoRow[0]) && is_numeric($autoRow[0])) {
                            $lastVal = (int) $autoRow[0];
                            if ($lastVal > 0) {
                                $this->lastResolvedIdentityId = $lastVal;

                                return $lastVal;
                            }
                        }
                    }
                } catch (Throwable) {
                }

                // Step 2c: SQL GEN_ID within current transaction — double-quoted identifier (Firebird 3.0+).
                try {
                    $genResult = $this->query(sprintf(
                        'SELECT GEN_ID("%s", 0) FROM RDB$DATABASE',
                        str_replace('"', '""', $genName),
                    ));
                    $genRow    = $genResult->fetchNumeric();
                    if ($genRow !== false && isset($genRow[0]) && is_numeric($genRow[0])) {
                        $lastVal = (int) $genRow[0];
                        if ($lastVal > 0) {
                            $this->lastResolvedIdentityId = $lastVal;

                            return $lastVal;
                        }
                    }
                } catch (Throwable) {
                }
            }

            // Step 3 (last resort): fbird_last_insert_id() without a generator name (Firebird 5.0+).
            //         On Firebird 4.0 and earlier with FBIRD_EXCEPTION_MODE_THROW, this throws a
            //         Firebird\Exception. Wrap in try/catch to handle gracefully.
            //         NOTE: Only reached if genName lookup failed (Step 1) or all GEN_ID steps failed.
            //         On FB 3.0/4.0, Steps 2a-2c succeed so this is never reached in normal operation.
            try {
                /** @phpstan-ignore argument.type */
                $id = @fbird_last_insert_id($this->connection);
                if ($id !== false && $id > 0) {
                    $this->lastResolvedIdentityId = $id;

                    return $id;
                }
            } catch (Throwable) {
                // The Firebird exception may have aborted the current transaction. Restart if needed.
                if (! $this->isTransactionValid()) {
                    try {
                        $this->transactionManager->beginTransaction();
                    } catch (Throwable) {
                    }
                }
            }

            return $this->connectionInsertId ?? false;
        }

        if ($name === null) {
            // Try fbird_last_insert_id() - works on Firebird 5.0+ natively.
            // Wrap in try/catch: on Firebird 4.0 and earlier with FBIRD_EXCEPTION_MODE_THROW,
            // this throws a Firebird\Exception rather than a PHP warning.
            try {
                /** @phpstan-ignore argument.type */
                $id = @fbird_last_insert_id($this->connection);
                if ($id !== false) {
                    return $id;
                }
            } catch (Throwable) {
                // fbird_last_insert_id() not supported without a generator name on this Firebird version.
            }

            // On Firebird 4.0 and earlier, fbird_last_insert_id() requires a generator name.
            // Return the value cached by the most recent dotted-name lookup. ORM calls
            // lastInsertId('TABLE.COL') during flush(), so this will be set for ORM-driven INSERTs.
            if ($this->lastResolvedIdentityId !== null) {
                return $this->lastResolvedIdentityId;
            }

            return $this->connectionInsertId ?? false;
        }

        // Delegate to fbird_gen_id() for named sequences/generators
        try {
            /** @phpstan-ignore argument.type */
            $lastVal = fbird_gen_id($name, 0, $this->connection);

            return $lastVal === 0 ? false : $lastVal;
        } catch (Throwable) {
            return false;
        }
    }

    public function setLastInsertId(int $id): void
    {
        $this->connectionInsertId = $id;
    }

    #[Override]
    public function beginTransaction(): bool
    {
        return $this->transactionManager->beginTransaction();
    }

    #[Override]
    public function commit(): bool
    {
        return $this->transactionManager->commit();
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started.
     *
     * @throws DriverException
     */
    public function autoCommit(): void
    {
        $this->transactionManager->autoCommit();
    }

    /**
     * {@inheritdoc)
     */
    #[Override]
    public function rollBack(): bool
    {
        return $this->transactionManager->rollBack();
    }

    /**
     * Create a new savepoint.
     *
     * @throws DriverException
     */
    public function createSavepoint(string $savepoint): void
    {
        $this->transactionManager->createSavepoint($savepoint);
    }

    /**
     * Release a savepoint.
     *
     * @throws DriverException
     */
    public function releaseSavepoint(string $savepoint): void
    {
        $this->transactionManager->releaseSavepoint($savepoint);
    }

    /**
     * Rollback to a savepoint.
     *
     * @throws DriverException
     */
    public function rollbackSavepoint(string $savepoint): void
    {
        $this->transactionManager->rollbackSavepoint($savepoint);
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
        if (is_resource($this->connection)) {
            return $this->connection;
        }

        if (is_object($this->connection) && method_exists($this->connection, 'getNativeConnection')) {
            /** @phpstan-ignore method.notFound */
            return $this->connection->getNativeConnection();
        }

        return null;
    }

    /**
     * Check if the connection resource or object is valid.
     *
     * Since php-firebird v10.0.0, fbird_connect()/fbird_pconnect() return
     * Firebird\Connection objects instead of resources. All fbird_* functions
     * accept both resources and Connection objects transparently.
     *
     * @return bool True if connection is a valid Firebird resource or Connection object
     *
     * @psalm-assert-if-true resource|\Firebird\Connection $this->connection
     * @phpstan-assert-if-true resource|\Firebird\Connection $this->connection
     */
    public function isConnectionValid(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        if (is_resource($this->connection)) {
            return $this->isResourceTypeValid($this->connection);
        }

        // php-firebird v10.0.0+: fbird_connect() returns Firebird\Connection objects
        if ($this->connection instanceof FirebirdConnection) {
            return $this->connection->isConnected();
        }

        // In DBAL 3.10+, getNativeConnection() might return an object that wraps the resource.
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
        return $this->transactionManager->isTransactionValid();
    }

    /**
     * List attachments blocking access to a table.
     *
     * @return array<int, array<string, mixed>>|false Array of blocker info or false on error
     */
    public function listTableBlockers(string $tableName): array|false
    {
        if (! $this->isConnectionValid()) {
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
     * @throws DriverException
     */
    public function killAttachment(int $attachmentId): bool
    {
        if (! $this->isConnectionValid()) {
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
     * @throws DriverException
     */
    public function dropTableForce(string $tableName): bool
    {
        if (! $this->isConnectionValid()) {
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
     * @param array<mixed>|null $params Optional array of bind parameters
     *
     * @return resource|int|false Result resource for SELECT, affected-row count for DML, or false on failure
     *
     * @throws DriverException
     */
    public function executeAuto(string $sql, array|null $params = null): mixed
    {
        if (! $this->isConnectionValid()) {
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
        if ($transaction instanceof TransactionManager) {
            if (! $transaction->isTransactionValid()) {
                throw new DriverException('Invalid transaction resource.');
            }

            $transResource = $transaction->getResource();
        } else {
            $transResource = $transaction;

            /** @psalm-suppress DocblockTypeContradiction */
            if (! is_resource($transResource) || get_resource_type($transResource) !== 'Firebird transaction') {
                throw new DriverException('Invalid transaction resource.');
            }
        }

        try {
            return fbird_query_params_tx($this->connection, $transResource, $sql, $params);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    /**
     * Create a new independent transaction using the TBuilder fluent API.
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
     * @param string                  $sql         INSERT statement with placeholders
     * @param TransactionManager|null $transaction Optional transaction (uses active if null)
     *
     * @return ProceduralBatch Batch object for adding rows and executing
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function createBatch(string $sql, TransactionManager|null $transaction = null): ProceduralBatch
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
        if ($transaction instanceof TransactionManager) {
            if (! $transaction->isTransactionValid()) {
                throw new DriverException('No valid transaction available for batch operation.');
            }

            $transResource = $transaction->getResource();
        } else {
            $transResource = $this->transactionManager->getActiveTransaction();

            if (! is_resource($transResource)) {
                throw new DriverException('No valid transaction available for batch operation.');
            }
        }

        // Use procedural fbird_batch_* API via ProceduralBatch wrapper.
        // The OO Firebird\Batch class has a private constructor and Batch::fromQuery()
        // fails with "invalid batch handle" in php-firebird v10.3.9.

        /** @phpstan-ignore argument.type (connection validated above; transResource null-checked above) */
        return new ProceduralBatch($this->connection, $sql, $transResource);
    }

    /**
     * Execute a batch INSERT with data array (convenience method).
     *
     * @param string                               $sql         INSERT statement with placeholders
     * @param array<int, array<int|string, mixed>> $rows        Array of row data arrays
     * @param TransactionManager|null              $transaction Optional transaction
     *
     * @return ProceduralBatchResult Result with row counts and any errors
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function executeBatch(string $sql, array $rows, TransactionManager|null $transaction = null): ProceduralBatchResult
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

        try {
            return fbird_reconnect_transaction($this->connection, $transactionId);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }
    }

    // =========================================================================
    // Resource Reference Registry - prevents premature fbird_close()
    // =========================================================================

    /**
     * Get the reference count for a native resource (for testing/debugging).
     *
     * @param resource|object $resource Native connection resource or object
     */
    public static function getResourceRefCount(mixed $resource): int
    {
        if (is_resource($resource)) {
            $id = get_resource_id($resource);
        } elseif (is_object($resource)) {
            $id = spl_object_id($resource);
        } else {
            return 0;
        }

        return self::$resourceRegistry[$id] ?? 0;
    }

    /**
     * Get the unique ID for the current native connection resource/object.
     */
    private function getResourceId(): int|null
    {
        if ($this->connection === null) {
            return null;
        }

        if (is_resource($this->connection)) {
            return get_resource_id($this->connection);
        }

        if (is_object($this->connection)) {
            return spl_object_id($this->connection);
        }

        return null;
    }

    /**
     * Register this Connection object's reference to the native resource.
     */
    private function registerResource(): void
    {
        $id = $this->getResourceId();
        if ($id === null) {
            return;
        }

        self::$resourceRegistry[$id] = (self::$resourceRegistry[$id] ?? 0) + 1;
    }

    /** @param resource $resource */
    private function isResourceTypeValid($resource): bool
    {
        $type = get_resource_type($resource);

        return in_array($type, self::RESOURCE_TYPES_CONNECTION, true)
            || in_array($type, self::RESOURCE_TYPES_PERSISTENT_CONNECTION, true);
    }

    /**
     * Unregister a resource reference by its ID.
     */
    private static function unregisterResourceById(int|null $resourceId): void
    {
        if ($resourceId === null || ! isset(self::$resourceRegistry[$resourceId])) {
            return;
        }

        self::$resourceRegistry[$resourceId]--;
        if (self::$resourceRegistry[$resourceId] > 0) {
            return;
        }

        unset(self::$resourceRegistry[$resourceId]);
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
            'TransactionManager.php',
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
