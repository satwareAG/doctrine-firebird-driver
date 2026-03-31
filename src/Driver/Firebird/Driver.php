<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\FirebirdConnectString;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\HostDbnameRequired;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;
use SensitiveParameter;
use Throwable;

use function extension_loaded;
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
     */
    #[Override]
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        if (! extension_loaded('firebird')) {
            throw new Exception('The firebird extension is required for this driver but is not loaded.');
        }

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

        try {
            $firebirdService = fbird_service_attach($host, $username, $password);
        } catch (Throwable $e) {
            throw Exception::fromThrowable($e);
        }

        if (! is_resource($firebirdService)) {
            throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
        }

        $serverVersion = fbird_server_info($firebirdService, FBIRD_SVC_SERVER_VERSION);
        if ($serverVersion === false) {
            throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
        }

        if (! fbird_service_detach($firebirdService)) {
            throw Exception::fromErrorInfo((string) fbird_errmsg(), (int) fbird_errcode());
        }

        unset($firebirdService);

        try {
            if ($persistent) {
                $connection = fbird_pconnect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect);
            } else {
                // Handle "I/O error ... no such file or directory" warning below as a valid case
                // (database doesn't exist yet, will be created by schema tool).
                $connection = fbird_connect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect);
            }
        } catch (Throwable $e) {
            throw Exception::fromThrowable($e);
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
