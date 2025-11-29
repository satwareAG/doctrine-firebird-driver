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
use function error_log;
use function fbird_blob_add;
use function fbird_blob_cancel;
use function fbird_blob_close;
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
use function sprintf;
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

    /** @var array<int|string, mixed> */
    protected array $boundValues = [];

    private Result|null $currentResult = null;

    /**
     * @param resource|false|null $statement
     * @param array<int|string>   $parameterMap
     *
     * @throws Exception
     */
    public function __construct(protected Connection $connection, protected $statement, private mixed $parameterMap = [])
    {
        if (is_resource($statement)) {
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
                $blobResource = null;
                $stream       = $variable;

                try {
                    $blobResource = fbird_blob_create($this->connection->getActiveTransaction());
                    if (! is_resource($blobResource)) {
                        throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
                    }

                    while (! feof($stream)) {
                        $chunk = fread($stream, 8192); // Read in chunks of 8KB (or a size appropriate for your needs)
                        if ($chunk === false || strlen($chunk) <= 0) {
                            continue;
                        }

                        if (fbird_blob_add($blobResource, $chunk) === false) {
                            throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
                        }
                    }

                    // Close the BLOB
                    $blobId = fbird_blob_close($blobResource);

                    if ($blobId === false) {
                        throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
                    }

                    $variable     = $blobId;
                    $blobResource = null; // Mark as closed
                    $type         = ParameterType::STRING;
                } catch (Throwable $e) {
                    error_log('BLOB creation failed during bindParam: ' . $e->getMessage());

                    /** @psalm-suppress NoValue */
                    if (is_resource($blobResource)) {
                        fbird_blob_cancel($blobResource);
                    }

                    throw $e;
                } finally {
                    /** @psalm-suppress RedundantCondition */
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
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

                $blobResource = null;

                try {
                    $transaction  = $this->connection->getActiveTransaction();
                    $blobResource = fbird_blob_create($transaction);
                    if (! is_resource($blobResource)) {
                        throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
                    }

                    while (! feof($variable)) {
                        $chunk = fread($variable, 8192); // Read in chunks of 8KB (or a size appropriate for your needs)
                        if ($chunk === false || strlen($chunk) <= 0) {
                            continue;
                        }

                        if (fbird_blob_add($blobResource, $chunk) === false) {
                            throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
                        }
                    }

                    // Close the BLOB
                    $blobId = fbird_blob_close($blobResource);

                    if ($blobId === false) {
                        throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
                    }

                    $blobResource = null; // Mark as closed

                    // Update the binding to the blob ID string
                    // detailed explanation: The crash was caused by bindValue() creating a temporary variable passed by value,
                    // which bindParam() then referenced. When bindValue() returned, the reference became unstable/dangling
                    // before fbird_execute() could use it. By handling this inline, we keep the data safe.
                    $this->queryParamBindings[$param] = $blobId;
                    $this->queryParamTypes[$param]    = ParameterType::STRING;
                } catch (Throwable $e) {
                    error_log('BLOB creation failed during execute: ' . $e->getMessage());

                    /** @psalm-suppress NoValue */
                    if (is_resource($blobResource)) {
                        fbird_blob_cancel($blobResource);
                    }

                    throw $e;
                } finally {
                    /** @psalm-suppress RedundantCondition */
                    if (is_resource($variable)) {
                        fclose($variable);
                    }
                }
            }

            $callArgs = $this->queryParamBindings;
            // sort
            ksort($callArgs);
            // Dereference args to ensure values are passed
            $callArgs = array_map(static fn ($v): mixed => $v, $callArgs);
            array_unshift($callArgs, $this->statement);

            $fbirdResultRc = fbird_execute(...$callArgs);

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
            // NOTE: We skip AutoCommit if result is a resource (SELECT) because commit_ret closes cursors!
            if (! is_resource($fbirdResultRc)) {
                $this->connection->autoCommit();
            }
        }

        $this->currentResult = new Result($fbirdResultRc, $this->connection, $this);

        return $this->currentResult;
    }
}
