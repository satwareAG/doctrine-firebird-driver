<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement;

/** @covers \Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement */
class StatementTest extends TestCase
{
    public function testBasics(): void
    {
        $connection = $this->_mockConnection();
        $statement  = new Statement($connection, 'foo');
        self::assertIsObject($statement);
    }

    protected function _mockConnection(): Connection
    {
        return $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
