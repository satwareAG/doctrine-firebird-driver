<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Satag\DoctrineFirebirdDriver\Driver\AbstractFirebirdDriver;
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
    public const ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL = 'doctrineTransactionIsolationLevel';

    public const ATTR_DOCTRINE_DEFAULT_TRANS_WAIT = 'doctrineTransactionWait';

    /**
     * {@inheritDoc}
     *
     * @return resource
     */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ) {
        $username   = $params['user'] ?? 'SYSDBA';
        $password   = $params['password'] ?? 'masterkey';
        $charset    = $params['charset'] ?? 'UTF8';
        $buffers    = $params['buffers'] ?? null;
        $dialect    = $params['dialect'] ?? 3;
        $persistent = ! empty($params['persistent']);

        if ($persistent) {
            $connection = @fbird_pconnect($connectString, $username, $password, $charset, $charset, $buffers, $dialect);
        } else {
            $connection = @fbird_connect($connectString, $username, $password, $charset, $charset, $buffers, $dialect);
        }

        if ($connection === false) {
            throw Exception::fromErrorInfo(@fbird_errmsg(), @fbird_errcode());
        }

        return $connection;
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
