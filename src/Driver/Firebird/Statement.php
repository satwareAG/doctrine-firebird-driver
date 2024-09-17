<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use Satag\DoctrineFirebirdDriver\SQLParserUtils;
use PDO;
use RuntimeException;

use function array_unshift;
use function assert;
use function call_user_func_array;
use function count;
use function fclose;
use function feof;
use function fread;
use function func_num_args;
use function get_resource_type;
use function fbird_affected_rows;
use function fbird_blob_add;
use function fbird_blob_close;
use function fbird_blob_create;
use function fbird_free_query;
use function fbird_num_fields;
use function fbird_prepare;
use function fbird_query;
use function is_int;
use function is_numeric;
use function is_object;
use function is_resource;
use function ksort;
use function sprintf;
use function strlen;
use function substr;

/**
 * Based on:
 *   - https://github.com/helicon-os/doctrine-dbal
 *   - https://github.com/doctrine/dbal/blob/2.6/lib/Doctrine/DBAL/Driver/SQLSrv/SQLSrvStatement.php
 */
class Statement implements StatementInterface
{
    public const DEFAULT_FETCH_CLASS                  = '\stdClass';
    public const DEFAULT_FETCH_CLASS_CONSTRUCTOR_ARGS = [];
    public const DEFAULT_FETCH_COLUMN                 = 0;
    public const DEFAULT_FETCH_MODE                   = PDO::FETCH_BOTH;

      /**
       * The SQL or DDL statement.
       */
    protected string $statement;

    /**
     * Zero-Based List of parameter bindings
     *
     * @var array<int, mixed>
     */
    protected array $queryParamBindings = [];

    /**
     * Zero-Based List of parameter binding types
     *
     * @var array
     */
    protected array $queryParamTypes = [];

     /**
      * Mapping between parameter names and positions
      *
      * The map is indexed by parameter name including the leading ':'.
      *
      * Each item contains an array of zero-based parameter positions.
      */
    protected array $namedParamsMap = [];

    /** @throws Exception */
    public function __construct(protected Connection $connection, string $prepareString)
    {
        $this->setStatement($prepareString);
    }

    /**
     * {@inheritdoc}
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

        return $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
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

        if (is_object($variable)) {
            $variable = (string) $variable;
        }

        if (is_numeric($param)) {
            $this->queryParamBindings[$param - 1] = &$variable;
            $this->queryParamTypes[$param - 1]    = $type;
        } else {
            if (! isset($this->namedParamsMap[$param])) {
                throw new Exception('Cannot bind to unknown parameter ' . $param, null);
            }

            foreach ($this->namedParamsMap[$param] as $pp) {
                assert(is_int($pp));
                $this->queryParamBindings[$pp] = &$variable;
                $this->queryParamTypes[$pp]    = $type;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function execute($params = null): ResultInterface
    {
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
                    $this->bindValue($key, $val, ParameterType::STRING);
                }
            }
        }

        // Execute statement
        if (count($this->queryParamBindings) > 0) {
            $fbirdResultRc = $this->doExecPrepared();
        } else {
            $fbirdResultRc = $this->doDirectExec();
        }

        $affectedRows = 0;
        $numFields    = 0;

        if ($fbirdResultRc === false) {
            throw new RuntimeException('Statement execute failed. Uncovered case. Result statement is `false`');
        }

        // Result seems ok - is either #rows or result handle
        if (is_numeric($fbirdResultRc)) {
            $affectedRows  = $fbirdResultRc;
            $fbirdResultRc = null;
        } elseif (is_resource($fbirdResultRc)) {
            $affectedRows = @fbird_affected_rows($this->connection->getActiveTransaction());
            $numFields    = @fbird_num_fields($fbirdResultRc) ?: 0;
        } elseif ($fbirdResultRc === true) {
            $fbirdResultRc = null;
        }

        // As the fbird-api does not have an auto-commit-mode, autocommit is simulated by calling the
        // function autoCommit of the connection
        $this->connection->autoCommit();

        // check for Identity
        return new Result($fbirdResultRc, $this->connection, $affectedRows, $numFields, $this->statement);
    }

    /**
     * @return true|int|resource
     *
     * @throws Exception
     */
    protected function doDirectExec()
    {
        try {
            $resultResource = @fbird_query($this->connection->getActiveTransaction(), $this->statement);
            if ($resultResource === false) {
                $this->connection->checkLastApiCall();

                throw new Exception('Result resource is `false` for ' . $this->statement);
            }
        } catch (Exception $e) {
            throw new Exception(sprintf(
                'Failed to perform `doDirectExec`: %s',
                $e->getMessage(),
            ), $e->getSQLState(), $e->getCode());
        }

        return $resultResource;
    }

    /**
     * Prepares the statement for further use and executes it
     *
     * @return resource
     *
     * @throws Exception
     */
    protected function doExecPrepared()
    {
        try {
            $activeTransaction = $this->connection->getActiveTransaction();
            $preparedStatement = @fbird_prepare(
                $activeTransaction,
                $this->statement,
            );
            if (! is_resource($preparedStatement)) {
                $this->connection->checkLastApiCall();
            }

            $callArgs = $this->queryParamBindings;
                // sort
            ksort($callArgs);
            foreach ($callArgs as $id => $arg) {
                if (! is_resource($arg)) {
                    continue;
                }

                $type    = get_resource_type($arg);
                $blob_id = @fbird_blob_create($this->connection->getActiveTransaction());
                while (! feof($arg)) {
                    $chunk = fread($arg, 8192); // Read in chunks of 8KB (or a size appropriate for your needs)
                    if ($chunk === false || strlen($chunk) <= 0) {
                        continue;
                    }

                    @fbird_blob_add($blob_id, $chunk);
                }

                // Close the BLOB
                $blob_id = fbird_blob_close($blob_id);
                fclose($arg);
                $callArgs[$id] = $blob_id;
            }

            array_unshift($callArgs, $preparedStatement);
            $resultResource = @call_user_func_array('fbird_execute', $callArgs); // Won't work: $resultResource = @fbird_execute(...$callArgs);
            if ($resultResource === false) {
                $this->connection->checkLastApiCall($preparedStatement, $activeTransaction);

                throw new Exception('Result resource is `false`');
            }

            @fbird_free_query($preparedStatement);
        } catch (Exception $e) {
            throw new Exception(sprintf(
                'Failed to perform `doExecPrepared`: %s',
                $e->getMessage(),
            ), $e->getSQLState(), $e->getCode());
        }

        return $resultResource;
    }

    /**
     * Sets and analyzes the statement.
     */
    protected function setStatement(string $statement): void
    {
        $this->statement      = $statement;
        $this->namedParamsMap = [];
        $pp                   = SQLParserUtils::getPlaceholderPositions($statement, false);
        if (empty($pp)) {
            return;
        }

        $pidx               = 0; // index-position of the parameter
        $le                 = 0; // substr start position
        $convertedStatement = '';
        foreach ($pp as $ppos => $pname) {
            $convertedStatement .= substr($statement, $le, $ppos - $le) . '?';
            if (! isset($this->namedParamsMap[':' . $pname])) {
                $this->namedParamsMap[':' . $pname] = (array) $pidx;
            } else {
                $this->namedParamsMap[':' . $pname][] = $pidx;
            }

            $le = $ppos + strlen($pname) + 1; // Continue at position after :name
            $pidx++;
        }

        $convertedStatement .= substr($statement, $le);
        $this->statement     = $convertedStatement;
    }
}
