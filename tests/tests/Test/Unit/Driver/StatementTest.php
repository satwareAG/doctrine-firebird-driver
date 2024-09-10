<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Unit\Driver;

use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Connection;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @covers \Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement
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
