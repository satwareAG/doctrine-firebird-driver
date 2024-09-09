<?php
namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Driver\Result as ResultInterface;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\Deprecations\Deprecation;
use Kafoso\DoctrineFirebirdDriver\Driver\AbstractFirebirdInterbaseDriver;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception\HostDbnameRequired;
use Kafoso\DoctrineFirebirdDriver\ValueFormatter;
use RuntimeException;

/**
 * Based on https://github.com/helicon-os/doctrine-dbal
 * and Doctrine\DBAL\Driver\OCI8\Connection
 */
final class Connection implements ServerInfoAwareConnection
{
    const DEFAULT_CHARSET = 'UTF-8';
    const DEFAULT_BUFFERS = 0;

    /**
     * here are a couple of additional caveats to keep in mind when using persistent connections
     * https://www.php.net/manual/en/features.persistent-connections.php
     */
    const DEFAULT_IS_PERSISTENT = true;
    const DEFAULT_DIALECT = 0;
    protected string $connectString;

    protected string $host;

    /**
     * @var bool
     */
    protected $isPersistent = true;

    /**
     * @var string
     */
    protected $charset = 'UTF-8';

    /**
     * @var int
     */
    protected $buffers = 0;

    /**
     * @var int
     */
    protected $dialect = 0;

    /**
     * @var false|resource (ibase_pconnect or ibase_connect)
     */
    private $_ibaseConnectionRc = false;

    /**
     * @var false|resource
     */
    private $_ibaseService = false;

    /**
     * @var int
     */
    private $_ibaseTransactionLevel = 0;

    /**
     * @var false|resource
     */
    private $_ibaseActiveTransaction = false;

    /**
     * Isolation level used when a transaction is started.
     * @var int
     */
    protected $attrDcTransIsolationLevel = TransactionIsolationLevel::READ_COMMITTED;

    /**
     * Wait timeout used in transactions
     *
     * @var integer  Number of seconds to wait.
     */
    protected $attrDcTransWait = 5;

    /**
     * True if auto-commit is enabled
     * @var boolean
     */
    protected $attrAutoCommit = true;

    private bool $isPrivileged;
    private string $password;
    private string $username;

    /**
     * @param array<int|string,mixed> $params
     * @param array<int|string, mixed> $driverOptions
     * @throws HostDbnameRequired
     */
    public function __construct(array $params, string $username, string $password, array $driverOptions = [])
    {
        $this->close(); // Close/reset; because calling __construct after instantiation is apparently a thing
        $this->isPersistent = self::DEFAULT_IS_PERSISTENT;
        if (isset($params['persistent'])) {
            $this->isPersistent = (bool)$params['persistent'];
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
        if (isset($params['dialect'])
            && is_int($params['dialect'])
            && $params['dialect'] >= 0
            && $params['dialect'] <= 3) {
            $this->dialect = $params['dialect'];
        }


        if (isset($params['host']) && is_string($params['host'])) {
            $this->host = $params['host'];
        } else {
            throw HostDbnameRequired::noHostParameter();
        }

        $this->username = $username;
        $this->password = $password;
        foreach ($driverOptions as $k => $v) {
            $this->setAttribute($k, $v);
        }

        $this->isPrivileged = false;
        if (isset($params['privileged'])) {
            $this->isPrivileged = (bool)$params['privileged'];
        }

        $this->_ibaseService = @ibase_service_attach($this->host, $this->username, $this->password);
        if (!is_resource($this->_ibaseService)) {
            $this->checkLastApiCall();
        }

        if (!$this->isPrivileged) {
            $this->connectString = self::generateConnectString($params);
            $this->getActiveTransaction(); // Connects to the database
        }
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (Exception $e) {
            unset($this->_ibaseConnectionRc);
            unset($this->_ibaseService);
            unset($this->_ibaseTransactionLevel);
        }
    }

    /**
     * {@inheritDoc}
     *
     * Additionally to the standard driver attributes, the attribute
     * {@link AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL} can be used to control the
     * isolation level used for transactions.
     *
     * @param string|int $attribute
     */
    public function setAttribute($attribute, mixed $value): void
    {
        switch ($attribute) {
            case AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL:
                $this->attrDcTransIsolationLevel = $value;
                break;
            case AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT:
                $this->attrDcTransWait = $value;
                break;
            case \PDO::ATTR_AUTOCOMMIT:
                $this->attrAutoCommit = $value;
                break;
        }
    }
    /**
     * {@inheritDoc}
     *
     * @param string|int $attribute
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        switch ($attribute) {
            case AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL:
                return $this->attrDcTransIsolationLevel;
            case AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT:
                return $this->attrDcTransWait;
            case \PDO::ATTR_AUTOCOMMIT:
                return $this->attrAutoCommit;
        }
        return null;
    }

    /**
     * @return false|resource (ibase_pconnect or ibase_connect)
     */
    public function getInterbaseConnectionResource()
    {
        return $this->_ibaseConnectionRc;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return is_resource($this->_ibaseService) ? ibase_server_info($this->_ibaseService, IBASE_SVC_SERVER_VERSION) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql): DriverStatement
    {
        return new Statement($this, $sql);
    }

    /**
     * {@inheritdoc}
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

    /**
     * {@inheritdoc}
     */
    public function exec(string $sql): int
    {
        return $this->prepare($sql)->execute()->rowCount();
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return false;
        }
        if (false == is_string($name)) {
            throw new \InvalidArgumentException(sprintf(
                "Argument \$name must be null or a string. Found: %s",
                ValueFormatter::found($name)
            ));
        }

        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4687',
            'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
        );

        if(\str_starts_with($name, 'SELECT RDB')) {
            $name = $this->query($name)->fetchOne();
        } else {
            $maxGeneratorLength = 31;
            $regex = "/^\w{1,{$maxGeneratorLength}}\$/";
            if (1 !== preg_match($regex, $name)) {
                throw new \UnexpectedValueException(sprintf(
                    "Expects argument \$name to match regular expression '%s'. Found: %s",
                    $regex,
                    ValueFormatter::found($name)
                ));
            }
        }




        $sql = "SELECT GEN_ID({$name}, 0) LAST_VAL FROM RDB\$DATABASE";
        return $this->query($sql)->fetchOne();
    }

    /**
     * @throws Exception
     */
    public function getStartTransactionSql(int $isolationLevel): string
    {
        $result = "";
        match ($isolationLevel) {
            TransactionIsolationLevel::READ_UNCOMMITTED => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ UNCOMMITTED RECORD_VERSION',
            TransactionIsolationLevel::READ_COMMITTED => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ COMMITTED RECORD_VERSION',
            TransactionIsolationLevel::REPEATABLE_READ => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT',
            TransactionIsolationLevel::SERIALIZABLE => $result .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT TABLE STABILITY',
            default => throw new Exception(sprintf(
                "Isolation level %s is not supported",
                ValueFormatter::cast($isolationLevel)
            )),
        };
        if (($this->attrDcTransWait > 0)) {
            $result .= ' WAIT LOCK TIMEOUT ' . $this->attrDcTransWait;
        } elseif  (($this->attrDcTransWait === -1)) {
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
        if ($this->_ibaseTransactionLevel < 1) {
            $this->_ibaseActiveTransaction = $this->createTransaction(true);
            $this->_ibaseTransactionLevel++;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        if ($this->_ibaseTransactionLevel > 0) {
            if (!is_resource($this->_ibaseActiveTransaction)) {
                throw new RuntimeException(sprintf(
                    "No active transaction. \$this->_ibaseTransactionLevel = %d",
                    $this->_ibaseTransactionLevel
                ));
            }
            $success = @ibase_commit_ret($this->_ibaseActiveTransaction);
            if (false == $success) {
                $this->checkLastApiCall();
            }
            $this->_ibaseTransactionLevel--;
        }
        if (0 == $this->_ibaseTransactionLevel) {
            @ibase_commit($this->_ibaseActiveTransaction);
            $this->_ibaseActiveTransaction = $this->createTransaction(true);
        }
        return true;
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started.
     * @throws RuntimeException
     * @return null|bool
     */
    public function autoCommit()
    {
        if ($this->attrAutoCommit && $this->_ibaseTransactionLevel < 1) {
            if (false == is_resource($this->_ibaseActiveTransaction)) {
                throw new RuntimeException(sprintf(
                    "No active transaction. \$this->_ibaseTransactionLevel = %d",
                    $this->_ibaseTransactionLevel
                ));
            }
            $success = @ibase_commit_ret($this->getActiveTransaction());
            if (false == $success) {
                $this->checkLastApiCall();
            }
            return true;
        }
        return null;
    }

    /**
     * {@inheritdoc)
     * @throws RuntimeException
     */
    public function rollBack()
    {
        if ($this->_ibaseTransactionLevel > 0) {
            if (false === is_resource($this->_ibaseActiveTransaction)) {
                throw new RuntimeException(sprintf(
                    "No active transaction. \$this->_ibaseTransactionLevel = %d",
                    $this->_ibaseTransactionLevel
                ));
            }
            $success = @ibase_rollback($this->_ibaseActiveTransaction);
            if (false == $success) {
                $this->checkLastApiCall();
            }
            $this->_ibaseTransactionLevel--;
        }
        $this->_ibaseActiveTransaction = $this->createTransaction(true);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode(): bool|int
    {
        return ibase_errcode();
    }

    /**
     * {@inheritdoc}
     * @return array<string, mixed>
     */
    public function errorInfo(): array
    {
        $errorCode = $this->errorCode();
        if (false !== $errorCode) {
            return [
                'code' => $errorCode,
                'message' => ibase_errmsg(),
            ];
        }
        return [
            'code' => 0,
            'message' => null,
        ];
    }

    /**
     * @throws RuntimeException
     * @return resource
     */
    public function getActiveTransaction()
    {
        if (!is_resource($this->_ibaseConnectionRc)) {
            try {
                if ($this->isPersistent) {
                    $this->_ibaseConnectionRc = @ibase_pconnect( // Notice the "p"
                        $this->connectString,
                        $this->username,
                        $this->password,
                        $this->charset,
                        $this->buffers,
                        $this->dialect
                    );
                } else {
                    $this->_ibaseConnectionRc = @ibase_connect(
                        $this->connectString,
                        $this->username,
                        $this->password,
                        $this->charset,
                        $this->buffers,
                        $this->dialect
                    );
                }
                if (!is_resource($this->_ibaseConnectionRc)) {
                    $this->checkLastApiCall();
                }
                $this->_ibaseActiveTransaction = $this->createTransaction(true);
            } catch (\Exception $e) {
                throw new RuntimeException("Failed to connect", 0, $e);
            }
        }
        if (false == is_resource($this->_ibaseActiveTransaction)) {
            throw new RuntimeException(sprintf(
                "No active transaction. \$this->_ibaseTransactionLevel = %d",
                $this->_ibaseTransactionLevel
            ));
        }
        return $this->_ibaseActiveTransaction;
    }

    /**
     * Checks ibase_error and raises an exception if an error occured
     *
     * @throws Exception
     */
    public function checkLastApiCall($preparedStatement = null, $transaction = null): void
    {
        $lastError = $this->errorInfo();
        if (isset($lastError['code']) && $lastError['code'] !== 0) {
            if (isset($transaction) && $this->_ibaseActiveTransaction < 1) {
                $result = @ibase_rollback($transaction);
                if($result) {
                    $this->_ibaseActiveTransaction = $this->createTransaction(true);
                }
            }

            if($preparedStatement) {
                $result =  @ibase_free_query($preparedStatement);
            }

            throw Exception::fromErrorInfo($lastError);
        }
    }

    /**
     * @param bool $commitDefaultTransaction
     * @return resource The ibase transaction.
     * @throws Exception
     */
    protected function createTransaction($commitDefaultTransaction = true)
    {
        if ($commitDefaultTransaction && is_resource($this->_ibaseConnectionRc)) {
            @ibase_commit($this->_ibaseConnectionRc);
        }
        $sql = $this->getStartTransactionSql($this->attrDcTransIsolationLevel);
        $result = @ibase_query($this->_ibaseConnectionRc, $sql);
        if (!is_resource($result)) {
            $this->checkLastApiCall();
        }
        return $result;
    }

    public function close(): void
    {
            if (is_resource($this->_ibaseActiveTransaction)) {
                if ($this->_ibaseTransactionLevel > 0) {
                    $this->rollBack(); // Auto-rollback explicit transactions
                }
                $this->autoCommit();
                @ibase_commit($this->_ibaseActiveTransaction);
            }
            $success = true;

            if (is_resource($this->_ibaseConnectionRc)) {
                $success = @ibase_close($this->_ibaseConnectionRc);
            }
            if (is_resource($this->_ibaseService)) {
                $success = @ibase_service_detach($this->_ibaseService);
            }
            $this->_ibaseConnectionRc = false;
            $this->_ibaseActiveTransaction  = false;
            $this->_ibaseTransactionLevel = 0;
            if (false === $success) {
               $this->checkLastApiCall();
            }
    }

    /**
     * @param array<int|string, string> $params
     * @return string
     * *@throws HostDbnameRequired
     */
    public static function generateConnectString(array $params): string
    {
        if (isset($params['host'], $params['dbname']) && $params['host'] !== '' && $params['dbname'] !== '') {
            $str = $params['host'];
            if (isset($params['port'])) {
                if ($params['port'] === '' || !is_numeric($params['port'])) {
                    throw HostDbnameRequired::invalidPort();
                }
                $str .= '/' . $params['port'];
            }
            $str .= ':' . $params['dbname'];
            return $str;
        }
        throw HostDbnameRequired::new();
    }

    /**
     * @return false|resource
     */
    public function getNativeConnection()
    {
        return $this->_ibaseConnectionRc;
    }

}
