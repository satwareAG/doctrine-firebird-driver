<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\Deprecations\Deprecation;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use UnexpectedValueException;

use function addcslashes;
use function fbird_close;
use function fbird_commit;
use function fbird_commit_ret;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_prepare;
use function fbird_rollback;
use function fbird_trans;
use function fbird_trans_start;
use function function_exists;
use function get_resource_type;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

use const IBASE_COMMITTED;
use const IBASE_CONCURRENCY;
use const IBASE_CONSISTENCY;
use const IBASE_NOWAIT;
use const IBASE_REC_VERSION;
use const IBASE_WAIT;
use const IBASE_WRITE;

/**
 * Based on https://github.com/helicon-os/doctrine-dbal
 * and Doctrine\DBAL\Driver\OCI8\Connection
 */
final class Connection implements ServerInfoAwareConnection
{
    private readonly ExecutionMode $executionMode;

    /**
     * Isolation level used when a transaction is started.
     */
    private int $attrDcTransIsolationLevel = TransactionIsolationLevel::READ_COMMITTED;

    /**
     * Wait timeout used in transactions
     *
     * @var int  Number of seconds to wait.
     */
    private int $attrDcTransWait = 5;

    /**
     * True if auto-commit is enabled
     */
    private bool $attrAutoCommit = true;

    private string|null $connectionInsertColumn = null;

    private int|null $connectionInsertId = null;

    private readonly Parser $parser;

    private int $fbirdTransactionLevel = 0;

    /** @var resource|null */
    private $firebirdActiveTransaction = null;

    /**
     * @param resource|null        $connection
     * @param array<string, mixed> $params
     *
     * @throws Exception
     */
    public function __construct(private $connection, private readonly string $serverVersion, protected bool $isPersistent, private readonly Exception|null $databaseNotFoundException, array $params)
    {
        $this->parser        = new Parser(false);
        $this->executionMode = new ExecutionMode();
        if ($connection !== null) {
            $this->firebirdActiveTransaction = $this->createTransaction();
        }

        foreach ($params as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function __destruct()
    {
        $connectionClosable = false;
        if (is_resource($this->connection)) {
            $type = get_resource_type($this->connection);
            if ($type === 'Firebird/InterBase link') {
                $connectionClosable = true;
            } elseif ($type === 'Firebird/InterBase persistent link') {
                $connectionClosable = false;
            } elseif ($type === 'Unknown') {
                $this->connection = null;
            }
        }

        if (is_resource($this->firebirdActiveTransaction)) {
            $type = get_resource_type($this->firebirdActiveTransaction);
            if ($type === 'Firebird/InterBase transaction') {
                fbird_commit($this->firebirdActiveTransaction);
            }

            unset($this->firebirdActiveTransaction);
            $this->firebirdActiveTransaction = null;
        }

        if ($connectionClosable) {
            fbird_close($this->connection);
        }

        unset($this->connection);
        $this->connection = null;
    }

    /** @return resource|null */
    public function getActiveTransaction()
    {
        return $this->firebirdActiveTransaction;
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
                $this->attrDcTransIsolationLevel = $value;
                break;
            case FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT:
                $this->attrDcTransWait = $value;
                break;
            case FirebirdDriver::ATTR_AUTOCOMMIT:
                $this->attrAutoCommit = $value;
                break;
        }
    }

    public function getAttribute(string|int $attribute): int|bool|null
    {
        return match ($attribute) {
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL
              => $this->attrDcTransIsolationLevel,
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT
              => $this->attrDcTransWait,
            PDO::ATTR_AUTOCOMMIT
              => $this->attrAutoCommit,
            PDO::ATTR_PERSISTENT
              => $this->isPersistent,
            default => null,
        };
    }

    public function getConnectionInsertColumn(): string|null
    {
        return $this->connectionInsertColumn;
    }

    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    /**
     * @throws Exception
     * @throws Exception
     * @throws Parser\Exception
     */
    public function prepare(string $sql): DriverStatement
    {
        if ($this->connection === null && is_object($this->databaseNotFoundException)) {
            throw $this->databaseNotFoundException;
        }

        $visitor = new ConvertParameters();

        $this->parser->parse($sql, $visitor);

        $sql = $visitor->getSQL();

        return new Statement(
            $this,
            @fbird_prepare($this->connection, $this->firebirdActiveTransaction, $sql),
            $visitor->getParameterMap(),
        );
    }

    public function setConnectionInsertColumn(string|null $column): void
    {
        $this->connectionInsertColumn = $column;
    }

    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException('Given value is not scalar.');
        }

        $value = str_replace("'", "''", (string) $value);

        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }

    public function exec(string $sql): int
    {
        return $this->prepare($sql)->execute()->rowCount();
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     *
     * @psalm-suppress DocblockTypeContradiction
     */
    public function lastInsertId($name = null)
    {
        if ($name !== null && ! is_string($name)) {
            throw new InvalidArgumentException(sprintf('Argument $name in %s must be null or a string. Found: %s', __FUNCTION__, ValueFormatter::found($name)));
        }

        if ($name === null) {
            return $this->connectionInsertId ?? false;
        }

        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4687',
            'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
        );

        if (str_contains($name, '.')) {
            return $this->connectionInsertId ?? false;
        }

        if (str_starts_with($name, 'SELECT RDB')) {
            $name = $this->query($name)->fetchOne();
        } else {
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

        $sql     = 'SELECT GEN_ID(' . $name . ', 0) LAST_VAL FROM RDB$DATABASE';
        $lastVal = $this->query($sql)->fetchOne();

        return $lastVal === 0 ? false : $lastVal;
    }

    public function setLastInsertId(int $id): void
    {
        $this->connectionInsertId = $id;
    }

    public function beginTransaction(): bool
    {
        if ($this->fbirdTransactionLevel === 0) {
            // as Firebird always generates a transaction, we have to commit everything now.
            if (is_resource($this->firebirdActiveTransaction)) {
                if (! fbird_commit($this->firebirdActiveTransaction)) {
                    // If implicit commit fails, try rollback to clear state before throwing
                    fbird_rollback($this->firebirdActiveTransaction);
                    $this->checkLastApiCall();
                }
            }

            $this->firebirdActiveTransaction = $this->createTransaction();
        }

        $this->fbirdTransactionLevel++;
        $this->executionMode->disableAutoCommit();

        return true;
    }

    public function commit(): bool
    {
        if ($this->fbirdTransactionLevel > 0) {
            $this->fbirdTransactionLevel--;
        }

        if ($this->fbirdTransactionLevel === 0) {
            if (! is_resource($this->firebirdActiveTransaction)) {
                throw new RuntimeException('No active transaction resource.');
            }

            if (! fbird_commit($this->firebirdActiveTransaction)) {
                // Capture error, attempt rollback cleanup, then throw
                $lastError = $this->errorInfo();
                fbird_rollback($this->firebirdActiveTransaction);

                if (isset($lastError['code']) && $lastError['code'] !== 0) {
                    throw DriverException::fromErrorInfo($lastError['message'], $lastError['code']);
                }
            }

            $this->firebirdActiveTransaction = $this->createTransaction();
            $this->executionMode->enableAutoCommit();
        }

        return true;
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started.
     *
     * @throws RuntimeException|Exception
     */
    public function autoCommit(): void
    {
        if (! $this->executionMode->isAutoCommitEnabled() || $this->fbirdTransactionLevel >= 1) {
            return;
        }

        if (is_resource($this->firebirdActiveTransaction) === false) {
            throw new RuntimeException(sprintf(
                'No active transaction. $this->_fbirdTransactionLevel = %d',
                $this->fbirdTransactionLevel,
            ));
        }

        $success = fbird_commit_ret($this->firebirdActiveTransaction);

        if ($success !== false) {
            return;
        }

        $this->checkLastApiCall();
    }

    /**
     * {@inheritdoc)
     *
     * @throws RuntimeException
     */
    public function rollBack(): bool
    {
        if ($this->fbirdTransactionLevel > 0) {
            $this->fbirdTransactionLevel--;
        }

        if ($this->fbirdTransactionLevel === 0) {
            if (! is_resource($this->firebirdActiveTransaction)) {
                throw new RuntimeException('No active transaction resource.');
            }

            $success = fbird_rollback($this->firebirdActiveTransaction);

            if (! $success) {
                // Capture error before resetting state
                $lastError = $this->errorInfo();
            }

            // Always attempt to restore valid state for next operation
            $this->firebirdActiveTransaction = $this->createTransaction();
            $this->executionMode->enableAutoCommit();

            if (! $success && isset($lastError['code']) && $lastError['code'] !== 0) {
                throw DriverException::fromErrorInfo($lastError['message'], $lastError['code']);
            }
        }

        return true;
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
        return $this->connection;
    }

    /**
     * @return resource The firebird transaction.
     * @psalm-return resource
     *
     * @throws DriverException
     */
    private function createTransaction()
    {
        if (! is_resource($this->connection) || get_resource_type($this->connection) === 'Unknown') {
            $this->checkLastApiCall();
        }

        if (function_exists('fbird_trans_start')) {
            $options = ['access_mode' => IBASE_WRITE];

            switch ($this->attrDcTransIsolationLevel) {
                case TransactionIsolationLevel::READ_UNCOMMITTED:
                    $options['isolation'] = IBASE_COMMITTED | IBASE_REC_VERSION;
                    break;
                case TransactionIsolationLevel::READ_COMMITTED:
                    $options['isolation'] = IBASE_COMMITTED | IBASE_REC_VERSION;
                    break;
                case TransactionIsolationLevel::REPEATABLE_READ:
                    $options['isolation'] = IBASE_CONCURRENCY;
                    break;
                case TransactionIsolationLevel::SERIALIZABLE:
                    $options['isolation'] = IBASE_CONSISTENCY;
                    break;
            }

            if ($this->attrDcTransWait === -1) {
                $options['lock_resolution'] = IBASE_WAIT;
            } elseif ($this->attrDcTransWait === 0) {
                $options['lock_resolution'] = IBASE_NOWAIT;
            } else {
                $options['lock_resolution'] = IBASE_WAIT;
                $options['lock_timeout']    = $this->attrDcTransWait;
            }

            $result = fbird_trans_start($this->connection, $options);
        } else {
            $flags = IBASE_WRITE | IBASE_COMMITTED | IBASE_REC_VERSION;

            switch ($this->attrDcTransIsolationLevel) {
                case TransactionIsolationLevel::READ_UNCOMMITTED:
                    $flags = IBASE_WRITE | IBASE_COMMITTED | IBASE_REC_VERSION;
                    break;
                case TransactionIsolationLevel::READ_COMMITTED:
                    $flags = IBASE_WRITE | IBASE_COMMITTED | IBASE_REC_VERSION;
                    break;
                case TransactionIsolationLevel::REPEATABLE_READ:
                    $flags = IBASE_WRITE | IBASE_CONCURRENCY;
                    break;
                case TransactionIsolationLevel::SERIALIZABLE:
                    $flags = IBASE_WRITE | IBASE_CONSISTENCY;
                    break;
            }

            if ($this->attrDcTransWait === -1) {
                $flags |= IBASE_WAIT;
            } elseif ($this->attrDcTransWait === 0) {
                $flags |= IBASE_NOWAIT;
            } else {
                $flags |= IBASE_WAIT;
            }

            $result = fbird_trans($flags, $this->connection);
        }

        if (! is_resource($result)) {
            $this->checkLastApiCall();

            // If checking last API call didn't throw an exception but we don't have a resource, something is wrong
            throw new DriverException('Failed to create transaction');
        }

        return $result;
    }
}
