<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\Deprecations\Deprecation;
use InvalidArgumentException;
use Kafoso\DoctrineFirebirdDriver\Driver\AbstractFirebirdInterbaseDriver;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception\HostDbnameRequired;
use Kafoso\DoctrineFirebirdDriver\ValueFormatter;
use PDO;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

use function addcslashes;
use function get_resource_type;
use function ibase_close;
use function ibase_commit;
use function ibase_commit_ret;
use function ibase_connect;
use function ibase_errcode;
use function ibase_errmsg;
use function ibase_free_query;
use function ibase_pconnect;
use function ibase_query;
use function ibase_rollback;
use function ibase_server_info;
use function ibase_service_attach;
use function ibase_service_detach;
use function is_float;
use function is_int;
use function is_numeric;
use function is_resource;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function str_starts_with;

use const IBASE_SVC_SERVER_VERSION;

/**
 * Based on https://github.com/helicon-os/doctrine-dbal
 * and Doctrine\DBAL\Driver\OCI8\Connection
 */
final class Connection implements ServerInfoAwareConnection
{
    public const DEFAULT_CHARSET = 'UTF-8';
    public const DEFAULT_BUFFERS = 0;

    /**
     * here are a couple of additional caveats to keep in mind when using persistent connections
     * https://www.php.net/manual/en/features.persistent-connections.php
     */
    public const DEFAULT_IS_PERSISTENT = true;
    public const DEFAULT_DIALECT       = 0;
    protected string $connectString;

    protected string $host;

    protected bool $isPersistent;

    protected string $charset = 'UTF-8';

    protected int $buffers = 0;

    protected int $dialect = 0;

    /** @var false|resource|null (ibase_pconnect or ibase_connect) */
    private $ibaseConnectionRc = null;

    /** @var false|resource|null */
    private $ibaseService = null;

    private int $ibaseTransactionLevel = 0;

    /** @var false|resource|null */
    private $ibaseActiveTransaction = false;

    /**
     * Isolation level used when a transaction is started.
     */
    protected int $attrDcTransIsolationLevel = TransactionIsolationLevel::READ_COMMITTED;

    /**
     * Wait timeout used in transactions
     *
     * @var int  Number of seconds to wait.
     */
    protected int $attrDcTransWait = 5;

    /**
     * True if auto-commit is enabled
     */
    protected bool $attrAutoCommit = true;

    private bool $isPrivileged;

    /**
     * @param array<int|string,mixed>  $params
     * @param array<int|string, mixed> $driverOptions
     *
     * @throws HostDbnameRequired
     */
    public function __construct(
        array $params,
        private string $username,
        private string $password,
        array $driverOptions = [],
    ) {
        $this->close(true); // Close/reset; because calling __construct after instantiation is apparently a thing
        $this->isPersistent = self::DEFAULT_IS_PERSISTENT;
        if (isset($params['persistent'])) {
            $this->isPersistent = (bool) $params['persistent'];
        }

        $this->charset = self::DEFAULT_CHARSET;
        if (isset($params['charset']) && is_string($params['charset'])) {
            $this->charset = $params['charset'];
        }

        $this->buffers = self::DEFAULT_BUFFERS;
        if (isset($params['buffers']) && is_int($params['buffers']) && $params['buffers'] >= 0) {
            $this->buffers = $params['buffers'];
        }

        $this->dialect = self::DEFAULT_DIALECT;
        if (
            isset($params['dialect'])
            && is_int($params['dialect'])
            && $params['dialect'] >= 0
            && $params['dialect'] <= 3
        ) {
            $this->dialect = $params['dialect'];
        }

        if (! isset($params['host']) || ! is_string($params['host'])) {
            throw HostDbnameRequired::noHostParameter();
        }

        $this->host = $params['host'];

        foreach ($driverOptions as $k => $v) {
            $this->setAttribute($k, $v);
        }

        $this->isPrivileged = false;
        if (isset($params['privileged'])) {
            $this->isPrivileged = (bool) $params['privileged'];
        }

        $this->ibaseService = @ibase_service_attach($this->host, $this->username, $this->password);
        if (! is_resource($this->ibaseService)) {
            $this->checkLastApiCall();
        }

        if ($this->isPrivileged) {
            return;
        }

        $this->connectString = self::generateConnectString($params);
        $this->getActiveTransaction(); // Connects to the database
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (Exception) {
            $this->ibaseConnectionRc     = null;
            $this->ibaseService          = null;
            $this->ibaseTransactionLevel = 0;
        }
    }

    /**
     * Additionally to the standard driver attributes, the attribute
     * {@link AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL} can be used to control the
     * isolation level used for transactions.
     */
    public function setAttribute(string|int $attribute, mixed $value): void
    {
        switch ($attribute) {
            case AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL:
                $this->attrDcTransIsolationLevel = $value;
                break;
            case AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT:
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
            AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL
              => $this->attrDcTransIsolationLevel,
            AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT
              => $this->attrDcTransWait,
            PDO::ATTR_AUTOCOMMIT
              => $this->attrAutoCommit,
            PDO::ATTR_PERSISTENT
              => $this->isPersistent,
            default => null,
        };
    }

    /** @return false|resource (ibase_pconnect or ibase_connect) */
    public function getInterbaseConnectionResource()
    {
        return $this->ibaseConnectionRc;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return is_resource($this->ibaseService) ? ibase_server_info($this->ibaseService, IBASE_SVC_SERVER_VERSION) : '';
    }

    public function requiresQueryForServerVersion(): bool
    {
        return false;
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement($this, $sql);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sql
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
        if ($name === null) {
            return false;
        }

        if (is_string($name) === false) {
            throw new InvalidArgumentException(sprintf(
                'Argument $name must be null or a string. Found: %s',
                ValueFormatter::found($name),
            ));
        }

        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4687',
            'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
        );

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

        $sql = 'SELECT GEN_ID(' . $name . ', 0) LAST_VAL FROM RDB$DATABASE';

        return $this->query($sql)->fetchOne();
    }

    /** @throws Exception */
    public function getStartTransactionSql(int $isolationLevel): string
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
            default => throw new Exception(sprintf(
                'Isolation level %s is not supported',
                ValueFormatter::cast($isolationLevel),
            )),
        };
        if (($this->attrDcTransWait > 0)) {
            $result .= ' WAIT LOCK TIMEOUT ' . $this->attrDcTransWait;
        } elseif (($this->attrDcTransWait === -1)) {
            $result .= ' WAIT';
        } else {
            $result .= ' NO WAIT';
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        if ($this->ibaseTransactionLevel < 1) {
            $this->ibaseActiveTransaction = $this->createTransaction(true);
            $this->ibaseTransactionLevel++;
        }

        return true;
    }

    public function commit(): bool
    {
        if ($this->ibaseTransactionLevel > 0) {
            if (! is_resource($this->ibaseActiveTransaction)) {
                throw new RuntimeException(sprintf(
                    'No active transaction. $this->_ibaseTransactionLevel = %d',
                    $this->ibaseTransactionLevel,
                ));
            }

            $success = @ibase_commit_ret($this->ibaseActiveTransaction);
            if ($success === false) {
                $this->checkLastApiCall(null, $this->ibaseActiveTransaction);
            }

            $this->ibaseTransactionLevel--;
        }

        if ($this->ibaseTransactionLevel === 0) {
            @ibase_commit($this->ibaseActiveTransaction);
            $this->ibaseActiveTransaction = $this->createTransaction(true);
        }

        return true;
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started.
     *
     * @throws RuntimeException
     */
    public function autoCommit(): bool|null
    {
        if ($this->attrAutoCommit && $this->ibaseTransactionLevel < 1) {
            if (is_resource($this->ibaseActiveTransaction) === false) {
                throw new RuntimeException(sprintf(
                    'No active transaction. $this->_ibaseTransactionLevel = %d',
                    $this->ibaseTransactionLevel,
                ));
            }

            $success = @ibase_commit_ret($this->getActiveTransaction());
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
    public function rollBack(): void
    {
        if ($this->ibaseTransactionLevel > 0) {
            if (is_resource($this->ibaseActiveTransaction) === false) {
                throw new RuntimeException(sprintf(
                    'No active transaction. $this->_ibaseTransactionLevel = %d',
                    $this->ibaseTransactionLevel,
                ));
            }

            $success = @ibase_rollback($this->ibaseActiveTransaction);
            if ($success === false) {
                $this->checkLastApiCall();
            }

            $this->ibaseTransactionLevel--;
        }

        $this->ibaseActiveTransaction = $this->createTransaction(true);
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function errorInfo(): array
    {
        $errorCode = @ibase_errcode();
        if ($errorCode !== false) {
            return [
                'code' => $errorCode,
                'message' => @ibase_errmsg(),
            ];
        }

        return [
            'code' => 0,
            'message' => null,
        ];
    }

    /**
     * @return resource
     *
     * @throws RuntimeException
     */
    public function getActiveTransaction()
    {
        if (! is_resource($this->ibaseConnectionRc)) {
            try {
                if ($this->isPersistent) {
                    $this->ibaseConnectionRc = @ibase_pconnect( // Notice the "p"
                        $this->connectString,
                        $this->username,
                        $this->password,
                        $this->charset,
                        $this->buffers,
                        $this->dialect,
                    );
                } else {
                    $this->ibaseConnectionRc = @ibase_connect(
                        $this->connectString,
                        $this->username,
                        $this->password,
                        $this->charset,
                        $this->buffers,
                        $this->dialect,
                    );
                }

                if (! is_resource($this->ibaseConnectionRc)) {
                    $this->checkLastApiCall();
                }

                $this->ibaseActiveTransaction = $this->createTransaction(true);
            } catch (Throwable $e) {
                throw $e;
            }
        }

        if (is_resource($this->ibaseActiveTransaction) === false) {
            throw new RuntimeException(sprintf(
                'No active transaction. $this->_ibaseTransactionLevel = %d',
                $this->ibaseTransactionLevel,
            ));
        }

        return $this->ibaseActiveTransaction;
    }

    /**
     * Checks ibase_error and raises an exception if an error occured
     *
     * @param resource|null $preparedStatement
     * @param resource|null $transaction
     *
     * @throws Exception
     */
    public function checkLastApiCall($preparedStatement = null, $transaction = null): void
    {
        $lastError = $this->errorInfo();
        if (! isset($lastError['code']) || $lastError['code'] === 0) {
            return;
        }

        if (isset($transaction) && $this->ibaseActiveTransaction < 1) {
            $result = @ibase_rollback($transaction);
            if ($result) {
                $this->ibaseActiveTransaction = $this->createTransaction(true);
            }
        }

        if ($preparedStatement) {
            $result =  @ibase_free_query($preparedStatement);
        }

        throw Exception::fromErrorInfo($lastError);
    }

    /**
     * @return resource The ibase transaction.
     *
     * @throws Exception
     */
    protected function createTransaction(bool $commitDefaultTransaction = true)
    {
        if ($commitDefaultTransaction && is_resource($this->ibaseConnectionRc)) {
            @ibase_commit($this->ibaseConnectionRc);
        }

        $sql    = $this->getStartTransactionSql($this->attrDcTransIsolationLevel);
        $result = @ibase_query($this->ibaseConnectionRc, $sql);
        if (! is_resource($result)) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    public function close(): void
    {
        if (
                   is_resource($this->ibaseActiveTransaction)
                && get_resource_type($this->ibaseActiveTransaction) !== 'Unknown'
        ) {
            if ($this->ibaseTransactionLevel > 0) {
                $this->rollBack(); // Auto-rollback explicit transactions
            }

            $this->autoCommit();
            @ibase_commit($this->ibaseActiveTransaction);
            @ibase_close($this->ibaseActiveTransaction);
        }

            $success = true;

        while (
                    is_resource($this->ibaseConnectionRc)
                && get_resource_type($this->ibaseConnectionRc) !== 'Unknown'
        ) {
            $success = @ibase_close($this->ibaseConnectionRc);
        }

        if (is_resource($this->ibaseService)) {
            $success = @ibase_service_detach($this->ibaseService);
        }

            $this->ibaseConnectionRc      = null;
            $this->ibaseActiveTransaction = null;
            $this->ibaseTransactionLevel  = 0;

        if ($success !== false) {
            return;
        }

        $this->checkLastApiCall();
    }

    /** @param array<int|string, mixed> $params */
    public static function generateConnectString(array $params): string
    {
        if (isset($params['host'], $params['dbname']) && $params['host'] !== '' && $params['dbname'] !== '') {
            $str = $params['host'];
            if (isset($params['port'])) {
                if ($params['port'] === '' || ! is_numeric($params['port'])) {
                    throw HostDbnameRequired::invalidPort();
                }

                $str .= '/' . $params['port'];
            }

            $str .= ':' . $params['dbname'];

            return $str;
        }

        throw HostDbnameRequired::new();
    }

    /** @return false|resource */
    public function getNativeConnection()
    {
        return $this->ibaseConnectionRc;
    }
}
