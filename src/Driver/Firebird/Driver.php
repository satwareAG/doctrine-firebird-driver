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
use function fbird_server_version;
use function stristr;

use const FBIRD_CONNECT_FORCE_NEW;

/**
 * A Doctrine DBAL driver for the FirebirdSQL/php-firebird.
 *
 * @psalm-suppress UnusedClass
 * @psalm-suppress DeprecatedInterface
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
        $role       = $params['role'] ?? '';
        $persistent = ! empty($params['persistent']);
        $forceNew   = ! empty($params['forceNewConnection']);

        $connectString = $this->buildConnectString($params);

        // R4: Use fbird_server_version() after connect instead of the service-attach
        // roundtrip (fbird_service_attach + fbird_server_info + fbird_service_detach).
        // The service-attach path is fragile: it can fail silently with empty errmsg
        // on some Firebird Docker images (service_mgr not defined), and it requires
        // a separate network roundtrip before the main connection.
        $serverVersion = '3.0'; // Default minimum; overwritten after successful connect.

        try {
            if ($persistent) {
                $connection = @fbird_pconnect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect, $role);
            } elseif ($forceNew) {
                $connection = @fbird_connect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect, $role, FBIRD_CONNECT_FORCE_NEW);
            } else {
                // Suppress "I/O error ... no such file or directory" warning when the
                // database doesn't exist yet. The schema tool creates it after the
                // initial connect fails; we throw a structured exception below.
                $connection = @fbird_connect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect, $role);
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
        } else {
            // R4: Get server version from the established connection.
            // fbird_server_version() is a v13.0.0 function that returns the raw
            // isc_info_firebird_version buffer (binary format: 1 byte impl type,
            // 1 byte length, N bytes version string, then more protocol layers).
            // Extract just the first version string for platform selection.
            $rawVersion = @fbird_server_version($connection);
            if ($rawVersion !== false && strlen($rawVersion) >= 2) {
                $len = ord($rawVersion[1]);
                if ($len > 0 && strlen($rawVersion) >= 2 + $len) {
                    $serverVersion = substr($rawVersion, 2, $len);
                }
            }
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
