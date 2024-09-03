<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration\Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Result;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement;
use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;


/**
 * @runTestsInSeparateProcesses
 */
class StatementTest extends AbstractIntegrationTestCase
{
    public function testFetchWorks()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT * FROM Album";
        $statement = new Statement($connection, $sql);

        $result = $statement->execute();
        $row = $result->fetchAssociative();
        $this->assertSame(1, $row['ID']);
        $this->assertSame('2017-01-01 15:00:00', $row['TIMECREATED']);
        $this->assertSame('...Baby One More Time', $row['NAME']);
        $this->assertSame(2, $row['ARTIST_ID']);

        $result = $statement->execute();
        $row = $result->fetchNumeric();
        $this->assertSame(1, $row[0]);
        $this->assertSame('2017-01-01 15:00:00', $row[1]);
        $this->assertSame('...Baby One More Time', $row[2]);
        $this->assertSame(2, $row[3]);

    }

    public function testFetchAllWorks()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT * FROM Album";
        $statement = new Statement($connection, $sql);

        $rows = $statement->execute()->fetchAllAssociative();
        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertIsArray($rows[1]);
        $this->assertSame(1, $rows[0]['ID'] ?? false);
        $this->assertSame('2017-01-01 15:00:00', $rows[0]['TIMECREATED'] ?? false);
        $this->assertSame('...Baby One More Time', $rows[0]['NAME'] ?? false);
        $this->assertSame(2, $rows[0]['ARTIST_ID'] ?? false);
        $this->assertSame(2, $rows[1]['ID'] ?? false);
        $this->assertSame('2017-01-01 15:00:00', $rows[1]['TIMECREATED'] ?? false);
        $this->assertSame('Dark Horse', $rows[1]['NAME'] ?? false);
        $this->assertSame(3, $rows[1]['ARTIST_ID'] ?? false);

        $rows = $statement->execute()->fetchAllNumeric();
        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertIsArray($rows[1]);
        $this->assertSame(1, $rows[0][0] ?? false);
        $this->assertSame('2017-01-01 15:00:00', $rows[0][1] ?? false);
        $this->assertSame('...Baby One More Time', $rows[0][2] ?? false);
        $this->assertSame(2, $rows[0][3] ?? false);
        $this->assertSame(2, $rows[1][0] ?? false);
        $this->assertSame('2017-01-01 15:00:00', $rows[1][1] ?? false);
        $this->assertSame('Dark Horse', $rows[1][2] ?? false);
        $this->assertSame(3, $rows[1][3] ?? false);

    }

    public function testFetchColumnWorks()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT * FROM Album";
        $statement = new Statement($connection, $sql);

        $result = $statement->execute();
        $column = $result->fetchNumeric();
        $this->assertSame(1, $column[0]);


        $this->assertSame('2017-01-01 15:00:00', $column[1]);

        $this->assertSame('...Baby One More Time', $column[2]);

        $this->assertSame(2, $column[3]);
    }

    public function testGetIteratorWorks()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT * FROM Album";
        $statement = new Statement($connection, $sql);
        $result = $statement->execute()->fetchAllAssociative();
        $array = [];
        foreach ($result as $row) {
            $array[] = $row;
        }
        $this->assertCount(2, $array);
        $this->assertIsArray($array[0]);
        $this->assertIsArray($array[1]);
        $this->assertSame(1, $array[0]['ID'] ?? false);
        $this->assertSame(2, $array[1]['ID'] ?? false);
    }

    public function testExecuteWorks()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT * FROM Album";
        $statement = new Statement($connection, $sql);
        $this->assertInstanceOf(Result::class, $statement->execute());
    }

    public function testExecuteWorksWithParameters()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT * FROM Album WHERE ID = ?";
        $statement = new Statement($connection, $sql);
        $this->assertInstanceOf(Result::class, $statement->execute([1]));
    }

    public function testExecuteThrowsExceptionWhenSQLIsInvalid()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $statement = new Statement($connection, "SELECT 1");
        try {
            $statement->execute();
        } catch (\Throwable $t) {
            $this->assertSame(Exception::class, $t::class);
            $this->assertSame(-104, $t->getCode());
            $this->assertSame("Failed to perform `doDirectExec`: Dynamic SQL Error SQL error code = -104 Unexpected end of command - line 1, column 8 ", $t->getMessage());
            $this->assertNull($t->getPrevious());
            $this->assertSame(-104, $t->getCode());
            $this->assertNull($t->getSQLState());
            return;
        }
        $this->fail("Exception was never thrown");
    }

    public function testExecuteThrowsExceptionWhenParameterizedSQLIsInvalid()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $statement = new Statement($connection, "SELECT ?");
        $variable = "foo";
        $statement->bindParam(0, $variable);
        try {
            $statement->execute();
        } catch (\Throwable $t) {
            $this->assertSame(Exception::class, $t::class);
            $this->assertSame(-104, $t->getCode());
            $this->assertSame("Failed to perform `doExecPrepared`: Dynamic SQL Error SQL error code = -104 Unexpected end of command - line 1, column 8 ", $t->getMessage());
            $this->assertNull($t->getPrevious());
            $this->assertSame(-104, $t->getCode());
            $this->assertNull($t->getSQLState());
            return;
        }
        $this->fail("Exception was never thrown");
    }

    public function testBindValueWorks()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT ID FROM Album WHERE ID = ?";
        $statement = new Statement($connection, $sql);
        $statement->bindValue(0, 2);
        $result = $statement->execute();
        $value = $result->fetchOne();
        $this->assertSame(2, $value);
    }

    public function testBindParamWorks()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $sql = "SELECT ID FROM Album WHERE ID = :ID";
        $statement = new Statement($connection, $sql);
        $id = 2;
        $statement->bindParam(':ID', $id);
        $value = $statement->execute()->fetchOne();
        $this->assertSame(2, $value);
    }
}
