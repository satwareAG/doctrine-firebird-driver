<?php
declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use Kafoso\DoctrineFirebirdDriver\SQLParserUtils;

/**
 * Based on:
 *   - https://github.com/helicon-os/doctrine-dbal
 *   - https://github.com/doctrine/dbal/blob/2.6/lib/Doctrine/DBAL/Driver/SQLSrv/SQLSrvStatement.php
 */
class Statement implements StatementInterface
{
    const DEFAULT_FETCH_CLASS = '\stdClass';
    const DEFAULT_FETCH_CLASS_CONSTRUCTOR_ARGS = [];
    const DEFAULT_FETCH_COLUMN = 0;
    const DEFAULT_FETCH_MODE = \PDO::FETCH_BOTH;

    /**
     * @var Connection $connection
     */
    protected $connection;

    /**
     * @var null|resource   Resource of the prepared statement
     */
    protected $ibaseStatementRc;

    /**
     * The SQL or DDL statement.
     * @var string
     */
    protected $statement = null;

    /**
     * Zero-Based List of parameter bindings
     * @var array
     */
    protected $queryParamBindings = [];

    /**
     * Zero-Based List of parameter binding types
     * @var array
     */
    protected $queryParamTypes = [];

    /**
     * @var integer Default fetch mode set by setFetchMode
     */
    protected $defaultFetchMode = self::DEFAULT_FETCH_MODE;

    /**
     * @var string  Default class to be used by FETCH_CLASS or FETCH_OBJ
     */
    protected $defaultFetchClass = self::DEFAULT_FETCH_CLASS;

    /**
     * @var integer Default column to fetch by FETCH_COLUMN
     */
    protected $defaultFetchColumn = self::DEFAULT_FETCH_COLUMN;

    /**
     * @var array   Parameters to be passed to constructor in FETCH_CLASS
     */
    protected $defaultFetchClassConstructorArgs = self::DEFAULT_FETCH_CLASS_CONSTRUCTOR_ARGS;

    /**
     * @var null|Object  Object used as target by FETCH_INTO
     */
    protected $defaultFetchInto = null;

    /**
     * Mapping between parameter names and positions
     *
     * The map is indexed by parameter name including the leading ':'.
     *
     * Each item contains an array of zero-based parameter positions.
     */
    protected $namedParamsMap = [];

    /**
     * @throws Exception
     */
    public function __construct(Connection $connection, string $prepareString)
    {
        $this->connection = $connection;
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
            $this->queryParamTypes[$param - 1] = $type;
        } else {
            if (isset($this->namedParamsMap[$param])) {
                /**
                 * @var integer $pp *zero* based Parameter index
                 */
                foreach ($this->namedParamsMap[$param] as $pp) {
                    $this->queryParamBindings[$pp] = &$variable;
                    $this->queryParamTypes[$pp] = $type;
                }
            } else {
                throw new Exception('Cannot bind to unknown parameter ' . $param, null);
            }
        }
        return true;
    }



    /**
     * {@inheritdoc}
     * @throws \RuntimeException
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
            $ibaseResultRc = $this->doExecPrepared();
        } else {
            $ibaseResultRc = $this->doDirectExec();
        }
        $affectedRows = 0;
        $numFields = 0;

        if ($ibaseResultRc !== false) {
            // Result seems ok - is either #rows or result handle
            if (is_numeric($ibaseResultRc)) {
                $affectedRows = $ibaseResultRc;
                $ibaseResultRc = null;
            } elseif (is_resource($ibaseResultRc)) {
                $affectedRows = @ibase_affected_rows($this->connection->getActiveTransaction());
                $numFields = @ibase_num_fields($ibaseResultRc) ?: 0;
            } elseif (true === $ibaseResultRc) {
                $ibaseResultRc = null;
            }
            // As the ibase-api does not have an auto-commit-mode, autocommit is simulated by calling the
            // function autoCommit of the connection
            $this->connection->autoCommit();
        } else {
            throw new \RuntimeException("Statement execute failed. Uncovered case. Result statement is `false`");
        }

        return new Result($ibaseResultRc, $this->connection, $affectedRows, $numFields);

    }

    public function errorCode()
    {
        return ibase_errcode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        $errorCode = $this->errorCode();
        if ($errorCode) {
            return [
                'code' => $this->errorCode(),
                'message' => ibase_errmsg(),
            ];
        }
        return [
            'code' => 0,
            'message' => null,
        ];
    }

    /**
     * @return true|int|resource
     * @throws Exception
     */
    protected function doDirectExec()
    {
        try {
            $resultResource = @ibase_query($this->connection->getActiveTransaction(), $this->statement);
            if (false === $resultResource) {
                $this->connection->checkLastApiCall();
                throw new Exception("Result resource is `false`");
            }
        } catch (Exception $e) {
            throw new Exception(sprintf(
                "Failed to perform `doDirectExec`: %s",
                $e->getMessage()
            ), $e->getSQLState(), $e->getCode());
        }
        return $resultResource;
    }

    /**
     * Prepares the statement for further use and executes it
     * @throws Exception
     * @return resource
     */
    protected function doExecPrepared()
    {
        try {
            if (!$this->ibaseStatementRc || !is_resource($this->ibaseStatementRc)) {
                $this->ibaseStatementRc = @ibase_prepare(
                    $this->connection->getActiveTransaction(),
                    $this->statement
                );
                if (!$this->ibaseStatementRc || !is_resource($this->ibaseStatementRc)) {
                    $this->connection->checkLastApiCall();
                }
            }
            $callArgs = $this->queryParamBindings;
            array_unshift($callArgs, $this->ibaseStatementRc);
            $resultResource = @call_user_func_array('ibase_execute', $callArgs);
            if (false === $resultResource) {
                $this->connection->checkLastApiCall();
                throw new Exception("Result resource is `false`");
            }
        } catch (Exception $e) {
            throw new Exception(sprintf(
                "Failed to perform `doExecPrepared`: %s",
                $e->getMessage()
            ), $e->getSQLState(), $e->getCode());
        }
        return $resultResource;
    }

    /**
     * Sets and analyzes the statement.
     */
    protected function setStatement(string $statement)
    {
        $this->statement = $statement;
        $this->namedParamsMap = [];
        $pp = SQLParserUtils::getPlaceholderPositions($statement, false);
        if (!empty($pp)) {
            $pidx = 0; // index-position of the parameter
            $le = 0; // substr start position
            $convertedStatement = '';
            foreach ($pp as $ppos => $pname) {
                $convertedStatement .= substr($statement, $le, $ppos - $le) . '?';
                if (!isset($this->namedParamsMap[':' . $pname])) {
                    $this->namedParamsMap[':' . $pname] = (array)$pidx;
                } else {
                    $this->namedParamsMap[':' . $pname][] = $pidx;
                }
                $le = $ppos + strlen($pname) + 1; // Continue at position after :name
                $pidx++;
            }
            $convertedStatement .= substr($statement, $le);
            $this->statement = $convertedStatement;
        }
    }
}
