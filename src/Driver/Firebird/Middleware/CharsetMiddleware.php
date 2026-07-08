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
 * Coverage (every SQL-carrying driver entry point):
 *   - CharsetConnectionMiddleware::prepare()  re-encodes the SQL body (covers
 *     QueryBuilder literal fragments like ->andWhere("name LIKE '%Müller%'")
 *     and other inline string literals that bypass bindValue()) AND wraps the
 *     returned Statement in CharsetStatementMiddleware for bound-param encoding.
 *   - CharsetConnectionMiddleware::query()    re-encodes the SQL body AND wraps
 *     the Result in CharsetResultMiddleware (closes the GH-116 parameterless
 *     SELECT path: DBAL Connection::executeQuery() shortcut).
 *   - CharsetConnectionMiddleware::exec()     re-encodes the SQL body for
 *     parameterless DML (DBAL Connection::executeStatement() shortcut).
 *   - CharsetConnectionMiddleware::quote()    re-encodes scalar string inputs.
 *   - CharsetStatementMiddleware::bindValue()/execute()   re-encodes bound
 *     parameters (covers QueryBuilder ->setParameter('search', '%Faß%')).
 *   - CharsetResultMiddleware::fetch*()       decodes string and TEXT BLOB
 *     results back to the PHP encoding.
 *
 * Non-charset-aware escape hatches (bypass this middleware entirely; callers
 * must mb_convert_encoding values manually):
 *   - Driver\Firebird\Connection::executeAuto()
 *   - Driver\Firebird\Connection::queryInTransaction()
 *   - Driver\Firebird\Connection::createBatch() / executeBatch()
 *   - Driver\Firebird\Connection::getNativeConnection() (direct fbird_* calls)
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
 *   PHP ($phpEncoding) → CharsetConnectionMiddleware re-encodes SQL body
 *                     → CharsetStatementMiddleware::bindValue() re-encodes bound params
 *                     → $databaseEncoding → Firebird
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
