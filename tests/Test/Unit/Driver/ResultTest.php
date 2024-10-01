<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Result;

/** @covers \Satag\DoctrineFirebirdDriver\Driver\Firebird\Result */
class ResultTest extends TestCase
{
    public function testBasics(): void
    {
        $connection = $this->_mockConnection();
        $resource = null;
        $result     = new Result($resource, $connection, 0, 0);

        self::assertSame(0, $result->columnCount());
        self::assertSame(0, $result->rowCount());

        self::assertFalse($result->fetchNumeric());
        self::assertFalse($result->fetchAssociative());
    }

    protected function _mockConnection(): Connection
    {
        return $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
