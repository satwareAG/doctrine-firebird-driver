<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use RuntimeException;

use function array_flip;
use function array_unshift;
use function assert;
use function fbird_blob_add;
use function fbird_blob_close;
use function fbird_blob_create;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_execute;
use function fbird_free_query;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function func_num_args;
use function fwrite;
use function get_resource_type;
use function is_int;
use function is_resource;
use function ksort;
use function strlen;

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

    /** @var array<int|string> */
    private mixed $parameterMap;

    /**
     * @param resource|false|null $statement
     * @param array<int|string>   $parameterMap
     *
     * @throws Exception
     */
    public function __construct(protected Connection $connection, protected $statement, array $parameterMap = [])
    {
        if (! is_resource($statement)) {
            $this->connection->checkLastApiCall();
        }

        $this->parameterMap = $parameterMap;
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

        @fbird_free_query($this->statement);
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

        return $this->bindParam($param, $value, $type, null);
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
                throw new Exception('Positional Parameter not found');
            }
        } else {
            $params = array_flip($this->parameterMap);
            if (! isset($params[$param])) {
                    throw new Exception('Named Parameter not found');
            }

            $param = $params[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            if ($variable !== null) {
                $blobResource = @fbird_blob_create($this->connection->getActiveTransaction());
                if (! is_resource($blobResource)) {
                    throw Exception::fromErrorInfo((string) @fbird_errmsg(), (int) fbird_errcode());
                }

                if (! is_resource($variable)) {
                    $fp = fopen('php://temp', 'rb+');
                    assert(is_resource($fp));
                    fwrite($fp, $variable);
                    fseek($fp, 0);
                    $variable = $fp;
                }

                while (! feof($variable)) {
                    $chunk = fread($variable, 8192); // Read in chunks of 8KB (or a size appropriate for your needs)
                    if ($chunk === false || strlen($chunk) <= 0) {
                        continue;
                    }

                    @fbird_blob_add($blobResource, $chunk);
                }

                fclose($variable);
                // Close the BLOB
                $variable = @fbird_blob_close($blobResource);
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
                $this->connection->checkLastApiCall();
            }

            // Result seems ok - is either #rows or result handle
            // As the fbird-api does not have an auto-commit-mode, autocommit is simulated by calling the
            // function autoCommit of the connection
            $this->connection->autoCommit();
        }

        return new Result($fbirdResultRc, $this->connection);
    }
}
