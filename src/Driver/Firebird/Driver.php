<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Satag\DoctrineFirebirdDriver\Driver\AbstractFirebirdDriver;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\FirebirdConnectString;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\HostDbnameRequired;
use SensitiveParameter;

use function fbird_connect;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_pconnect;

/**
 * A Doctrine DBAL driver for the FirebirdSQL/php-firebird.
 */
final class Driver extends AbstractFirebirdDriver
{
    /**
     * {@inheritDoc}
     *
     * @return Connection
     */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        $host       = $params['host'] ?? null;
        $username   = $params['user'] ?? 'SYSDBA';
        $password   = $params['password'] ?? 'masterkey';
        $charset    = $params['charset'] ?? 'UTF8';
        $buffers    = $params['buffers'] ?? 0;
        $dialect    = $params['dialect'] ?? 3;
        $persistent = ! empty($params['persistent']);

        $connectString = $this->buildConnectString($params);

        $fbirdService = @fbird_service_attach($host, $username, $password);
        if (! is_resource($fbirdService)) {
            throw Exception::fromErrorInfo(@fbird_errmsg(), @fbird_errcode());
        }

        if ($persistent) {
            $connection = @fbird_pconnect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect);
        } else {
            $connection = @fbird_connect($connectString, $username, $password, $charset, (int) $buffers, (int) $dialect);
        }
        if ($connection === false) {
            $code = @fbird_errcode();
            $msg   = @fbird_errmsg();
            if ($code === -902 && stristr($msg, 'no such file or directory')) {
                $connection = null;
            } else {
                throw Exception::fromErrorInfo($msg, $code);
            }

        }


        return new Connection($connection, $fbirdService, $persistent);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }


    /**
     * Returns an appropriate connect string for the given parameters.
     *
     * @param array<string, mixed> $params The connection parameters to return the connect string for.
     *
     * @return string
     * @throws HostDbnameRequired
     */
    private function buildConnectString(array $params): string
    {
        return (string) FirebirdConnectString::fromConnectionParameters($params);
    }

}
