<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\SQL\Parser;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\Deprecations\Deprecation;
use InvalidArgumentException;
use Satag\DoctrineFirebirdDriver\Driver\AbstractFirebirdDriver;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\HostDbnameRequired;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use PDO;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

use function addcslashes;
use function get_resource_type;
use function fbird_close;
use function fbird_commit;
use function fbird_commit_ret;
use function fbird_connect;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_pconnect;
use function fbird_query;
use function fbird_rollback;
use function fbird_server_info;
use function fbird_service_attach;
use function fbird_service_detach;
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
    private ?string $connectionInsertColumn = null;

    private $connectionInsertId = null;
    private ?string $connectionInsertTable = null;

    private Parser $parser;

    /** @var false|resource|null (fbird_pconnect or fbird_connect) */
    private $fbirdConnectionRc = null;

    /** @var false|resource|null */
    private $fbirdService = null;

    private int $fbirdTransactionLevel = 0;

    /** @var false|resource|null */
    private $fbirdActiveTransaction = false;

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
    private static bool $initialIbaseCloseCalled = false;

    private bool $isPrivileged;

    static protected ?int $lastInsertIdentityId = null;

    static protected array $lastInsertIdenties = [];
    private static ?string $lastInsertSequence = null;
    private static array $lastInsertSequences =  [];


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
        $this->parser        = new Parser(false);
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

        $this->fbirdService = @fbird_service_attach($this->host, $this->username, $this->password);
        if (! is_resource($this->fbirdService)) {
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
        } catch (DriverException) {
            $this->fbirdConnectionRc     = null;
            $this->fbirdService          = null;
            $this->fbirdTransactionLevel = 0;
        }
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

    public function getConnectionInsertColumn(): ?string
    {
        return $this->connectionInsertColumn;
    }

    /**
     * @return null
     */
    public function getConnectionInsertId()
    {
        return $this->connectionInsertId;
    }

    /** @return false|resource (fbird_pconnect or fbird_connect) */
    public function getInterbaseConnectionResource()
    {
        return $this->fbirdConnectionRc;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return is_resource($this->fbirdService) ? fbird_server_info($this->fbirdService, IBASE_SVC_SERVER_VERSION) : '';
    }

    public function requiresQueryForServerVersion(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function prepare(string $sql): DriverStatement
    {
        $visitor = new ConvertParameters();

        $this->parser->parse($sql, $visitor);

        return new Statement($this, @fbird_prepare(
            $this->fbirdConnectionRc,
            $this->getActiveTransaction(),
            $visitor->getSQL()
        ), $visitor->getParameterMap());
    }

    public function setConnectionInsertTableColumn($table, $column)
    {
        $this->connectionInsertTable = $table;
        $this->connectionInsertColumn = $column;
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
        if($name === null && $this->getConnectionInsertId() !== null ) {
            return $this->getConnectionInsertId() ;
        }

        if ($name === null) {
            return false;
        }
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4687',
            'The usage of Connection::lastInsertId() with a sequence name is deprecated.',
        );

        if (str_contains((string)$name, '.')) {
            list($table, $column) = preg_split('/\./', $name);
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

        $sql = 'SELECT GEN_ID(' . $name . ', 0) LAST_VAL FROM RDB$DATABASE';
        $lastVal = $this->query($sql)->fetchOne();
        return $lastVal === 0 ? false : $lastVal;
    }

    public function setLastInsertId(int $id)
    {
        $this->connectionInsertId = $id;
    }

    /** @throws DriverException */
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
            default => throw new DriverException(sprintf(
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
    public function beginTransaction(): bool
    {
        if ($this->fbirdTransactionLevel < 1) {
            $this->fbirdActiveTransaction = $this->createTransaction(true);
            $this->fbirdTransactionLevel++;
        }

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

        return true;
    }

    /**
     * Commits the transaction if autocommit is enabled no explicte transaction has been started.
     *
     * @throws RuntimeException|Exception
     */
    public function autoCommit(): bool|null
    {
        if ($this->attrAutoCommit && $this->fbirdTransactionLevel < 1) {
            if (is_resource($this->fbirdActiveTransaction) === false) {
                throw new RuntimeException(sprintf(
                    'No active transaction. $this->_fbirdTransactionLevel = %d',
                    $this->fbirdTransactionLevel,
                ));
            }

            $success = @fbird_commit_ret($this->getActiveTransaction());
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
     * @return resource
     *
     * @throws RuntimeException|Exception
     */
    public function getActiveTransaction()
    {
        if (! is_resource($this->fbirdConnectionRc)) {
            try {
                if ($this->isPersistent) {
                    $this->fbirdConnectionRc = @fbird_pconnect( // Notice the "p"
                        $this->connectString,
                        $this->username,
                        $this->password,
                        $this->charset,
                        $this->buffers,
                        $this->dialect,
                    );
                } else {
                    $this->fbirdConnectionRc = @fbird_connect(
                        $this->connectString,
                        $this->username,
                        $this->password,
                        $this->charset,
                        $this->buffers,
                        $this->dialect,
                    );
                }

                if (! is_resource($this->fbirdConnectionRc)) {
                    $this->checkLastApiCall();
                }

                $this->fbirdActiveTransaction = $this->createTransaction(true);
            } catch (Throwable $e) {
                throw $e;
            }
        }

        if (is_resource($this->fbirdActiveTransaction) === false) {
            throw new RuntimeException(sprintf(
                'No active transaction. $this->_fbirdTransactionLevel = %d',
                $this->fbirdTransactionLevel,
            ));
        }

        return $this->fbirdActiveTransaction;
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

        throw DriverException::fromErrorInfo($lastError);
    }

    /**
     * @return resource The fbird transaction.
     *
     * @throws DriverException
     */
    protected function createTransaction(bool $commitDefaultTransaction = true)
    {
        if ($commitDefaultTransaction && is_resource($this->fbirdConnectionRc)) {
            @fbird_commit($this->fbirdConnectionRc);
        }

        $sql    = $this->getStartTransactionSql($this->attrDcTransIsolationLevel);
        if (!is_resource($this->fbirdConnectionRc) || get_resource_type($this->fbirdConnectionRc) === 'Unknown') {
            $this->checkLastApiCall();
        }

        $result = @fbird_query($this->fbirdConnectionRc, $sql);
        if (! is_resource($result)) {
            $this->checkLastApiCall();
        }

        return $result;
    }

    /**
     * @throws DriverException
     */
    public function close(): void
    {
        if (!self::$initialIbaseCloseCalled) {
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
                    is_resource($this->fbirdConnectionRc)
                && get_resource_type($this->fbirdConnectionRc) !== 'Unknown'
        ) {
            $success = @fbird_close($this->fbirdConnectionRc);
        }

        if (is_resource($this->fbirdService)) {
            @fbird_service_detach($this->fbirdService);
            $success = @fbird_close($this->fbirdService);
        }

            $this->fbirdConnectionRc      = null;
            $this->fbirdActiveTransaction = null;
            $this->fbirdTransactionLevel  = 0;

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
        return $this->fbirdConnectionRc;
    }

    public static function getTableNameFromInsert($sql): ?string
    {
        if (preg_match('/INSERT INTO\s+([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
