<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver\Result as DriverResult;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetResultMiddleware;

use function fopen;
use function fwrite;
use function mb_convert_encoding;
use function rewind;
use function strpos;

/**
 * Tests CharsetResultMiddleware encoding conversion paths.
 *
 * All string values must be decoded from databaseEncoding → phpEncoding.
 * Non-string values (int, float, null, bool) pass through unchanged.
 *
 * Binary strings containing NULL bytes must NOT be transcoded (#127).
 * The NULL-byte heuristic catches JPEG, PNG, GIF, PDF, ZIP, BMP, TIFF.
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

    // -----------------------------------------------------------------------
    // Binary BLOB string detection (#127)
    // -----------------------------------------------------------------------

    public function testBinaryStringWithNullBytesNotTranscoded(): void
    {
        // JPEG SOI + JFIF marker: \xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x46\x49\x46\x00
        $binary = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x46\x49\x46\x00";

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn($binary);

        // ISO-8859-1 → UTF-8 (production config that causes corruption without fix)
        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        $decoded = $mw->fetchOne();

        self::assertSame($binary, $decoded, 'Binary string with NULL bytes must not be transcoded');
    }

    public function testJpegHeaderPreserved(): void
    {
        $jpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAllAssociative')->willReturn([['image' => $jpegHeader]]);

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        $rows = $mw->fetchAllAssociative();

        self::assertSame($jpegHeader, $rows[0]['image'], 'JPEG header bytes must be preserved');
    }

    public function testTextStringWithoutNullBytesIsTranscoded(): void
    {
        // ISO-8859-1 string with umlaut: "Müller" → \x4D\xFC\x6C\x6C\x65\x72
        $isoText  = "\x4D\xFC\x6C\x6C\x65\x72";
        $utf8Text = 'Müller';

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn($isoText);

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        $decoded = $mw->fetchOne();

        self::assertSame($utf8Text, $decoded, 'Text without NULL bytes must be transcoded from ISO-8859-1 to UTF-8');
    }

    public function testEmptyStringPassthrough(): void
    {
        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn('');

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        self::assertSame('', $mw->fetchOne());
    }

    public function testPureAsciiStringUnchanged(): void
    {
        $ascii = 'Hello World 123';

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn($ascii);

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        // ASCII is a subset of both ISO-8859-1 and UTF-8, so conversion is a no-op
        self::assertSame($ascii, $mw->fetchOne());
    }

    public function testAll256BytesWithNullPreserved(): void
    {
        $binary = '';
        for ($i = 0; $i < 256; $i++) {
            $binary .= chr($i);
        }

        self::assertNotFalse(strpos($binary, "\x00"), 'Test data must contain NULL byte');

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn($binary);

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        $decoded = $mw->fetchOne();

        self::assertSame($binary, $decoded, 'All 256 byte values with NULL must be preserved');
    }

    public function testHighBytesWithoutNullAreTranscoded(): void
    {
        // Bytes 0x80-0xFF without any NULL byte — this is text, not binary
        $isoText = '';
        for ($i = 0x80; $i <= 0xFF; $i++) {
            $isoText .= chr($i);
        }

        // No \x00 in this data, so it should be transcoded
        self::assertFalse(strpos($isoText, "\x00"));

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn($isoText);

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        $decoded = $mw->fetchOne();

        // Verify it was transcoded (UTF-8 will be longer than ISO-8859-1 for chars > 0x7F)
        self::assertNotSame($isoText, $decoded, 'High-byte text without NULL must be transcoded');
        self::assertSame(mb_convert_encoding($isoText, 'UTF-8', 'ISO-8859-1'), $decoded);
    }

    public function testBinaryStringInFetchAssociative(): void
    {
        $binary = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46";

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAssociative')->willReturn(['data' => $binary, 'id' => 1]);

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        $row = $mw->fetchAssociative();

        self::assertSame($binary, $row['data'], 'Binary BLOB in associative fetch must not be transcoded');
        self::assertSame(1, $row['id']);
    }

    public function testBinaryStringInFetchAllNumeric(): void
    {
        $binary = "\x89PNG\x0D\x0A\x1A\x0A\x00\x00\x00\x0D";

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAllNumeric')->willReturn([[$binary, 42]]);

        $mw = new CharsetResultMiddleware($resultMock, 'ISO-8859-1', 'UTF-8');

        $rows = $mw->fetchAllNumeric();

        self::assertSame($binary, $rows[0][0], 'PNG header in fetchAllNumeric must not be transcoded');
        self::assertSame(42, $rows[0][1]);
    }
}
