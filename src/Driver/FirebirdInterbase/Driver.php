<?php
namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Kafoso\DoctrineFirebirdDriver\Driver\AbstractFirebirdInterbaseDriver;
use SensitiveParameter;

final class Driver extends AbstractFirebirdInterbaseDriver
{
    /**
     * {@inheritDoc}
     *
     * @return Connection
     */
    public function connect(
        #[SensitiveParameter]
        array $params
    ) {
        $this->setDriverOptions($params);
        $username    = $params['user'] ?? '';
        $password    = $params['password'] ?? '';

        return new Connection(
            $params,
            $username,
            $password,
            $this->getDriverOptions() // Sanitized
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'FirebirdInterbase';
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
