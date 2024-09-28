<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

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
use Satag\DoctrineFirebirdDriver\Driver\AbstractFirebirdDriver;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use UnexpectedValueException;

use function addcslashes;
use function fbird_close;
use function fbird_commit;
use function fbird_commit_ret;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_prepare;
use function fbird_query;
use function fbird_rollback;
use function fbird_server_info;
use function fbird_service_detach;
use function get_resource_type;
use function is_float;
use function is_int;
use function is_resource;
use function preg_match;
use function preg_split;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

use const IBASE_SVC_SERVER_VERSION;

/**
 * Based on https://github.com/helicon-os/doctrine-dbal
 * and Doctrine\DBAL\Driver\OCI8\Connection
 */
final class Connection implements ServerInfoAwareConnection
{
    /**
     * @var resource|false
     */
    private $connection;
    private ExecutionMode $executionMode;

    /**
     * Isolation level used when a transaction is started.
     */
    private int $attrDcTransIsolationLevel = TransactionIsolationLevel::READ_COMMITTED;

    /**
     * Wait timeout used in transactions
     *
     * @var int  Number of seconds to wait.
     */
    private int $attrDcTransWait = 0;

    /**
     * True if auto-commit is enabled
     */
    private bool $attrAutoCommit = true;

    private string|null $connectionInsertColumn = null;

    private $connectionInsertId;

    private readonly Parser $parser;

    /** @var false|resource|null (fbird_pconnect or fbird_connect) */
    private $firebirdConnection;

    /** @var false|resource|null */
    private $fbirdService;

    private int $fbirdTransactionLevel = 0;

    /** @var false|resource|null */
    private $fbirdActiveTransaction = false;
    private bool $isPrivileged;

    private static int|null $lastInsertIdentityId = null;

    private static array $lastInsertIdenties = [];

    private static bool $initialIbaseCloseCalled   = false;
    private static string|null $lastInsertSequence = null;
    private static array $lastInsertSequences      =  [];


    /**
     * @param resource|null $connection
     * @param resource $fbirdService
     * @param bool $isPersistent
     * @throws Exception
     */
    public function __construct($connection, $fbirdService, protected bool $isPersistent) {
        $this->connection = $connection;
        $this->parser       = new Parser(false);
        $this->fbirdService = $fbirdService;
        $this->executionMode = new ExecutionMode();
        if($connection !== null) {
            $this->fbirdActiveTransaction = $this->createTransaction(true);
        }
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (DriverException) {
            $this->connection            = null;
            $this->fbirdService          = null;
            $this->fbirdTransactionLevel = 0;
        }
    }

    public function getActiveTransaction()
    {
        return $this->fbirdActiveTransaction;
    }

    /**
     * Additionally to the standard driver attributes, the attribute
     * {@link AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL} can be used to control the
     * isolation level used for transactions.
     */
    public function setAttribute(string|int $attribute, mixed $value): void
    {
        switch ($attribute) {
            case AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL:
                $this->attrDcTransIsolationLevel = $value;
                break;
            case AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT:
                $this->attrDcTransWait = $value;
                break;
            case PDO::ATTR_AUTOCOMMIT:
                $this->attrAutoCommit = $value;
                break;
        }
    }

    public function getAttribute(string|int $attribute): mixed
    {
        return match ($attribute) {
            AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL
              => $this->attrDcTransIsolationLevel,
            AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT
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

    public function getConnectionInsertId()
    {
        return $this->connectionInsertId;
    }

    /** @return false|resource (fbird_pconnect or fbird_connect) */

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return is_resource($this->fbirdService) ? @fbird_server_info($this->fbirdService, IBASE_SVC_SERVER_VERSION) : '';
    }

    public function requiresQueryForServerVersion(): bool
    {
        return false;
    }

    public function prepare(string $sql): DriverStatement
    {
        $visitor = new ConvertParameters();

        $this->parser->parse($sql, $visitor);

        $sql = $visitor->getSQL();

        if(str_starts_with($sql, 'SET TRANSACTION')) {
            $this->fbirdActiveTransaction = $this->createTransaction(true, $sql);
            $this->fbirdTransactionLevel++;
            return new Statement(
                $this,
                $this->fbirdActiveTransaction,
                $visitor->getParameterMap()
            );
        }

        return new Statement($this, @fbird_prepare(
            $this->connection,
            $this->fbirdActiveTransaction,
            $sql,
        ), $visitor->getParameterMap());
    }

    public function setConnectionInsertTableColumn($table, $column): void
    {
        $this->connectionInsertColumn = $column;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql): ResultInterface
    {
        return $this->prepare($sql)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = str_replace("'", "''", $value);

        return "'" . addcslashes($value, "\000\n\r\\\032") . "'";
    }

    public function exec(string $sql): int
    {
        return $this->prepare($sql)->execute()->rowCount();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function lastInsertId($name = null)
    {
        if ($name === null && $this->connectionInsertId !== null) {
            return $this->connectionInsertId;
        }

        if ($name === null) {
            return false;
        }

        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4687',
            'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
        );

        if (str_contains((string) $name, '.')) {
            [$table, $column] = preg_split('/\./', $name);

// if($this->connectionInsertColumn === $column && $this->connectionInsertTable === $table) {
                return $this->connectionInsertId;
            //}
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

    /** @throws DriverException */
    public static function getStartTransactionSql(int $isolationLevel): string
    {
        $result = '';
        match ($isolationLevel) {
            TransactionIsolationLevel::READ_UNCOMMITTED
                => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ UNCOMMITTED RECORD_VERSION',
            TransactionIsolationLevel::READ_COMMITTED
                => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ COMMITTED RECORD_VERSION',
            TransactionIsolationLevel::REPEATABLE_READ
                => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT',
            TransactionIsolationLevel::SERIALIZABLE
                => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT TABLE STABILITY',
            default => throw new DriverException(sprintf(
                'Isolation level %s is not supported',
                ValueFormatter::cast($isolationLevel),
            )),
        };
        $result .= ' NO WAIT';
        /**
        if (($this->attrDcTransWait > 0)) {
            $result .= ' WAIT LOCK TIMEOUT ' . $this->attrDcTransWait;
        } elseif (($this->attrDcTransWait === -1)) {
            $result .= ' WAIT';
        } else {
            $result .= ' NO WAIT';
        }
        */
        return $result;
    }

    public function beginTransaction(): bool
    {
        if ($this->fbirdTransactionLevel < 1) {
            $this->fbirdActiveTransaction = $this->createTransaction(true);
            $this->fbirdTransactionLevel++;
        }

        $this->executionMode->disableAutoCommit();
        return true;
    }

    public function commit(): bool
    {
        if ($this->fbirdTransactionLevel > 0) {
            if (! is_resource($this->fbirdActiveTransaction)) {
                throw new RuntimeException(sprintf(
                    'No active transaction. $this->_fbirdTransactionLevel = %d',
                    $this->fbirdTransactionLevel,
                ));
            }

            $success = @fbird_commit_ret($this->fbirdActiveTransaction);
            if ($success === false) {
                $this->checkLastApiCall(null, $this->fbirdActiveTransaction);
            }

            $this->fbirdTransactionLevel--;
        }

        if ($this->fbirdTransactionLevel === 0) {
            @fbird_commit($this->fbirdActiveTransaction);
            $this->fbirdActiveTransaction = $this->createTransaction(true);
        }
        $this->executionMode->enableAutoCommit();

        return true;
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started.
     *
     * @throws RuntimeException|Exception
     */
    public function autoCommit(): bool|null
    {
        if ($this->executionMode->isAutoCommitEnabled() && $this->fbirdTransactionLevel < 1) {
            if (is_resource($this->fbirdActiveTransaction) === false) {
                throw new RuntimeException(sprintf(
                    'No active transaction. $this->_fbirdTransactionLevel = %d',
                    $this->fbirdTransactionLevel,
                ));
            }

            $success = @fbird_commit_ret($this->fbirdActiveTransaction);
            if ($success === false) {
                $this->checkLastApiCall();
            }

            return true;
        }

        return null;
    }

    /**
     * {@inheritdoc)
     *
     * @throws RuntimeException
     */
    public function rollBack(): bool
    {
        if ($this->fbirdTransactionLevel > 0) {
            if (is_resource($this->fbirdActiveTransaction) === false) {
                throw new RuntimeException(sprintf(
                    'No active transaction. $this->_fbirdTransactionLevel = %d',
                    $this->fbirdTransactionLevel,
                ));
            }

            $success = @fbird_rollback($this->fbirdActiveTransaction);
            if ($success === false) {
                $this->checkLastApiCall();
            }

            $this->fbirdTransactionLevel--;
        }

        $this->fbirdActiveTransaction = $this->createTransaction(true);
        $this->executionMode->enableAutoCommit();
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function errorInfo(): array
    {
        $errorCode = @fbird_errcode();
        if ($errorCode !== false) {
            return [
                'code' => $errorCode,
                'message' => @fbird_errmsg(),
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
     * @param resource|null $preparedStatement
     * @param resource|null $transaction
     *
     * @throws DriverException
     */
    public function checkLastApiCall($preparedStatement = null, $transaction = null): void
    {
        $lastError = $this->errorInfo();
        if (! isset($lastError['code']) || $lastError['code'] === 0) {
            return;
        }

        if (isset($transaction) && $this->fbirdActiveTransaction < 1) {
            $result = @fbird_rollback($transaction);
            if ($result) {
                $this->fbirdActiveTransaction = $this->createTransaction(true);
            }
        }

        throw DriverException::fromErrorInfo($lastError['message'], $lastError['code']);
    }

    /** @throws DriverException */
    public function close(): void
    {
        if (! self::$initialIbaseCloseCalled) {
            @fbird_close();
            self::$initialIbaseCloseCalled = true;

            return;
        }

        if (
                   is_resource($this->fbirdActiveTransaction)
                && get_resource_type($this->fbirdActiveTransaction) !== 'Unknown'
        ) {
            if ($this->fbirdTransactionLevel > 0) {
                $this->rollBack(); // Auto-rollback explicit transactions
            }

            $this->autoCommit();
            @fbird_commit($this->fbirdActiveTransaction);
            @fbird_close($this->fbirdActiveTransaction);
        }

            $success = true;

        while (
                    is_resource($this->connection)
                && get_resource_type($this->connection) !== 'Unknown'
        ) {
            $success = @fbird_close($this->connection);
        }

        if (is_resource($this->fbirdService)) {
            @fbird_service_detach($this->fbirdService);
            $success = @fbird_close($this->fbirdService);
        }

            $this->connection      = null;
            $this->fbirdActiveTransaction = null;
            $this->fbirdTransactionLevel  = 0;

        if ($success !== false) {
            return;
        }

        $this->checkLastApiCall();
    }

    /** @return false|resource */
    public function getNativeConnection()
    {
        return $this->connection;
    }

    /**
     * @return resource The fbird transaction.
     *
     * @throws DriverException
     */
    private function createTransaction(bool $commitDefaultTransaction = true, ?string $sql = null)
    {
        if ($commitDefaultTransaction && is_resource($this->connection)) {
            @fbird_commit($this->connection);
        }

        if($sql === null) {
            $sql = $this->getStartTransactionSql($this->attrDcTransIsolationLevel);
        }

        if (! is_resource($this->connection) || get_resource_type($this->connection) === 'Unknown') {
            $this->checkLastApiCall();
        }

        $result = @fbird_query($this->connection, $sql);
        if (! is_resource($result)) {
            $this->checkLastApiCall();
        }

        return $result;
    }
}
