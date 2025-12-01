<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use RuntimeException;

use function array_flip;
use function array_map;
use function array_unshift;
use function assert;
use function count;
use function fbird_affected_rows;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_execute;
use function fbird_fetch_assoc;
use function fbird_free_query;
use function fclose;
use function func_num_args;
use function get_resource_type;
use function is_array;
use function is_int;
use function is_numeric;
use function is_resource;
use function ksort;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strcasecmp;
use function stream_get_contents;
use function strtoupper;
use function trim;

/**
 * Based on:
 *   - https://github.com/helicon-os/doctrine-dbal
 *   - https://github.com/doctrine/dbal/blob/2.6/lib/Doctrine/DBAL/Driver/SQLSrv/SQLSrvStatement.php
 */
class Statement implements StatementInterface
{
    /** @var array<int, mixed> */
    protected array $queryParamBindings = [];

    /**
     * Zero-Based List of parameter binding types
     *
     * @var array<int, mixed>
     */
    protected array $queryParamTypes = [];

    /** @var array<int|string, mixed> */
    protected array $boundValues = [];

    private Result|null $currentResult = null;

    /** @var bool True if this statement is a DML operation (INSERT/UPDATE/DELETE/MERGE) */
    private bool $isDml = false;

    /** @var bool True if this is specifically an INSERT statement */
    private bool $isInsert = false;

    /** @var bool True if this statement has a RETURNING clause */
    private bool $hasReturning = false;

    /**
     * @param resource|false|null $statement
     * @param array<int|string>   $parameterMap
     * @param string              $sql          The SQL statement for DML detection
     *
     * @throws Exception
     */
    public function __construct(protected Connection $connection, protected $statement, private mixed $parameterMap = [], string $sql = '')
    {
        if (is_resource($statement)) {
            // Determine if this is a DML statement by examining the SQL
            $this->isDml = $this->detectDmlStatement($sql);
            // Specifically check if it's an INSERT
            $this->isInsert = $this->detectInsertStatement($sql);
            // Check if this statement has a RETURNING clause
            $this->hasReturning = $this->detectReturningClause($sql);

            return;
        }

        $this->connection->checkLastApiCall();
    }

    public function __destruct()
    {
        if (! is_resource($this->statement)) {
            return;
        }

        $statementType = get_resource_type($this->statement);
        if ($statementType === 'Firebird/InterBase transaction') {
            return;
        }

        if ($statementType === 'interbase query' || $statementType === 'Firebird/InterBase query') {
            fbird_free_query($this->statement);
            unset($this->statement);
        }

        $this->statement = null;
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-suppress PossiblyUnusedReturnValue
     */
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

        return $this->bindParam($param, $this->boundValues[$param], $type, null);
    }

    /**
     * {@inheritDoc}
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5563',
            '%s is deprecated. Use bindValue() instead.',
            __METHOD__,
        );

        // Break references to ensure re-binding works correctly
        if (isset($this->queryParamBindings[$param])) {
            unset($this->queryParamBindings[$param]);
        }

        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindParam() is deprecated.'
                . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        if (is_int($param)) {
            if (! isset($this->parameterMap[$param])) {
                throw new Exception(sprintf('Positional Parameter %d not found in the parameter map', $param));
            }
        } else {
            $params = array_flip($this->parameterMap);
            if (! isset($params[$param])) {
                throw new Exception(sprintf('Named Parameter %s not found in the parameter map', $param));
            }

            $param = $params[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            if ($variable !== null && is_resource($variable)) {
                // Workaround for php-firebird 6.2.0+ segfault:
                // Read stream into memory and pass as string.
                // This avoids fbird_blob_create/add/close which seem to cause instability.
                $content = stream_get_contents($variable);
                if (is_resource($variable)) {
                    fclose($variable);
                }

                $variable = $content;
                $type     = ParameterType::STRING;
            }
        }

        assert(is_int($param));
        $this->queryParamBindings[$param] = &$variable;
        $this->queryParamTypes[$param]    = $type;

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     */
    public function execute($params = null): ResultInterface
    {
        assert(is_resource($this->statement));

        if ($this->currentResult !== null) {
            try {
                $this->currentResult->free();
            } catch (Exception) {
                // Ignore if already freed or invalid
            }

            $this->currentResult = null;
        }

        if (get_resource_type($this->statement) === 'Firebird/InterBase transaction') {
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
                        $this->bindValue($check[':' . $key] ?? 0, ParameterType::STRING);
                    }
                }
            }

            // Execute statement
            foreach ($this->queryParamTypes as $param => $type) {
                if ($type !== ParameterType::LARGE_OBJECT) {
                    continue;
                }

                $variable = $this->queryParamBindings[$param];
                if ($variable === null || ! is_resource($variable)) {
                    continue;
                }

                // Workaround for php-firebird 6.2.0+ segfault:
                // Read stream into memory and pass as string.
                $content = stream_get_contents($variable);
                if (is_resource($variable)) {
                    fclose($variable);
                }

                $this->queryParamBindings[$param] = $content;
                $this->queryParamTypes[$param]    = ParameterType::STRING;
            }

            $callArgs = $this->queryParamBindings;
            // sort
            ksort($callArgs);
            // Dereference args to ensure values are passed
            $callArgs = array_map(static fn ($v): mixed => $v, $callArgs);
            array_unshift($callArgs, $this->statement);

            $conn = $this->connection->getNativeConnection();

            // Suppress warning since we properly check return value and throw exception
            // PHP Firebird extension 6.2.0 may emit warnings during cleanup operations
            $fbirdResultRc = @fbird_execute(...$callArgs);

            if ($fbirdResultRc === false) {
                // fbird_execute returns false on failure and emits a warning or sets error info
                $this->connection->checkLastApiCall();

                // If checkLastApiCall didn't throw, report generic failure
                throw new Exception(sprintf(
                    'fbird_execute returned false without error info. Error code: %s, Message: %s',
                    (string) fbird_errcode(),
                    (string) fbird_errmsg(),
                ));
            }

            // Result seems ok - is either #rows or result handle
            // As the fbird-api does not have an auto-commit-mode, autocommit is simulated by calling the
            // function autoCommit of the connection

            if (! is_resource($fbirdResultRc)) {
                // fbird_execute() returned boolean/integer (direct DML without prepared statement)
                if ($fbirdResultRc === true) {
                    // For DML operations that return true, get affected rows count
                    $postExecAffectedRows = is_resource($conn) ? fbird_affected_rows($conn) : 0;
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

                    if ($this->hasReturning) {
                        // DML with RETURNING clause - fetch the returned values
                        // This is used by ConnectionWrapper to get identity column values
                        $returnedRow = @fbird_fetch_assoc($fbirdResultRc);

                        if ($returnedRow !== false && is_array($returnedRow)) {
                            // Look for identity column value in returned row
                            // ConnectionWrapper sets connectionInsertColumn when RETURNING is added
                            $identityColumn = $this->connection->getConnectionInsertColumn();

                            // Try to find the identity value in the returned row
                            foreach ($returnedRow as $key => $value) {
                                // ConnectionWrapper uses alias format: ID<hash>.<hash>
                                // Also check for the actual column name
                                if (
                                    $identityColumn !== null && (
                                    strcasecmp($key, $identityColumn) === 0 ||
                                    str_starts_with(strtoupper($key), 'ID')
                                    )
                                ) {
                                    if (is_numeric($value)) {
                                        $this->connection->setLastInsertId((int) $value);
                                        break;
                                    }
                                }

                                // Fallback: if only one numeric value returned, assume it's the ID
                                if (! is_numeric($value) || count($returnedRow) !== 1) {
                                    continue;
                                }

                                $this->connection->setLastInsertId((int) $value);
                            }
                        }
                    }

                    // Get affected rows BEFORE commit - fbird_affected_rows returns count
                    // for the last DML operation in the current transaction
                    $preCommitAffectedRows = is_resource($conn) ? fbird_affected_rows($conn) : 0;

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

        $this->currentResult = new Result($fbirdResultRc, $this->connection, $this);

        return $this->currentResult;
    }

    /**
     * Detect if the given SQL is a DML statement (INSERT/UPDATE/DELETE/MERGE).
     * SELECT statements and DDL are not considered DML.
     */
    private function detectDmlStatement(string $sql): bool
    {
        // Match DML keywords directly, skipping optional block comments and WITH clauses
        return (bool) preg_match(
            '/^\s*(?:\/\*.*?\*\/\s*)*(?:WITH\s+.*?\s+)?(INSERT|UPDATE|DELETE|MERGE|EXECUTE)\b/is',
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

    /**
     * Detect if the SQL statement has a RETURNING clause.
     */
    private function detectReturningClause(string $sql): bool
    {
        // Check for RETURNING keyword (case-insensitive)
        return preg_match('/\bRETURNING\b/i', $sql) === 1;
    }
}
