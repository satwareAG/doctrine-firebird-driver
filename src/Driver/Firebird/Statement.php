<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use Firebird\ResultSet;
use Firebird\Transaction;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Throwable;

use function array_flip;
use function array_map;
use function array_unshift;
use function assert;
use function fbird_affected_rows;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_execute;
use function fbird_free_query;
use function func_num_args;
use function is_int;
use function ksort;
use function preg_match;
use function sprintf;
use function strtoupper;
use function trim;

/**
 * Based on:
 *   - https://github.com/helicon-os/doctrine-dbal
 *   - https://github.com/doctrine/dbal/blob/2.6/lib/Doctrine/DBAL/Driver/SQLSrv/SQLSrvStatement.php
 */
final class Statement implements StatementInterface
{
    /** @var array<int, mixed> */
    private array $queryParamBindings = [];

    /**
     * Zero-Based List of parameter binding types
     *
     * @var array<int, mixed>
     */
    private array $queryParamTypes = [];

    /** @var array<int|string, mixed> */
    private array $boundValues = [];

    private Result|null $currentResult = null;

    /** @var bool True if this statement is a DML operation (INSERT/UPDATE/DELETE/MERGE) */
    private bool $isDml = false;

    /** @var bool True if this is specifically an INSERT statement */
    private bool $isInsert = false;

    private readonly BlobHandler $blobHandler;

    /**
     * @param \Firebird\Statement|Transaction|false|null $statement
     * @param array<int|string>                          $parameterMap
     * @param string                                     $sql          The SQL statement for DML detection
     *
     * @throws Exception
     */
    // phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- accepts resource|object for dual-accept bridge
    public function __construct(protected Connection $connection, private $statement = null, private mixed $parameterMap = [], private string $sql = '')
    {
        $this->blobHandler = new BlobHandler();

        if ($this->statement !== null) {
            // Determine if this is a DML statement by examining the SQL
            $this->isDml = $this->detectDmlStatement($sql);
            // Specifically check if it's an INSERT
            $this->isInsert = $this->detectInsertStatement($sql);
        }

        if ($this->isStatementValid()) {
            return;
        }

        $this->connection->checkLastApiCall();
    }

    public function __destruct()
    {
        // Only free Statement handles; Transaction handles are managed by TransactionManager.
        if (! $this->statement instanceof \Firebird\Statement) {
            return;
        }

        // Destructors must not throw — wrap cleanup in try-catch
        try {
            fbird_free_query($this->statement);
        } catch (Throwable) {
        }

        $this->statement = null;
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    #[Override]
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindValue() is deprecated.'
                . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        $this->boundValues[$param] = $value;

        return $this->bindValueInternal($param, $this->boundValues[$param], $type);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use bindValue() instead.
     */
    #[Override]
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5563',
            '%s is deprecated. Use bindValue() instead.',
            __METHOD__,
        );

        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindParam() is deprecated.'
                . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        $this->boundValues[$param] = &$variable;

        return $this->bindValueInternal($param, $variable, $type);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function execute($params = null): ResultInterface
    {
        // Defense-in-depth: with php-firebird v12.0.0+ (#305 fixed),
        // fbird_prepare_ex failures throw under THROW mode, making this path
        // unreachable in THROW mode. Kept for SILENT mode users and as a
        // safety net against unexpected null returns.
        if (! $this->isStatementValid()) {
            throw new DriverException('Statement is not valid or has been closed.');
        }

        if ($this->currentResult !== null) {
            try {
                $this->currentResult->free();
            } catch (Throwable) {
                // Ignore if already freed or invalid
            }

            $this->currentResult = null;
        }

        // Check if statement is actually a transaction handle (used for implicit commits)
        if ($this->statement instanceof Transaction) {
            $fbirdResultRc = 1;
        } else {
            if ($params !== null) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/5556',
                    'Passing $params to Statement::execute() is deprecated. Bind parameters using'
                    . ' Statement::bindParam() or Statement::bindValue() instead.',
                );

                foreach ($params as $key => $val) {
                    if (is_int($key)) {
                        $this->bindValue($key + 1, $val, ParameterType::STRING);
                    } else {
                        $check = array_flip($this->parameterMap);
                        $this->bindValue($check[':' . $key] ?? 0, $val, ParameterType::STRING);
                    }
                }
            }

            // Execute statement
            foreach ($this->queryParamTypes as $param => $type) {
                if ($type !== ParameterType::LARGE_OBJECT) {
                    continue;
                }

                $this->queryParamBindings[$param] = $this->blobHandler->toInternalValue(
                    $this->queryParamBindings[$param],
                    $type,
                );
                $this->queryParamTypes[$param]    = ParameterType::STRING;
            }

            $callArgs = $this->queryParamBindings;
            // sort
            ksort($callArgs);
            // Dereference args to ensure values are passed
            $callArgs = array_map(static fn ($v): mixed => $v, $callArgs);
            array_unshift($callArgs, $this->statement);

            $conn = $this->connection->getNativeConnection();

            try {
                $fbirdResultRc = fbird_execute(...$callArgs);
            } catch (Throwable $e) {
                throw DriverException::fromThrowable($e);
            }

            // Result seems ok - is either #rows or result handle
            // As the fbird-api does not have an auto-commit-mode, autocommit is simulated by calling the
            // function autoCommit of the connection

            if (! $fbirdResultRc instanceof ResultSet) {
                // fbird_execute() returned boolean/integer (direct DML without prepared statement)
                if ($fbirdResultRc === true) {
                    // For DML operations that return true, get affected rows count
                    $postExecAffectedRows = $this->connection->isConnectionValid() ? fbird_affected_rows($conn) : 0;
                    if ($postExecAffectedRows > 0) {
                        $fbirdResultRc = $postExecAffectedRows;
                    } elseif ($this->isInsert) {
                        // INSERT operations that succeed should return 1 even if affected_rows says 0
                        $fbirdResultRc = 1;
                    } else {
                        // UPDATE/DELETE that matched 0 rows
                        $fbirdResultRc = 0;
                    }
                }

                $this->connection->autoCommit();
            } else {
                // fbird_execute() returned resource - this happens for prepared statements
                // For prepared DML with bound parameters, affected_rows is unreliable (returns 0)
                // We use SQL-based detection (isDml flag) because fbird_num_fields is unreliable
                // (it can return non-zero even for DML statements in some Firebird versions)

                if ($this->isDml) {
                    // This is a DML statement (INSERT/UPDATE/DELETE/MERGE/EXECUTE)

                    // Get affected rows BEFORE commit - fbird_affected_rows returns count
                    // for the last DML operation in the current transaction
                    $preCommitAffectedRows = $this->connection->isConnectionValid() ? fbird_affected_rows($conn) : 0;

                    // Commit the transaction
                    $this->connection->autoCommit();

                    // Use the pre-commit value as our result
                    // fbird_affected_rows() returns the count for the most recent DML operation
                    if ($preCommitAffectedRows > 0) {
                        // Got a positive count = actual affected row count
                        $fbirdResultRc = $preCommitAffectedRows;
                    } elseif ($this->isInsert) {
                        // INSERT succeeded but extension returned 0 - default to 1
                        $fbirdResultRc = 1;
                    } else {
                        // UPDATE/DELETE with 0 or negative value - assume 0 rows matched
                        $fbirdResultRc = 0;
                    }
                }

                // else: This is a SELECT query - keep the resource for fetching
                // No auto-commit needed for SELECT (doesn't make changes)
            }
        }

        // Cache the INSERT table name so that lastInsertId(null) can resolve the IDENTITY
        // generator on Firebird 3.0/4.0 (where fbird_last_insert_id() requires a generator name).
        if (
            $this->isInsert && preg_match(
                '/INSERT\s+INTO\s+"?([A-Za-z_\x80-\xFF][A-Za-z0-9_$\x80-\xFF]*)"?\s*[(\s]/i',
                $this->sql,
                $m,
            ) === 1
        ) {
            $this->connection->setLastInsertTable(strtoupper(trim($m[1], '"')));
        }

        if ($fbirdResultRc === false) {
            throw new DriverException(
                (string) fbird_errmsg(),
                null,
                (int) fbird_errcode(),
            );
        }

        $this->currentResult = new Result($fbirdResultRc, $this->connection, $this);

        return $this->currentResult;
    }

    /**
     * Drop the retained reference to $result (called by Result::free(), #176).
     *
     * Statement and Result reference each other; without this notification a
     * consumed Result stays alive (cursor open, transaction locks retained)
     * until cycle-GC, defeating the extension's idle-lock release.
     *
     * @internal Only ever called by {@see Result::free()}
     */
    public function clearCurrentResult(Result $result): void
    {
        if ($this->currentResult !== $result) {
            return;
        }

        $this->currentResult = null;
    }

    /**
     * Check if the statement handle is valid.
     *
     * php-firebird v11.1.0+: fbird_prepare()/fbird_prepare_ex() return
     * Firebird\Statement objects (M3 migration complete, issue #297).
     * Transaction handles (Firebird\Transaction) are also valid for implicit commits.
     *
     * @psalm-assert-if-true \Firebird\Statement|Transaction $this->statement
     * @phpstan-assert-if-true \Firebird\Statement|Transaction $this->statement
     */
    public function isStatementValid(): bool
    {
        return $this->statement instanceof \Firebird\Statement
            || $this->statement instanceof Transaction;
    }

    /**
     * Internal method to bind a value to a parameter.
     * This contains the core binding logic used by both bindValue() and bindParam().
     */
    private function bindValueInternal(int|string $param, mixed &$variable, int $type): bool
    {
        // Break references to ensure re-binding works correctly
        if (isset($this->queryParamBindings[$param])) {
            unset($this->queryParamBindings[$param]);
        }

        if (is_int($param)) {
            if (! isset($this->parameterMap[$param])) {
                throw new DriverException(sprintf('Positional Parameter %d not found in the parameter map', $param));
            }
        } else {
            $params = array_flip($this->parameterMap);
            if (! isset($params[$param])) {
                throw new DriverException(sprintf('Named Parameter %s not found in the parameter map', $param));
            }

            $param = $params[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            $variable = $this->blobHandler->toInternalValue($variable, $type);
            $type     = ParameterType::STRING;
        }

        assert(is_int($param));
        $this->queryParamBindings[$param] = &$variable;
        $this->queryParamTypes[$param]    = $type;

        return true;
    }

    /**
     * Detect if the given SQL is a DML statement (INSERT/UPDATE/DELETE/MERGE).
     * SELECT statements and DDL are not considered DML.
     */
    private function detectDmlStatement(string $sql): bool
    {
        // Match DML and DDL keywords directly, skipping optional block comments and WITH clauses.
        // We include DDL (CREATE|ALTER|DROP) because Firebird metadata changes must be
        // committed to release system table locks and be visible to subsequent operations.
        return (bool) preg_match(
            '/^\s*(?:\/\*.*?\*\/\s*)*(?:WITH\s+.*?\s+)?(INSERT|UPDATE|DELETE|MERGE|EXECUTE|CREATE|ALTER|DROP|RECREATE)\b/is',
            trim($sql),
        );
    }

    /**
     * Detect if the SQL is specifically an INSERT statement.
     */
    private function detectInsertStatement(string $sql): bool
    {
        // Match INSERT keyword, skipping optional block comments and WITH clauses
        return (bool) preg_match(
            '/^\s*(?:\/\*.*?\*\/\s*)*(?:WITH\s+.*?\s+)?INSERT\b/is',
            trim($sql),
        );
    }
}
