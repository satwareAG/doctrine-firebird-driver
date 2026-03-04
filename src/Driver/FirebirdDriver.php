<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
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
use function is_string;
use function preg_match;
use function version_compare;

/**
 * Abstract base implementation of the {@see Driver} interface for Firebird based drivers.
 *
 * This driver is version-aware and provides platform instances appropriate
 * for the connected Firebird server version.
 */
abstract class FirebirdDriver implements Driver, VersionAwarePlatformDriver // @phpstan-ignore-line classImplements.deprecated
{
    public const ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL = 'doctrineTransactionIsolationLevel';

    public const ATTR_DOCTRINE_DEFAULT_TRANS_WAIT = 'doctrineTransactionWait';

    public const ATTR_AUTOCOMMIT = 'doctrineAutoCommit';

    /**
     * Retry DML/DDL on lock conflicts (e.g. objects in use) by forcing a full commit
     */
    public const ATTR_DOCTRINE_RETRY_ON_LOCK = 'doctrineRetryOnLock';

    /**
     * Firebird-specific connection options.
     *
     * @var array<string, mixed>
     */
    protected array $firebirdOptions = [];

    /**
     * Factory method for creating the appropriate platform instance for the given version.
     *
     * Accepts both the legacy Firebird version string format ("LI|WI-V<major>.<minor>.<patch>.<build>")
     * and the plain numeric format returned by newer Firebird Docker images ("<major>.<minor>.<patch>.<build>").
     *
     * @param mixed $version The platform/server version string to evaluate.
     *
     * @throws Exception If the given version string could not be evaluated.
     */
    public function createDatabasePlatformForVersion(mixed $version): AbstractPlatform
    {
        if (! is_string($version)) {
            throw Exception::invalidPlatformVersionSpecified(
                (string) $version,
                'LI|WI-V<major_version>.<minor_version>.<patch_version>.<build_version>',
            );
        }

        $versionParts = [];

        // Accept legacy format: "LI-V3.0.13.33818", "WI-V4.0.5.3116", "LI-T3.0.0.29316"
        if (
            preg_match(
                '/^(LI|WI)-([VT])(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+)(?:\.(?P<build>\d+))?)?)?/',
                $version,
                $versionParts,
            ) === 1
        ) {
            $majorVersion = $versionParts['major'];
            $minorVersion = $versionParts['minor'] ?? 0;
            $patchVersion = $versionParts['patch'] ?? 0;
            $buildVersion = $versionParts['build'] ?? 0;
        } elseif (
            // Accept plain numeric format returned by newer Firebird Docker images: "5.0.3.1683", "3.0.13.33818"
            preg_match(
                '/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+)(?:\.(?P<build>\d+))?)?)?$/',
                $version,
                $versionParts,
            ) === 1
        ) {
            $majorVersion = $versionParts['major'];
            $minorVersion = $versionParts['minor'] ?? 0;
            $patchVersion = $versionParts['patch'] ?? 0;
            $buildVersion = $versionParts['build'] ?? 0;
        } else {
            throw Exception::invalidPlatformVersionSpecified(
                $version,
                'LI|WI-V<major_version>.<minor_version>.<patch_version>.<build_version>',
            );
        }

        $version = $majorVersion . '.' . $minorVersion . '.' . $patchVersion . '.' . $buildVersion;

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

    public function getDatabasePlatform(): FirebirdPlatform
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
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): FirebirdSchemaManager
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
