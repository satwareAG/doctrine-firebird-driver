<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * 
 * @covers \Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement
 */
class StatementTest extends TestCase
{
    public function testBasics()
    {
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "foo");
        $this->assertIsObject($statement);
    }

    protected function _mockConnection(): Connection
    {
        $connection = $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        return $connection;
    }
}
