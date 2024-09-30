<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Driver;

use Doctrine\DBAL\Driver as DriverInterface;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

class DriverTest extends AbstractFirebirdDriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
