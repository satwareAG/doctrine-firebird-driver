<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver\Result as DriverResult;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetResultMiddleware;

/**
 * Tests CharsetResultMiddleware encoding conversion paths.
 * All string values must be decoded from databaseEncoding → phpEncoding.
 * Non-string values (int, float, null, bool) pass through unchanged.
 */
class CharsetResultMiddlewareTest extends TestCase
{
    // -----------------------------------------------------------------------
    // fetchNumeric
    // -----------------------------------------------------------------------

    public function testFetchNumericConvertsStringValues(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchNumeric')->willReturn(['hello', 42, null]);

        $mw  = new CharsetResultMiddleware($result, 'UTF-8', 'UTF-8');
        $row = $mw->fetchNumeric();

        self::assertIsArray($row);
        self::assertIsString($row[0]);
        self::assertSame(42, $row[1]);
        self::assertNull($row[2]);
    }

    public function testFetchNumericReturnsFalseOnEmptyResult(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchNumeric')->willReturn(false);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertFalse($mw->fetchNumeric());
    }

    // -----------------------------------------------------------------------
    // fetchAssociative
    // -----------------------------------------------------------------------

    public function testFetchAssociativeConvertsStringValues(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchAssociative')->willReturn([
            'name' => 'Alice',
            'age'  => 30,
            'note' => null,
        ]);

        $mw  = new CharsetResultMiddleware($result, 'UTF-8', 'UTF-8');
        $row = $mw->fetchAssociative();

        self::assertIsArray($row);
        self::assertIsString($row['name']);
        self::assertSame(30, $row['age']);
        self::assertNull($row['note']);
    }

    public function testFetchAssociativeReturnsFalseOnEmptyResult(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchAssociative')->willReturn(false);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertFalse($mw->fetchAssociative());
    }

    // -----------------------------------------------------------------------
    // fetchOne
    // -----------------------------------------------------------------------

    public function testFetchOneConvertsStringValue(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchOne')->willReturn('database-string');

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertIsString($mw->fetchOne());
    }

    public function testFetchOnePassesThroughIntegerValue(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchOne')->willReturn(42);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertSame(42, $mw->fetchOne());
    }

    public function testFetchOnePassesThroughNullValue(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchOne')->willReturn(null);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertNull($mw->fetchOne());
    }

    public function testFetchOnePassesThroughBooleanValue(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchOne')->willReturn(false);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertFalse($mw->fetchOne());
    }

    // -----------------------------------------------------------------------
    // fetchAllNumeric
    // -----------------------------------------------------------------------

    public function testFetchAllNumericConvertsMultipleRows(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchAllNumeric')->willReturn([
            ['Alice', 10],
            ['Bob', 20],
        ]);

        $mw   = new CharsetResultMiddleware($result, 'UTF-8', 'UTF-8');
        $rows = $mw->fetchAllNumeric();

        self::assertCount(2, $rows);
        self::assertSame(10, $rows[0][1]);
        self::assertSame(20, $rows[1][1]);
    }

    public function testFetchAllNumericReturnsEmptyArrayOnNoRows(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchAllNumeric')->willReturn([]);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertSame([], $mw->fetchAllNumeric());
    }

    public function testFetchAllNumericPreservesNumericKeys(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchAllNumeric')->willReturn([['x', 'y', 3]]);

        $mw   = new CharsetResultMiddleware($result, 'UTF-8', 'UTF-8');
        $rows = $mw->fetchAllNumeric();

        self::assertArrayHasKey(0, $rows[0]);
        self::assertArrayHasKey(1, $rows[0]);
        self::assertSame(3, $rows[0][2]);
    }

    // -----------------------------------------------------------------------
    // fetchAllAssociative
    // -----------------------------------------------------------------------

    public function testFetchAllAssociativeConvertsMultipleRows(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'Alice', 'score' => 95],
            ['name' => 'Bob', 'score' => 87],
        ]);

        $mw   = new CharsetResultMiddleware($result, 'UTF-8', 'UTF-8');
        $rows = $mw->fetchAllAssociative();

        self::assertCount(2, $rows);
        self::assertSame(95, $rows[0]['score']);
        self::assertArrayHasKey('name', $rows[1]);
    }

    public function testFetchAllAssociativeReturnsEmptyArrayOnNoRows(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertSame([], $mw->fetchAllAssociative());
    }

    // -----------------------------------------------------------------------
    // fetchFirstColumn
    // -----------------------------------------------------------------------

    public function testFetchFirstColumnConvertsStringValues(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchFirstColumn')->willReturn(['Alice', 'Bob', 'Charlie']);

        $mw     = new CharsetResultMiddleware($result, 'UTF-8', 'UTF-8');
        $values = $mw->fetchFirstColumn();

        self::assertCount(3, $values);
        self::assertIsString($values[0]);
    }

    public function testFetchFirstColumnPassesThroughNonStringValues(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchFirstColumn')->willReturn([null, 0, 3.14, false]);

        $mw     = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');
        $values = $mw->fetchFirstColumn();

        self::assertNull($values[0]);
        self::assertSame(0, $values[1]);
        self::assertSame(3.14, $values[2]);
        self::assertFalse($values[3]);
    }

    public function testFetchFirstColumnReturnsEmptyArrayOnNoRows(): void
    {
        $result = $this->createMock(DriverResult::class);
        $result->method('fetchFirstColumn')->willReturn([]);

        $mw = new CharsetResultMiddleware($result, 'Windows-1252', 'UTF-8');

        self::assertSame([], $mw->fetchFirstColumn());
    }

    public function testFetchOneConvertsStreamToDecodedString(): void
    {
        $utf8String   = 'Hällo World';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $win1252Bytes);
        rewind($stream);

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn($stream);

        $mw = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');

        $decoded = $mw->fetchOne();

        self::assertIsString($decoded);
        self::assertSame($utf8String, $decoded);

        fclose($stream);
    }
}
