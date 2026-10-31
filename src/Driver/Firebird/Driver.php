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
use function preg_match;
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
            $connection = $this->establishNativeLink(
                $connectString,
                $username,
                $password,
                $charset,
                (int) $buffers,
                (int) $dialect,
                $role,
                $persistent,
                $forceNew,
            );
        } catch (Throwable $e) {
            throw Exception::fromThrowable($e);
        }

        // Issue #162: give the connection a factory that can re-establish the
        // native link with identical parameters when the underlying attachment
        // dies underneath it. Credentials are captured in the closure only -
        // same lifetime as the connection object itself.
        $reconnectFactory = fn (): mixed => $this->establishNativeLink(
            $connectString,
            $username,
            $password,
            $charset,
            (int) $buffers,
            (int) $dialect,
            $role,
            $persistent,
            $forceNew,
        );

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
            // fbird_server_version() (v13.0.0+) returns the server version as a
            // string, but the shape varies by extension release: clean version
            // string since v13.3.1 (php-firebird #696), clump-garbled bytes on
            // older releases. Scan defensively for the first version-like token;
            // keep the '3.0' default when nothing parseable is found (#257).
            $parsedVersion = self::extractServerVersionToken(@fbird_server_version($connection));
            if ($parsedVersion !== null) {
                $serverVersion = $parsedVersion;
            }
        }

        return new Connection($connection, $serverVersion, $persistent, $notFoundException, $params, $reconnectFactory);
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

    /**
     * Open the native fbird link. Single source of truth for the initial
     * connect AND dead-link re-establishment (#162).
     *
     * @return mixed \Firebird\Connection on success, false when the extension
     *                reports failure in non-throwing mode
     *
     * @throws Throwable In exception mode (php-firebird v8.0.0+).
     */
    private function establishNativeLink(
        string $connectString,
        #[SensitiveParameter]
        string $username,
        #[SensitiveParameter]
        string $password,
        string $charset,
        int $buffers,
        int $dialect,
        string $role,
        bool $persistent,
        bool $forceNew,
    ): mixed {
        if ($persistent) {
            return @fbird_pconnect($connectString, $username, $password, $charset, $buffers, $dialect, $role);
        }

        if ($forceNew) {
            return @fbird_connect($connectString, $username, $password, $charset, $buffers, $dialect, $role, FBIRD_CONNECT_FORCE_NEW);
        }

        // Suppress "I/O error ... no such file or directory" warning when the
        // database doesn't exist yet. The schema tool creates it after the
        // initial connect fails; connect() throws a structured exception then.
        return @fbird_connect($connectString, $username, $password, $charset, $buffers, $dialect, $role);
    }

    /**
     * Extract the first version-like token from a fbird_server_version() return.
     *
     * The return shape changed across extension releases: raw
     * isc_info_firebird_version buffer bytes, clump-garbled output with inner
     * length prefixes (client-library dependent, pre-v13.3.1), and the clean
     * server version string ("LI-V3.0.14.33856 Firebird 3.0", v13.3.1+,
     * php-firebird #696). The connected server's own version digits are the
     * first version-like token in every shape, so a defensive regex scan stays
     * stable regardless of client library - byte-offset slicing does not.
     *
     * @return string|null The token ("major.minor[.patch[.build]]"), or null
     *                     when the input carries no parseable version (the
     *                     caller then keeps its '3.0' minimum default).
     */
    private static function extractServerVersionToken(string|false $rawVersion): string|null
    {
        if ($rawVersion === false || $rawVersion === '') {
            return null;
        }

        if (preg_match('/V?(\d+\.\d+(\.\d+)?(\.\d+)?)/', $rawVersion, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
