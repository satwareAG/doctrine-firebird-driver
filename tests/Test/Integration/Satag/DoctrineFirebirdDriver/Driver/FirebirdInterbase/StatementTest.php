<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Exception\SyntaxErrorException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Result;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Throwable;

class StatementTest extends AbstractIntegrationTestCase
{
    public function tearDown(): void
    {
        $this->connection->close();

        parent::tearDown();
    }

    public function testFetchWorks(): void
    {
        $statement = $this->connection->prepare('SELECT * FROM Album');
        $result    = $statement->execute();
        $row       = $result->fetchAssociative();
        self::assertSame(1, $row['ID']);
        self::assertSame('2017-01-01 15:00:00', $row['TIMECREATED']);
        self::assertSame('...Baby One More Time', $row['NAME']);
        self::assertSame(2, $row['ARTIST_ID']);

        $result = $statement->execute();
        $row    = $result->fetchNumeric();
        self::assertSame(1, $row[0]);
        self::assertSame('2017-01-01 15:00:00', $row[2]);
        self::assertSame('...Baby One More Time', $row[3]);
        self::assertSame(2, $row[1]);
    }

    public function testFetchAllWorks(): void
    {
        $sql       = 'SELECT * FROM Album';
        $statement = $this->connection->prepare($sql);

        $rows = $statement->execute()->fetchAllAssociative();
        self::assertIsArray($rows);
        self::assertCount(2, $rows);
        self::assertIsArray($rows[0]);
        self::assertIsArray($rows[1]);
        self::assertSame(1, $rows[0]['ID'] ?? false);
        self::assertSame('2017-01-01 15:00:00', $rows[0]['TIMECREATED'] ?? false);
        self::assertSame('...Baby One More Time', $rows[0]['NAME'] ?? false);
        self::assertSame(2, $rows[0]['ARTIST_ID'] ?? false);
        self::assertSame(2, $rows[1]['ID'] ?? false);
        self::assertSame('2017-01-01 15:00:00', $rows[1]['TIMECREATED'] ?? false);
        self::assertSame('Dark Horse', $rows[1]['NAME'] ?? false);
        self::assertSame(3, $rows[1]['ARTIST_ID'] ?? false);

        $rows = $statement->execute()->fetchAllNumeric();
        self::assertIsArray($rows);
        self::assertCount(2, $rows);
        self::assertIsArray($rows[0]);
        self::assertIsArray($rows[1]);
        self::assertSame(1, $rows[0][0] ?? false);
        self::assertSame('2017-01-01 15:00:00', $rows[0][2] ?? false);
        self::assertSame('...Baby One More Time', $rows[0][3] ?? false);
        self::assertSame(2, $rows[0][1] ?? false);
        self::assertSame(2, $rows[1][0] ?? false);
        self::assertSame('2017-01-01 15:00:00', $rows[1][2] ?? false);
        self::assertSame('Dark Horse', $rows[1][3] ?? false);
        self::assertSame(3, $rows[1][1] ?? false);
    }

    public function testFetchColumnWorks(): void
    {
        $sql       = 'SELECT * FROM Album';
        $statement = $this->connection->prepare($sql);

        $result = $statement->execute();
        $column = $result->fetchNumeric();
        self::assertSame(1, $column[0]);

        self::assertSame('2017-01-01 15:00:00', $column[2]);

        self::assertSame('...Baby One More Time', $column[3]);

        self::assertSame(2, $column[1]);
    }

    public function testGetIteratorWorks(): void
    {
        $sql       = 'SELECT * FROM Album';
        $statement = $this->connection->prepare($sql);
        $result    = $statement->execute()->fetchAllAssociative();
        $array     = $result;

        self::assertCount(2, $array);
        self::assertIsArray($array[0]);
        self::assertIsArray($array[1]);
        self::assertSame(1, $array[0]['ID'] ?? false);
        self::assertSame(2, $array[1]['ID'] ?? false);
    }

    public function testExecuteWorks(): void
    {
        $sql       = 'SELECT * FROM Album';
        $statement = $this->connection->getWrappedConnection()->prepare($sql);
        self::assertInstanceOf(Result::class, $statement->execute());
    }

    public function testExecuteWorksWithParameters(): void
    {
        $sql       = 'SELECT * FROM Album WHERE ID = ?';
        $statement = $this->connection->getWrappedConnection()->prepare($sql);
        self::assertInstanceOf(Result::class, $statement->execute([1]));
    }

    public function testExecuteThrowsExceptionWhenSQLIsInvalid(): void
    {
        try {
            $statement = $this->connection->prepare('SELECT 1');
            $statement->execute();
        } catch (Throwable $t) {
            self::assertSame(SyntaxErrorException::class, $t::class);
            self::assertSame(-104, $t->getCode());
            self::assertSame('An exception occurred while executing a query: Dynamic SQL Error SQL error code = -104 Unexpected end of command - line 1, column 8 ', $t->getMessage());

            self::assertSame(-104, $t->getCode());
            self::assertNull($t->getSQLState());

            return;
        }

        $this->fail('Exception was never thrown');
    }

    public function testExecuteThrowsExceptionWhenParameterizedSQLIsInvalid(): void
    {
        $variable = 'foo';

        try {
            $statement = $this->connection->prepare('SELECT ?');
            $statement->bindParam(1, $variable);
            $statement->execute();
        } catch (Throwable $t) {
            self::assertSame(SyntaxErrorException::class, $t::class);
            self::assertSame(-104, $t->getCode());
            self::assertSame('An exception occurred while executing a query: Dynamic SQL Error SQL error code = -104 Unexpected end of command - line 1, column 8 ', $t->getMessage());
            self::assertSame(-104, $t->getCode());
            self::assertNull($t->getSQLState());

            return;
        }

        $this->fail('Exception was never thrown');
    }

    public function testBindValueWorks(): void
    {
        $statement = $this->connection->prepare('SELECT ID FROM Album WHERE ID = ?');

        $statement->bindValue(1, 2);
        $result = $statement->execute();
        $value  = $result->fetchOne();
        self::assertSame(2, $value);
    }

    public function testBindParamWorks(): void
    {
        $statement = $this->connection->prepare('SELECT ID FROM Album WHERE ID = :ID');

        $id = 2;
        $statement->bindValue(':ID', $id);
        $value = $statement->execute()->fetchOne();
        self::assertSame(2, $value);
    }
}
