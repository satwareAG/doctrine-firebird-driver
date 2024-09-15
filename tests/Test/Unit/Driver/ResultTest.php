<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Result;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 *
 * @covers \Satag\DoctrineFirebirdDriver\Driver\Firebird\Result
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
