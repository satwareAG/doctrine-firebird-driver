<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Unit\Driver;

use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Connection;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class StatementTest extends TestCase
{
    public function testBasics()
    {
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "foo");
        $this->assertSame(['code' => 0, 'message' => null], $statement->errorInfo());
        $this->assertSame(0, $statement->columnCount());
        $this->assertSame(0, $statement->rowCount());
    }

    public function testFetchReturnsFalseWhenNoConnectionResourceExists()
    {
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "SELECT * FROM dummy");
        $row = $statement->fetch();
        $this->assertSame(false, $row);
    }

    public function testFetchThrowsExceptionWhenFetchModeIsUnsupported()
    {
        $this->expectExceptionMessage("Fetch mode -1 not supported by this driver. Called in method Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement::fetch");
        $this->expectException(Exception::class);
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "SELECT * FROM dummy");
        $statement->fetch(-1);
    }

    public function testFetchAllReturnsEmptyArrayWhenNoConnectionResourceExists()
    {
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "SELECT * FROM dummy");
        $rows = $statement->fetchAll();
        $this->assertSame([], $rows);
    }

    public function testFetchAllThrowsExceptionWhenModeIsPdoFetchInto()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot use \PDO::FETCH_INTO; fetching multiple rows into single object is impossible. Fetch object is: \stdClass");
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "SELECT * FROM dummy");
        $object = new \stdClass;
        $statement->setFetchMode(PDO::FETCH_INTO, $object);
        $statement->fetchAll();
    }

    public function testFetchAllThrowsExceptionWhenModeIsPdoFetchObjAndFetchObjectIsNotAString()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Argument $fetchArgument must - when fetch mode is \PDO::FETCH_OBJ - be null or a string.');
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "SELECT * FROM dummy");
        $object = new \stdClass;
        $statement->fetchAll(PDO::FETCH_OBJ, 1);
    }

    public function testFetchAllThrowsExceptionWhenModeIsPdoFetchColumnAndFetchArgumentIsNotAnInteger()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Argument $fetchArgument must - when fetch mode is \PDO::FETCH_COLUMN - be an integer. Found: (string) "1"');
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "SELECT * FROM dummy");
        $object = new \stdClass;
        $statement->fetchAll(PDO::FETCH_COLUMN, "1");
    }

    public function testFetchAllThrowsExceptionWhenModeIsUnsupported()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Fetch mode -1 not supported by this driver. Called through method Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement::fetchAll");
        $connection = $this->_mockConnection();
        $statement = new Statement($connection, "SELECT * FROM dummy");
        $object = new \stdClass;
        $statement->fetchAll(-1, $object);
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
