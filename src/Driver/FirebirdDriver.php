<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\Deprecations\Deprecation;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;

use function assert;
use function preg_match;
use function version_compare;

/**
 * Abstract base implementation of the {@see Driver} interface for Firebird based drivers.
 */
abstract class FirebirdDriver implements VersionAwarePlatformDriver
{
    public const ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL = 'doctrineTransactionIsolationLevel';

    public const ATTR_DOCTRINE_DEFAULT_TRANS_WAIT = 'doctrineTransactionWait';

    public const ATTR_AUTOCOMMIT = 'doctrineAutoCommit';

    /**
     * Firebird-specific connection options.
     *
     * @var array<string, mixed>
     */
    protected array $firebirdOptions = [];

     /**
      * {@inheritDoc}
      */
    public function createDatabasePlatformForVersion($version)
    {
        $versionParts = [];
        if (
            preg_match(
                '/^(LI|WI)-([VT])(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+)(?:\.(?P<build>\d+))?)?)?/',
                $version,
                $versionParts,
            ) !== 1
        ) {
            throw Exception::invalidPlatformVersionSpecified(
                $version,
                'LI|WI-V<major_version>.<minor_version>.<patch_version>.<build_version>',
            );
        }

        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? 0;
        $buildVersion = $versionParts['build'] ?? 0;
        $version      = $majorVersion . '.' . $minorVersion . '.' . $patchVersion . '.' . $buildVersion;

        $platform = match (true) {
            version_compare($version, '6.0', '>=') => new Firebird5Platform(),
            version_compare($version, '5.0', '>=') => new Firebird5Platform(),
            version_compare($version, '4.0', '>=') => new Firebird4Platform(),
            version_compare($version, '3.0', '>=') => new Firebird3Platform(),
            default => new FirebirdPlatform(),
        };

        $platform->setConfiguration(new FirebirdPlatformConfiguration($this->firebirdOptions));

        return $platform;
    }

    /**
     * {@inheritDoc}
     *
     * @return FirebirdPlatform
     */
    public function getDatabasePlatform()
    {
        $platform = new FirebirdPlatform();
        $platform->setConfiguration(new FirebirdPlatformConfiguration($this->firebirdOptions));

        return $platform;
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new Firebird\ExceptionConverter();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@link FirebirdPlatform::createSchemaManager()} instead.
     *
     * @return FirebirdSchemaManager
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5458',
            'FirebirdDriver::getSchemaManager() is deprecated.'
            . ' Use FirebirdPlatform::createSchemaManager() instead.',
        );

        assert($platform instanceof FirebirdPlatform);

        return new FirebirdSchemaManager($conn, $platform);
    }
}
