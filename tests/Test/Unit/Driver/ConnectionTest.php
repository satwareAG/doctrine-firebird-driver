<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;

class ConnectionTest extends TestCase
{
    public function testQuote(): void
    {
        $connection = $this->_createConnectionThroughReflection();
        self::assertSame("'key'", $connection->quote('key'));
        self::assertSame("'''key'", $connection->quote("'key"));
    }

    private function _createConnectionThroughReflection(): Connection
    {
        $reflectionClass = new ReflectionClass(Connection::class);

        return $reflectionClass->newInstanceWithoutConstructor();
    }
}
