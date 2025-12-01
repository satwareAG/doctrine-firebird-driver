<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use RuntimeException;
use Throwable;

use function array_flip;
use function array_map;
use function array_unshift;
use function assert;
use function fbird_affected_rows;
use function fbird_blob_create;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_execute;
use function fbird_free_query;
use function fclose;
use function feof;
use function fread;
use function func_num_args;
use function get_resource_type;
use function is_int;
use function is_resource;
use function ksort;
use function preg_match;
use function sprintf;
use function strlen;
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
            return;
        }

        $this->connection->checkLastApiCall();
    }

    /**
     * Detect if the given SQL is a DML statement (INSERT/UPDATE/DELETE/MERGE).
     * SELECT statements and DDL are not considered DML.
     */
    private function detectDmlStatement(string $sql): bool
    {
        // Normalize: trim whitespace, handle common prefixes
        $sql = trim($sql);
        
        // Skip common statement prefixes (comments, WITH clause)
        // Extract the first significant keyword
        if (preg_match('/^\s*(?:\/\*.*?\*\/\s*)*(?:WITH\s+.*?\s+)?(SELECT|INSERT|UPDATE|DELETE|MERGE|EXECUTE)\b/is', $sql, $matches)) {
            $keyword = strtoupper($matches[1]);
            return in_array($keyword, ['INSERT', 'UPDATE', 'DELETE', 'MERGE', 'EXECUTE'], true);
        }
        
        // Fallback: check if starts with DML keywords
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|MERGE|EXECUTE)\b/i', $sql)) {
            return true;
        }
        
        return false;
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
            //
            // IMPORTANT: For prepared statements, fbird_execute() ALWAYS returns a resource handle,
            // even for DML operations (INSERT/UPDATE/DELETE). We must capture the affected row count
            // BEFORE autoCommit() since fbird_commit_ret() invalidates the counter.
            
            // Capture affected rows BEFORE any commit - this is critical!
            // Note: fbird_affected_rows() only accepts connection link, not transaction
            // IMPORTANT: fbird_affected_rows() returns 0 for prepared DML statements in Firebird.
            // This is a known limitation of the Firebird PHP extension with prepared statements.
            $conn = $this->connection->getNativeConnection();
            $affectedRows = is_resource($conn) ? fbird_affected_rows($conn) : 0;
            $hasParams = ! empty($this->queryParamBindings);

            if (! is_resource($fbirdResultRc)) {
                // fbird_execute() returned boolean/integer (direct DML without prepared statement)
                if ($fbirdResultRc === true) {
                    // For DML operations that return true, use captured affected rows
                    $fbirdResultRc = $affectedRows > 0 ? $affectedRows : 1;
                }
                $this->connection->autoCommit();
            } else {
                // fbird_execute() returned resource - this happens for prepared statements
                // For prepared DML with bound parameters, affected_rows is unreliable (returns 0)
                // We use SQL-based detection (isDml flag) because fbird_num_fields is unreliable
                // (it can return non-zero even for DML statements in some Firebird versions)
                

                if ($this->isDml) {
                    // This is a DML statement (INSERT/UPDATE/DELETE/MERGE/EXECUTE)
                    // For prepared DML, fbird_affected_rows is unreliable (returns 0)
                    // Assume at least 1 row affected for successful execution
                    $fbirdResultRc = $affectedRows > 0 ? $affectedRows : 1;
                    $this->connection->autoCommit();
                }
                // else: This is a SELECT query - keep the resource for fetching
                // No auto-commit needed for SELECT (doesn't make changes)
            }
        }

        $this->currentResult = new Result($fbirdResultRc, $this->connection, $this);

        return $this->currentResult;
    }
}
