<?php
namespace Kafoso\DoctrineFirebirdDriver\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Kafoso\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use Kafoso\DoctrineFirebirdDriver\Schema\FirebirdInterbaseSchemaManager;

abstract class AbstractFirebirdInterbaseDriver implements VersionAwarePlatformDriver
{
    const ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL = 'doctrineTransactionIsolationLevel';

    const ATTR_DOCTRINE_DEFAULT_TRANS_WAIT = 'doctrineTransactionWait';

    /**
     * @var array<string|int, string>
     */
    private $_driverOptions = [];

    /**
     * {@inheritdoc}
     */
    public function createDatabasePlatformForVersion($version)
    {
        if (
            1 !== preg_match(
                '/^(LI|WI)-([VT])(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+)(?:\.(?P<build>\d+))?)?)?/',
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

        return match (true) {
            version_compare($version, '3.0', '>=') => new Firebird3Platform(),
            default => new FirebirdInterbasePlatform(),
        };
    }


    /**
     * @param int|string $key
     * @return self
     */
    public function setDriverOption($key, mixed $value)
    {
        if (in_array($key, self::getDriverOptionKeys(), true)) {
            $this->_driverOptions[$key] = $value;
        }
        return $this;
    }

    /**
     * @param array<string|int, string> $options
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
     * @return FirebirdInterbasePlatform
     */
    public function getDatabasePlatform()
    {
        return new FirebirdInterbasePlatform();
    }

    /**
     * @return array<int|string, string>
     */
    public function getDriverOptions()
    {
        return $this->_driverOptions;
    }

    /**
     * {@inheritdoc}
     * @return FirebirdInterbaseSchemaManager
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
    {
        return new FirebirdInterbaseSchemaManager($conn, $platform);
    }

    /**
     * @return array<int|string>
     */
    public static function getDriverOptionKeys()
    {
        return [
            self::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            self::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT,
            \PDO::ATTR_AUTOCOMMIT,
        ];
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new FirebirdInterbase\ExceptionConverter();
    }
}
