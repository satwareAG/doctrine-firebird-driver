<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\TransactionIsolationLevel;
use Firebird\Connection as FirebirdConnection;
use Firebird\Database;
use Firebird\DbInfo;
use Firebird\ResultSet as FirebirdResultSet;
use Firebird\TBuilder;
use Firebird\Transaction as FirebirdTransaction;
use Firebird\TransactionManager as FirebirdTransactionManager;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\SQL\Parser;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use Throwable;
use UnexpectedValueException;

use function array_key_exists;
use function class_exists;
use function explode;
use function fbird_close;
use function fbird_commit;
use function fbird_connection_info;
use function fbird_drop_table_force;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_escape_literal;
use function fbird_execute_auto;
use function fbird_fetch_row;
use function fbird_gen_id;
use function fbird_get_limbo_transactions;
use function fbird_kill_attachment;
use function fbird_last_insert_id;
use function fbird_list_table_blockers;
use function fbird_ping;
use function fbird_prepare_ex;
use function fbird_query_params_tx;
use function fbird_reconnect_transaction;
use function fbird_rollback;
use function fbird_set_exception_mode;
use function file_exists;
use function is_array;
use function is_dir;
use function is_numeric;
use function is_object;
use function is_string;
use function preg_match;
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
 *
 * @psalm-suppress DeprecatedInterface
 * @psalm-suppress TooFewArguments
 * @psalm-suppress PossiblyNullArgument
 * @psalm-suppress RedundantConditionGivenDocblockType
 */
final class Connection implements \Doctrine\DBAL\Driver\Connection
{
    private string|null $connectionInsertColumn = null;

    private int|null $connectionInsertId = null;

    /** Caches the last resolved identity value across dotted-name and null lookups */
    private int|string|null $lastResolvedIdentityId = null;

    /** Stores the table name of the most recent INSERT for lastInsertId(null) IDENTITY lookup */
    private string|null $lastInsertTable = null;

    /**
     * Per-connection cache: table name (upper) => generator name (or null if none found).
     * Avoids repeated RDB$RELATION_FIELDS queries for the same table.
     *
     * @var array<string, string|null>
     */
    private array $identityGeneratorCache = [];

    private readonly Parser $parser;

    private readonly TransactionManager $transactionManager;

    /**
     * Load Firebird OO API classes if they are available as PHP files.
     * Some extension releases provide them in /usr/local/lib/php/Firebird.
     */
    private static bool $ooApiLoaded = false;

    /**
     * @param array<string, mixed> $params
     *
     * @throws Exception
     */
    public function __construct(
        private mixed $connection,
        private readonly string $serverVersion,
        protected bool $isPersistent,
        private readonly Exception|null $databaseNotFoundException,
        array $params,
    ) {
        self::loadOoApi();

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
        // Guard against uninitialized typed property (PHP 8.4+ throws if
        // constructor hasn't completed, e.g. during global shutdown).
        if (! isset($this->connection)) {
            return;
        }

        if (! $this->isConnectionValid()) {
            return;
        }

        // Commit or rollback any pending transaction
        if ($this->isTransactionValid()) {
            $activeTransaction = $this->getActiveTransaction();
            if ($this->transactionManager->getLevel() > 0) {
                try {
                    fbird_commit($activeTransaction);
                } catch (Throwable) {
                    try {
                        fbird_rollback($activeTransaction);
                    } catch (Throwable) {
                    }
                }
            }

            $this->transactionManager->reset();
        }

        // Close non-persistent connections (persistent connections are managed by the extension)
        if (! $this->isPersistent) {
            try {
                fbird_close($this->connection);
            } catch (Throwable) {
                // Destructors must not throw
            }
        }

        $this->connection = null;
    }

    public function getActiveTransaction(): FirebirdTransaction|null
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
                $this->transactionManager->setIsolationLevel($value);
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

    public function getAttribute(string|int $attribute): TransactionIsolationLevel|int|bool|null
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

    #[Override]
    public function quote(string $value): string
    {
        // R9: Use fbird_escape_literal() (v13.0.0) which wraps in single
        // quotes and doubles internal quotes — same as manual quoting but
        // handled server-side for correctness.
        return fbird_escape_literal($value);
    }

    #[Override]
    public function exec(string $sql): int|string
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
    public function lastInsertId(): int|string
    {
        if (! $this->isConnectionValid()) {
            // Return cached value if connection is null/invalid (Unit Test path)
            return $this->connectionInsertId ?? 0;
        }

        // Firebird 5.0+: fbird_last_insert_id() works without a generator name.
        try {
            /** @phpstan-ignore argument.type */
            $id = @fbird_last_insert_id($this->connection);
            if ($id !== false && $id > 0) {
                return $id;
            }
        } catch (Throwable) {
            // Not supported on this FB version — fall through to table-based lookup.
        }

        // Firebird 3.0/4.0: look up the IDENTITY generator for the last INSERT table.
        // Statement::execute() caches the table name via setLastInsertTable() after every INSERT.
        if ($this->lastInsertTable !== null) {
            $genName = $this->resolveIdentityGenerator($this->lastInsertTable);
            if ($genName !== null) {
                try {
                    /** @phpstan-ignore argument.type */
                    $lastVal = fbird_gen_id($genName, 0, $this->connection);
                    if ($lastVal !== false && $lastVal > 0) {
                        $this->lastResolvedIdentityId = $lastVal;

                        return $lastVal;
                    }
                } catch (Throwable) {
                }
            }
        }

        // Fall back to the cached value from a prior dotted-name lookup.
        if ($this->lastResolvedIdentityId !== null) {
            return $this->lastResolvedIdentityId;
        }

        if ($this->connectionInsertId !== null) {
            return $this->connectionInsertId;
        }

        throw NoIdentityValue::new();
    }

    /**
     * Returns the last insert ID for a specific Firebird generator/sequence.
     *
     * This is a Firebird-specific extension that looks up the current value
     * of a named generator or identity column. Use this instead of lastInsertId()
     * when working with explicit Firebird sequences or when a dotted "table.column"
     * name from getIdentitySequenceName() is available.
     *
     * @param string $name Generator name (e.g. "MY_GENERATOR") or dotted table.column
     *                     (e.g. '"ALBUM"."id"') from DBAL3's getIdentitySequenceName().
     *
     * @return int|string|false The last insert ID, or false if not resolvable.
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function lastInsertIdBySequence(string $name): int|string|false
    {
        // Validate non-dotted names first — input validation before runtime checks.
        if (! str_contains($name, '.')) {
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
            return $this->connectionInsertId ?? false;
        }

        // Dotted names (e.g. '"ALBUM"."id"') come from getIdentitySequenceName() for native identity columns.
        if (str_contains($name, '.')) {
            $parts      = explode('.', str_replace('"', '', $name), 2);
            $tableName  = strtoupper($parts[0]);
            $columnName = strtoupper($parts[1] ?? '');

            // Step 1: Look up the internal identity generator name from RDB$RELATION_FIELDS.
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
                try {
                    $autoResult = $this->executeAuto(sprintf(
                        'SELECT GEN_ID("%s", 0) FROM RDB$DATABASE',
                        str_replace('"', '""', $genName),
                    ));
                    if ($autoResult !== false && $autoResult !== null) {
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

            // Last resort: fbird_last_insert_id() without name — Firebird 5.0+ only.
            try {
                /** @phpstan-ignore argument.type */
                $id = @fbird_last_insert_id($this->connection);
                if ($id !== false && $id > 0) {
                    $this->lastResolvedIdentityId = $id;

                    return $id;
                }
            } catch (Throwable) {
                if (! $this->isTransactionValid()) {
                    try {
                        $this->transactionManager->beginTransaction();
                    } catch (Throwable) {
                    }
                }
            }

            return $this->connectionInsertId ?? false;
        }

        // Named generator: delegate to fbird_gen_id().
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

    /**
     * Cache the table name of the most recently executed INSERT statement.
     * Called by Statement::execute() so that lastInsertId(null) can resolve
     * the IDENTITY generator on Firebird 3.0/4.0.
     */
    public function setLastInsertTable(string|null $table): void
    {
        $this->lastInsertTable = $table;
    }

    #[Override]
    public function beginTransaction(): void
    {
        $this->transactionManager->beginTransaction();
    }

    #[Override]
    public function commit(): void
    {
        $this->transactionManager->commit();
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
    public function rollBack(): void
    {
        $this->transactionManager->rollBack();
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

    /** @return object|resource */
    public function getNativeConnection(): mixed
    {
        // fbird_connect()/fbird_pconnect() return \Firebird\Connection objects
        // since php-firebird v11.0.0 (M3 opaque-object migration).
        if ($this->connection === null) {
            throw new RuntimeException('Native connection is not available.');
        }

        return $this->connection;
    }

    /**
     * Check if the connection handle is valid.
     *
     * php-firebird v11.0.0+: fbird_connect()/fbird_pconnect() return
     * Firebird\Connection objects (M3 opaque-object migration).
     * v11.1.0+: Complete M3 migration — all fbird_* functions return opaque objects.
     *
     * @psalm-assert-if-true FirebirdConnection $this->connection
     * @phpstan-assert-if-true FirebirdConnection $this->connection
     */
    public function isConnectionValid(): bool
    {
        if (! isset($this->connection)) {
            return false;
        }

        return $this->connection instanceof FirebirdConnection && $this->connection->isConnected();
    }

    /**
     * Lightweight network roundtrip to verify the connection is alive.
     *
     * Uses fbird_ping() (v13.0.0) which calls IAttachment::ping() —
     * a single lightweight roundtrip that doesn't require SQL parsing.
     *
     * @return bool True if the server responded to the ping
     */
    public function ping(): bool
    {
        if (! $this->isConnectionValid()) {
            return false;
        }

        return @fbird_ping($this->connection);
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
     * @return FirebirdResultSet|int|false Result set for SELECT, affected-row count for DML, or false on failure
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

        $transResource = $this->resolveTransactionResource($transaction);

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
     * @param string                                             $sql         INSERT statement with placeholders
     * @param TransactionManager|FirebirdTransactionManager|null $transaction Optional transaction (uses active if null)
     *
     * @return ProceduralBatch Batch object for adding rows and executing
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function createBatch(string $sql, TransactionManager|FirebirdTransactionManager|null $transaction = null): ProceduralBatch
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
        if ($transaction instanceof TransactionManager || $transaction instanceof FirebirdTransactionManager) {
            $transResource = $this->resolveTransactionResource($transaction);
        } else {
            $transResource = $this->transactionManager->getActiveTransaction();

            if ($transResource === null || $transResource === false) {
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
     * @param string                                             $sql         INSERT statement with placeholders
     * @param array<int, array<int|string, mixed>>               $rows        Array of row data arrays
     * @param TransactionManager|FirebirdTransactionManager|null $transaction Optional transaction
     *
     * @return ProceduralBatchResult Result with row counts and any errors
     *
     * @throws DriverException If Firebird version < 4.0 or connection invalid.
     */
    public function executeBatch(string $sql, array $rows, TransactionManager|FirebirdTransactionManager|null $transaction = null): ProceduralBatchResult
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
     * @return FirebirdTransaction|false Transaction object for commit/rollback, or false on error
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

    /**
     * Resolve a transaction argument to a Firebird\Transaction resource.
     *
     * Accepts the driver's TransactionManager, php-firebird's TransactionManager
     * (from TBuilder::start()), or a raw Firebird\Transaction.
     *
     * @param mixed $transaction Transaction to resolve
     *
     * @return mixed The resolved Firebird\Transaction
     *
     * @throws DriverException If the transaction is invalid or already closed.
     */
    private function resolveTransactionResource(mixed $transaction): mixed
    {
        if ($transaction instanceof TransactionManager) {
            if (! $transaction->isTransactionValid()) {
                throw new DriverException('Invalid transaction resource.');
            }

            return $transaction->getResource();
        }

        if ($transaction instanceof FirebirdTransactionManager) {
            // From TBuilder::start() — independent transaction (Firebird 4.0+).
            if (! $transaction->isActive()) {
                throw new DriverException('Transaction already committed or rolled back.');
            }

            return $transaction->getResource();
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if ($transaction === null || $transaction === false) {
            throw new DriverException('Invalid transaction handle.');
        }

        return $transaction;
    }

    // =========================================================================
    // Resource Reference Registry - prevents premature fbird_close()
    // =========================================================================

    /**
     * Look up the IDENTITY generator name for a given table from RDB$RELATION_FIELDS.
     * Returns null if no IDENTITY column is found or the lookup fails.
     *
     * Uses $this->query() (DBAL path) which runs within the active transaction.
     * This matches Doctrine best practice (Oracle OCI8, SQL Server drivers) where
     * metadata queries use the active transaction context for consistent reads.
     */
    private function resolveIdentityGenerator(string $tableName): string|null
    {
        $key = strtoupper($tableName);

        if (array_key_exists($key, $this->identityGeneratorCache)) {
            return $this->identityGeneratorCache[$key];
        }

        $result = null;

        try {
            // Use $this->query() (DBAL path) which runs within the active transaction.
            $sql       = sprintf(
                'SELECT FIRST 1 TRIM(RDB$GENERATOR_NAME) FROM RDB$RELATION_FIELDS'
                . ' WHERE UPPER(TRIM(RDB$RELATION_NAME)) = \'%s\''
                . ' AND RDB$GENERATOR_NAME IS NOT NULL',
                str_replace("'", "''", $key),
            );
            $rdbResult = $this->query($sql);
            $rdbRow    = $rdbResult->fetchNumeric();
            if (is_array($rdbRow) && isset($rdbRow[0]) && is_string($rdbRow[0])) {
                $tmp = trim($rdbRow[0]);
                if ($tmp !== '') {
                    $result = $tmp;
                }
            }
        } catch (Throwable) {
        }

        $this->identityGeneratorCache[$key] = $result;

        return $result;
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
