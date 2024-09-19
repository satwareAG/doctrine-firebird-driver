<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Satag\DoctrineFirebirdDriver\Driver\AbstractFirebirdDriver;
use SensitiveParameter;

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
        $this->setDriverOptions($params);
        $username = $params['user'] ?? '';
        $password = $params['password'] ?? '';


        return new Connection(
            $params,
            $username,
            $password,
            $this->getDriverOptions(), // Sanitized
        );
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
