<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Satag\DoctrineFirebirdDriver\Compat\Override;

/**
 * DBAL Driver Middleware support for the Firebird driver.
 *
 * Allows wrapping the Firebird driver with DBAL middleware (e.g. logging,
 * profiling, retry-on-connection-lost) by implementing the standard
 * {@see MiddlewareInterface} contract introduced in DBAL 3.2.
 *
 * Usage example:
 * ```php
 * $config = new Configuration();
 * $config->setMiddlewares([
 *     new \Doctrine\DBAL\Logging\Middleware($logger),
 *     new FirebirdDriverMiddleware(),
 * ]);
 * ```
 *
 * The returned wrapper delegates all calls to the wrapped driver and
 * correctly handles {@see \Doctrine\DBAL\VersionAwarePlatformDriver} via
 * {@see AbstractDriverMiddleware::createDatabasePlatformForVersion()}.
 */
final class FirebirdDriverMiddleware implements MiddlewareInterface
{
    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
        };
    }
}
