<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Exception\SyntaxErrorException;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Throwable;

class StatementTest extends AbstractIntegrationTestCase
{
    /**
     * Clean up extra Album rows inserted by write tests so that read assertions
     * always see exactly 2 fixture rows (id=1 and id=2).
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->connection->executeStatement('DELETE FROM ALBUM WHERE ID > 2');
    }

    public function testFetchWorks(): void
    {
        $statement = $this->connection->prepare('SELECT * FROM Album');
        $result    = $statement->execute();
        $row       = $result->fetchAssociative();
        self::assertSame(1, $row['ID']);
        self::assertStringStartsWith('2017-01-01 15:00:00', (string) ($row['TIMECREATED'] ?? ''));
        self::assertSame('...Baby One More Time', $row['NAME']);
        self::assertSame(2, $row['ARTIST_ID']);

        $result = $statement->execute();
        $row    = $result->fetchNumeric();
        self::assertSame(1, $row[0]);
        // Column order from CREATE TABLE: id(0), timeCreated(1), name(2), artist_id(3)
        self::assertStringStartsWith('2017-01-01 15:00:00', (string) $row[1]);
        self::assertSame('...Baby One More Time', $row[2]);
        self::assertSame(2, $row[3]);
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
        self::assertStringStartsWith('2017-01-01 15:00:00', (string) ($rows[0]['TIMECREATED'] ?? ''));
        self::assertSame('...Baby One More Time', $rows[0]['NAME'] ?? false);
        self::assertSame(2, $rows[0]['ARTIST_ID'] ?? false);
        self::assertSame(2, $rows[1]['ID'] ?? false);
        self::assertStringStartsWith('2017-01-01 15:00:00', (string) ($rows[1]['TIMECREATED'] ?? ''));
        self::assertSame('Dark Horse', $rows[1]['NAME'] ?? false);
        self::assertSame(3, $rows[1]['ARTIST_ID'] ?? false);

        $rows = $statement->execute()->fetchAllNumeric();
        self::assertIsArray($rows);
        self::assertCount(2, $rows);
        self::assertIsArray($rows[0]);
        self::assertIsArray($rows[1]);
        // Column order from CREATE TABLE: id(0), timeCreated(1), name(2), artist_id(3)
        self::assertSame(1, $rows[0][0] ?? false);
        self::assertStringStartsWith('2017-01-01 15:00:00', (string) ($rows[0][1] ?? ''));
        self::assertSame('...Baby One More Time', $rows[0][2] ?? false);
        self::assertSame(2, $rows[0][3] ?? false);
        self::assertSame(2, $rows[1][0] ?? false);
        self::assertStringStartsWith('2017-01-01 15:00:00', (string) ($rows[1][1] ?? ''));
        self::assertSame('Dark Horse', $rows[1][2] ?? false);
        self::assertSame(3, $rows[1][3] ?? false);
    }

    /**
     * Note: testFetchColumnWorks removed - overlaps with Functional/StatementTest::testFetchInColumnMode
     */

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

    /**
     * Note: testExecuteWorks removed - overlaps with Functional/StatementTest::testExecuteQuery
     * Note: testExecuteWorksWithParameters removed - overlaps with Functional/StatementTest::testExecuteQueryWithParams
     */

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
            // php-firebird v7.0.0+ now correctly returns SQLSTATE '42000' for syntax errors
            self::assertSame('42000', $t->getSQLState());

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
            // php-firebird v7.0.0+ now correctly returns SQLSTATE '42000' for syntax errors
            self::assertSame('42000', $t->getSQLState());

            return;
        }

        $this->fail('Exception was never thrown');
    }

    /**
     * Note: testBindValueWorks removed - overlaps with Functional/StatementTest::testReuseStatementWithReboundValue
     * Note: testBindParamWorks removed - overlaps with Functional/StatementTest::testReuseStatementWithReboundParam
     */
}
