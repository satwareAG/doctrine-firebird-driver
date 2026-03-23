<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;

/**
 * Firebird-specific Connection wrapper that configures the platform with
 * Firebird-specific options from connection parameters.
 *
 * This is the recommended way to use Firebird-specific configuration options
 * like 'like_cast_length'. It is forward-compatible with DBAL 4.x which removes
 * the VersionAwarePlatformDriver interface.
 *
 * Usage:
 *
 *     $connection = DriverManager::getConnection([
 *         'driver' => 'firebird',
 *         'host' => 'localhost',
 *         'dbname' => '/path/to/database.fdb',
 *         'user' => 'SYSDBA',
 *         'password' => 'masterkey',
 *         'wrapperClass' => FirebirdConnection::class,
 *         'firebird' => [
 *             'like_cast_length' => 4000,
 *         ],
 *     ]);
 */
final class FirebirdConnection extends Connection
{
    private bool $platformConfigured = false;

    /**
     * Gets the DatabasePlatform for the connection and configures it with
     * Firebird-specific options from the connection parameters.
     */
    #[Override]
    public function getDatabasePlatform(): AbstractPlatform
    {
        $platform = parent::getDatabasePlatform();

        // Configure platform with firebird options from connection params (once)
        if (! $this->platformConfigured && $platform instanceof FirebirdPlatform) {
            $params          = $this->getParams();
            $firebirdOptions = $params['firebird'] ?? [];
            if ($firebirdOptions !== []) {
                $platform->setConfiguration(new FirebirdPlatformConfiguration($firebirdOptions));
            }

            $this->platformConfigured = true;
        }

        return $platform;
    }
}
