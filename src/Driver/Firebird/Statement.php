<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use PDO;
use RuntimeException;

use function array_unshift;
use function assert;
use function fclose;
use function feof;
use function fread;
use function func_num_args;
use function fbird_blob_add;
use function fbird_blob_close;
use function fbird_blob_create;
use function fbird_free_query;
use function is_int;
use function is_object;
use function is_resource;
use function ksort;
use function sprintf;
use function strlen;

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
     * @var resource
     */
    protected  $statement;

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

    private mixed $parameterMap;

    /**
     * @param Connection $connection
     * @param resource $statement
     * @param null $executionMode
     */
    public function __construct(protected Connection $connection, $statement, array $parameterMap, $executionMode = null)
    {
        $this->statement     = $statement;
        $this->parameterMap  = $parameterMap;
        $this->executionMode = $executionMode;
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

        if (is_int($param)) {
            if (! isset($this->parameterMap[$param])) {
                throw new Exception('Positional Parameter not found');
            }
        }


        if ($type === ParameterType::LARGE_OBJECT) {
            if ($variable !== null) {
                $fbirdBlobResource = @fbird_blob_create($this->connection->getActiveTransaction());
                if (!is_resource($variable)) {
                    $fp = fopen('php://temp', 'rb+');
                    assert(is_resource($fp));
                    fwrite($fp, $variable);
                    fseek($fp, 0);
                    $variable = $fp;
                }

                while (!feof($variable)) {
                    $chunk = fread($variable, 8192); // Read in chunks of 8KB (or a size appropriate for your needs)
                    if ($chunk === false || strlen($chunk) <= 0) {
                        continue;
                    }

                    @fbird_blob_add($fbirdBlobResource, $chunk);
                }

                fclose($variable);
                // Close the BLOB
                $variable = @fbird_blob_close($fbirdBlobResource);
                $type = ParameterType::STRING;
            }
        }
        assert(is_int($param));
        $this->queryParamBindings[$param] = &$variable;
        $this->queryParamTypes[$param]    = $type;

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
                    $check = array_flip($this->parameterMap);
                    $this->bindValue($check[':' .$key] ?? 0, ParameterType::STRING);
                }
            }
        }

        // Execute statement




        foreach($this->queryParamTypes as $param => $type) {
            switch ($type) {
                case ParameterType::LARGE_OBJECT:
                    // recheck with BindParams
                    $this->bindValue($param, $this->queryParamBindings[$param], ParameterType::LARGE_OBJECT);
                    break;
            }
        }

        $callArgs = $this->queryParamBindings;

        // sort
        ksort($callArgs);

        array_unshift($callArgs, $this->statement);

        $fbirdResultRc = @fbird_execute(...$callArgs);
        if ($fbirdResultRc === false) {
            $this->connection->checkLastApiCall($this->statement);
        }


        // Result seems ok - is either #rows or result handle
        // As the fbird-api does not have an auto-commit-mode, autocommit is simulated by calling the
        // function autoCommit of the connection
        $this->connection->autoCommit();

        return new Result($fbirdResultRc, $this->connection);
    }

    public function __destruct()
    {
        if (is_resource($this->statement)) {
            $result = @fbird_close($this->statement);
        }
    }

}
