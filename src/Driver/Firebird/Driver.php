<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\FirebirdConnectString;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\HostDbnameRequired;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;
use SensitiveParameter;

use function fbird_connect;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_pconnect;
use function fbird_server_info;
use function fbird_service_attach;
use function fbird_service_detach;
use function is_resource;
use function stristr;

use const FBIRD_SVC_SERVER_VERSION;

/**
 * A Doctrine DBAL driver for the FirebirdSQL/php-firebird.
 *
 * @psalm-suppress UnusedClass
 */
final class Driver extends FirebirdDriver
{
    /**
     * {@inheritDoc}
     *
     * @return Connection
     */
    #[Override]
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        // Store Firebird-specific options for platform configuration
        $this->firebirdOptions = $params['firebird'] ?? [];

        // Validate configuration early (fail-fast)
        new FirebirdPlatformConfiguration($this->firebirdOptions);

        $host       = $params['host'] ?? 'localhost';
        $username   = $params['user'] ?? 'SYSDBA';
        $password   = $params['password'] ?? 'masterkey';
        $charset    = $params['charset'] ?? 'UTF8';
        $buffers    = $params['buffers'] ?? 0;
        $dialect    = $params['dialect'] ?? 3;
        $persistent = ! empty($params['persistent']);

        $connectString = $this->buildConnectString($params);

        $firebirdService = @fbird_service_attach($host, $username, $password);
        if (! is_resource($firebirdService)) {
            throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
        }

        $serverVersion = fbird_server_info($firebirdService, FBIRD_SVC_SERVER_VERSION);
        if (! fbird_service_detach($firebirdService)) {
            throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
        }

        unset($firebirdService);

        if ($persistent) {
            $connection = @fbird_pconnect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect);
        } else {
            $connection = @fbird_connect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect);
        }

        $notFoundException = null;

        if ($connection === false) {
            $code = (int) fbird_errcode();
            $msg  = (string) fbird_errmsg();
            if ($code !== -902 || stristr($msg, 'no such file or directory') === false) {
                throw Exception::fromErrorInfo($msg, $code);
            }

            $connection        = null;
            $notFoundException = Exception::fromErrorInfo($msg, $code);
        }

        return new Connection($connection, $serverVersion, $persistent, $notFoundException, $params);
    }

    #[Override]
    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }

    /**
     * Returns an appropriate connect string for the given parameters.
     *
     * @param array<string, mixed> $params The connection parameters to return the connect string for.
     *
     * @throws HostDbnameRequired
     */
    private function buildConnectString(array $params): string
    {
        return (string) FirebirdConnectString::fromConnectionParameters($params);
    }
}
