<?php
namespace Kafoso\DoctrineFirebirdDriver\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Kafoso\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use Kafoso\DoctrineFirebirdDriver\Schema\FirebirdInterbaseSchemaManager;

abstract class AbstractFirebirdInterbaseDriver implements Driver, ExceptionConverterDriver, VersionAwarePlatformDriver
{
    const ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL = 'doctrineTransactionIsolationLevel';

    const ATTR_DOCTRINE_DEFAULT_TRANS_WAIT = 'doctrineTransactionWait';

    private $_driverOptions = [];

    /**
     * {@inheritdoc}
     */
    public function createDatabasePlatformForVersion($version)
    {
        if (
            ! preg_match(
                '/^(LI|WI)-(V|T)(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+)(?:\.(?P<build>\d+))?)?)?/',
                $version,
                $versionParts
            )
        ) {
            throw Exception::invalidPlatformVersionSpecified(
                $version,
                'LI|WI-V<major_version>.<minor_version>.<patch_version>.<build_version>'
            );
        }
        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? 0;
        $buildVersion = $versionParts['build'] ?? 0;
        $version      = $majorVersion . '.' . $minorVersion . '.' . $patchVersion . '.' . $buildVersion;

        switch (true) {
            case version_compare($version, '3.0', '>='):
                return new Firebird3Platform();
            default:
                return new FirebirdInterbasePlatform();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function convertException($message, \Doctrine\DBAL\Driver\DriverException $exception)
    {
        $message = 'Error ' . $exception->getErrorCode() . ': ' . $message;
        switch ($exception->getErrorCode()) {
            case -104:
                return new \Doctrine\DBAL\Exception\SyntaxErrorException($message, $exception);
            case -204:
                if (preg_match('/.*(dynamic sql error).*(table unknown).*/i', $message)) {
                    return new \Doctrine\DBAL\Exception\TableNotFoundException($message, $exception);
                }
                if (preg_match('/.*(dynamic sql error).*(ambiguous field name).*/i', $message)) {
                    return new \Doctrine\DBAL\Exception\NonUniqueFieldNameException($message, $exception);
                }
                break;
            case -206:
                if (preg_match('/.*(dynamic sql error).*(table unknown).*/i', $message)) {
                    return new \Doctrine\DBAL\Exception\InvalidFieldNameException($message, $exception);
                }
                if (preg_match('/.*(dynamic sql error).*(column unknown).*/i', $message)) {
                    return new \Doctrine\DBAL\Exception\InvalidFieldNameException($message, $exception);
                }
                break;
            case -803:
                return new \Doctrine\DBAL\Exception\UniqueConstraintViolationException($message, $exception);
            case -530:
                return new \Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException($message, $exception);
            case -607:
                if (preg_match('/.*(unsuccessful metadata update Table).*(already exists).*/i', $message)) {
                    return new \Doctrine\DBAL\Exception\TableExistsException($message, $exception);
                }
                break;
            case -902:
                return new \Doctrine\DBAL\Exception\ConnectionException($message, $exception);
        }
        return new Exception\DriverException($message, $exception);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setDriverOption($key, $value)
    {
        if (trim($key) && in_array($key, self::getDriverOptionKeys())) {
            $this->_driverOptions[$key] = $value;
        }
        return $this;
    }

    /**
     * @param array $options
     * @return self
     */
    public function setDriverOptions($options)
    {
        if (is_array($options)) {
            foreach ($options as $k => $v) {
                $this->setDriverOption($k, $v);
            }
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();
        return $params['dbname'];
    }

    /**
     * {@inheritdoc}
     * @return FirebirdInterbasePlatform
     */
    public function getDatabasePlatform()
    {
        return new FirebirdInterbasePlatform();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getDriverOption($key)
    {
        if (trim($key) && in_array($key, self::getDriverOptionKeys())) {
            return $this->_driverOptions[$key];
        }
        return null;
    }

    /**
     * @return array
     */
    public function getDriverOptions()
    {
        return $this->_driverOptions;
    }

    /**
     * {@inheritdoc}
     * @return FirebirdInterbaseSchemaManager
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new FirebirdInterbaseSchemaManager($conn);
    }

    /**
     * @return array
     */
    public static function getDriverOptionKeys()
    {
        return [
            self::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            self::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT,
            \PDO::ATTR_AUTOCOMMIT,
        ];
    }
}
