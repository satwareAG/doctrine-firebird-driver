<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Exception\SyntaxErrorException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Result;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

/**
 *
 */
class StatementTest extends AbstractIntegrationTestCase
{
    public function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();
    }
    public function testFetchWorks()
    {
        $statement = $this->connection->prepare("SELECT * FROM Album");
        $result = $statement->execute();
        $row = $result->fetchAssociative();
        $this->assertSame(1, $row['ID']);
        $this->assertSame('2017-01-01 15:00:00', $row['TIMECREATED']);
        $this->assertSame('...Baby One More Time', $row['NAME']);
        $this->assertSame(2, $row['ARTIST_ID']);

        $result = $statement->execute();
        $row = $result->fetchNumeric();
        $this->assertSame(1, $row[0]);
        $this->assertSame('2017-01-01 15:00:00', $row[2]);
        $this->assertSame('...Baby One More Time', $row[3]);
        $this->assertSame(2, $row[1]);
    }



    public function testFetchAllWorks()
    {
        $sql = "SELECT * FROM Album";
        $statement = $this->connection->prepare($sql);

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
        $this->assertSame('2017-01-01 15:00:00', $rows[0][2] ?? false);
        $this->assertSame('...Baby One More Time', $rows[0][3] ?? false);
        $this->assertSame(2, $rows[0][1] ?? false);
        $this->assertSame(2, $rows[1][0] ?? false);
        $this->assertSame('2017-01-01 15:00:00', $rows[1][2] ?? false);
        $this->assertSame('Dark Horse', $rows[1][3] ?? false);
        $this->assertSame(3, $rows[1][1] ?? false);
    }

    public function testFetchColumnWorks()
    {

        $sql = "SELECT * FROM Album";
        $statement = $this->connection->prepare($sql);

        $result = $statement->execute();
        $column = $result->fetchNumeric();
        $this->assertSame(1, $column[0]);


        $this->assertSame('2017-01-01 15:00:00', $column[2]);

        $this->assertSame('...Baby One More Time', $column[3]);

        $this->assertSame(2, $column[1]);
}

    public function testGetIteratorWorks()
    {
        $sql = "SELECT * FROM Album";
$statement = $this->connection->prepare($sql);
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
        $sql = "SELECT * FROM Album";
        $statement = $this->connection->getWrappedConnection()->prepare($sql);
        $this->assertInstanceOf(Result::class, $statement->execute());
}

    public function testExecuteWorksWithParameters()
    {
        $sql = "SELECT * FROM Album WHERE ID = ?";
        $statement = $this->connection->getWrappedConnection()->prepare($sql);
        $this->assertInstanceOf(Result::class, $statement->execute([1]));
}

    public function testExecuteThrowsExceptionWhenSQLIsInvalid()
    {
        try {
            $statement = $this->connection->prepare("SELECT 1");
            $statement->execute();
        } catch (\Throwable $t) {
            $this->assertSame(SyntaxErrorException::class, $t::class);
            $this->assertSame(-104, $t->getCode());
            $this->assertSame("An exception occurred while executing a query: Dynamic SQL Error SQL error code = -104 Unexpected end of command - line 1, column 8 ", $t->getMessage());

            $this->assertSame(-104, $t->getCode());
            $this->assertNull($t->getSQLState());

            return;
        }
        $this->fail("Exception was never thrown");
    }

    public function testExecuteThrowsExceptionWhenParameterizedSQLIsInvalid()
    {


        $variable = "foo";

        try {
            $statement = $this->connection->prepare( "SELECT ?");
            $statement->bindParam(1, $variable);
            $statement->execute();
        } catch (\Throwable $t) {
            $this->assertSame(SyntaxErrorException::class, $t::class);
            $this->assertSame(-104, $t->getCode());
            $this->assertSame("An exception occurred while executing a query: Dynamic SQL Error SQL error code = -104 Unexpected end of command - line 1, column 8 ", $t->getMessage());
            $this->assertSame(-104, $t->getCode());
            $this->assertNull($t->getSQLState());
            return;
        }
        $this->fail("Exception was never thrown");
    }

    public function testBindValueWorks()
    {

        $statement = $this->connection->prepare("SELECT ID FROM Album WHERE ID = ?");

        $statement->bindValue(1, 2);
        $result = $statement->execute();
        $value = $result->fetchOne();
        $this->assertSame(2, $value);
}

    public function testBindParamWorks()
    {
        $statement = $this->connection->prepare("SELECT ID FROM Album WHERE ID = :ID");

        $id = 2;
        $statement->bindValue(':ID', $id);
        $value = $statement->execute()->fetchOne();
        $this->assertSame(2, $value);
}
}
