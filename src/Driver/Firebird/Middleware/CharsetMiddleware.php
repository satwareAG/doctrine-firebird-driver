<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use SensitiveParameter;

/**
 * DBAL Middleware that makes non-UTF-8 Firebird wire encoding fully transparent.
 *
 * Firebird databases are often configured with a non-UTF-8 charset (e.g.
 * ISO8859_1 / Windows-1252, WIN1252). This middleware transparently converts
 * string values between the PHP application encoding (default: UTF-8) and the
 * Firebird wire encoding (default: Windows-1252) at the DBAL driver level.
 *
 * Usage with DoctrineBundle (service.xml / services.yaml):
 * ```xml
 * <service id="Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware">
 *     <tag name="doctrine.middleware"/>
 * </service>
 * ```
 *
 * Usage with custom encodings:
 * ```php
 * $middleware = new CharsetMiddleware(databaseEncoding: 'ISO-8859-1', phpEncoding: 'UTF-8');
 * $config->setMiddlewares([$middleware]);
 * ```
 *
 * Flow:
 *   PHP ($phpEncoding) → CharsetStatementMiddleware::bindValue() → $databaseEncoding → Firebird
 *   Firebird → $databaseEncoding → CharsetResultMiddleware::fetch*() → $phpEncoding → PHP
 */
/** @psalm-suppress UnusedClass — used by downstream consumers or via DI service registration */
final class CharsetMiddleware implements MiddlewareInterface
{
    /**
     * @param string $databaseEncoding The encoding used by the Firebird database on the wire
     *                                 (e.g. 'Windows-1252' for ISO8859_1/WIN1252 charset).
     * @param string $phpEncoding      The encoding used by the PHP application (typically 'UTF-8').
     */
    public function __construct(
        private readonly string $databaseEncoding = 'Windows-1252',
        private readonly string $phpEncoding = 'UTF-8',
    ) {
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $databaseEncoding = $this->databaseEncoding;
        $phpEncoding      = $this->phpEncoding;

        /** @psalm-suppress DeprecatedInterface */
        return new class ($driver, $databaseEncoding, $phpEncoding) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $driver,
                private readonly string $databaseEncoding,
                private readonly string $phpEncoding,
            ) {
                parent::__construct($driver);
            }

            /**
             * Connect and wrap the native connection in CharsetConnectionMiddleware.
             *
             * {@inheritDoc}
             */
            #[Override]
            public function connect(
                #[SensitiveParameter]
                array $params,
            ): CharsetConnectionMiddleware {
                return new CharsetConnectionMiddleware(
                    parent::connect($params),
                    $this->databaseEncoding,
                    $this->phpEncoding,
                );
            }
        };
    }
}
