<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Driver;

use Doctrine\DBAL\Driver as DriverInterface;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter;

class DriverTest extends AbstractFirebirdDriverTestCase
{
    public function testGetExceptionConverter(): void
    {
        $driver    = new Driver();
        $converter = $driver->getExceptionConverter();

        self::assertInstanceOf(ExceptionConverter::class, $converter);
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
