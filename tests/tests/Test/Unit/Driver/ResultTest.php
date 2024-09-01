<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Unit\Driver;

use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Connection;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Result;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @covers \Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Result
 */
class ResultTest extends TestCase
{
    public function testBasics()
    {
        $connection = $this->_mockConnection();
        $result = new Result(null, $connection, 0, 0);

        $this->assertSame(0, $result->columnCount());
        $this->assertSame(0, $result->rowCount());

        $this->assertFalse($result->fetchNumeric());
        $this->assertFalse($result->fetchAssociative());
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
