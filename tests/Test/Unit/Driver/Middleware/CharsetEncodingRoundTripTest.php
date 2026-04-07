<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetConnectionMiddleware;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetResultMiddleware;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetStatementMiddleware;

use function fclose;
use function fopen;
use function fwrite;
use function mb_convert_encoding;
use function rewind;

/**
 * End-to-end round-trip encoding tests for the charset middleware stack.
 *
 * Verifies that UTF-8 strings are correctly encoded to Windows-1252 by the
 * statement middleware and decoded back to UTF-8 by the result middleware,
 * covering:
 *  - All fetch methods (fetchNumeric, fetchAssociative, fetchOne,
 *    fetchAllNumeric, fetchAllAssociative, fetchFirstColumn)
 *  - Both plain string and BLOB TEXT (stream resource) column values
 *  - LIKE / search parameter encoding via bindValue() and execute()
 *  - Connection::quote() encoding
 *  - The specific Amicron special characters from the charset spec
 *    (User Story 4: Faßbrause, Ärger mit Öl, Straße 123, Müller & Söhne, €uro)
 */
class CharsetEncodingRoundTripTest extends TestCase
{
    /**
     * Special characters relevant to Amicron Firebird Windows-1252 databases.
     * Each data set is a well-known German business string tested individually.
     *
     * @return array<string, array{string}>
     */
    public static function amicronSpecialStringProvider(): array
    {
        return [
            'german eszett in business context'    => ['Faßbrause für 30€?'],
            'german umlaut capitals with preposition' => ['Ärger mit Öl'],
            'street name with eszett'              => ['Straße 123'],
            'business name with umlauts'           => ['Müller & Söhne'],
            'euro symbol'                          => ['€uro'],
            'full lowercase umlaut set'            => ['äöüÄÖÜß'],
            'product name with umlaut'             => ['Produkt: Grüner Tee 500g'],
        ];
    }

    // -----------------------------------------------------------------------
    // Round-trip: bindValue (UTF-8 → WIN1252) then fetchOne (WIN1252 → UTF-8)
    // -----------------------------------------------------------------------

    #[DataProvider('amicronSpecialStringProvider')]
    public function testBindValueAndFetchOneRoundTrip(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        // Statement side: verify inner statement receives WIN1252 bytes
        $innerStmt = $this->createMock(DriverStatement::class);
        $innerStmt->expects(self::once())
            ->method('bindValue')
            ->with(1, $win1252Bytes, ParameterType::STRING)
            ->willReturn(true);

        $stmt = new CharsetStatementMiddleware($innerStmt, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, $utf8String, ParameterType::STRING);

        // Result side: verify WIN1252 bytes are decoded back to original UTF-8
        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchOne')->willReturn($win1252Bytes);

        $result = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');

        self::assertSame($utf8String, $result->fetchOne());
    }

    #[DataProvider('amicronSpecialStringProvider')]
    public function testExecuteWithInlineParamsRoundTrip(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $innerResult = $this->createMock(DriverResult::class);
        $innerResult->method('fetchOne')->willReturn($win1252Bytes);

        $innerStmt = $this->createMock(DriverStatement::class);
        $innerStmt->expects(self::once())
            ->method('bindValue')
            ->with(1, $win1252Bytes, ParameterType::STRING);
        $innerStmt->expects(self::once())
            ->method('execute')
            ->willReturn($innerResult);

        // DBAL4: inline execute([...]) params removed - use bindValue() instead
        $stmt = new CharsetStatementMiddleware($innerStmt, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, $utf8String, ParameterType::STRING);
        $result = $stmt->execute();

        self::assertSame($utf8String, $result->fetchOne());
    }

    // -----------------------------------------------------------------------
    // fetchAssociative: WIN1252 plain string decoding
    // -----------------------------------------------------------------------

    #[DataProvider('amicronSpecialStringProvider')]
    public function testFetchAssociativeDecodesWin1252ToUtf8(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAssociative')->willReturn([
            'NAME'  => $win1252Bytes,
            'ID'    => 42,
            'PRICE' => 3.14,
            'NOTE'  => null,
        ]);

        $mw  = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $row = $mw->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame($utf8String, $row['NAME']);
        self::assertSame(42, $row['ID']);
        self::assertSame(3.14, $row['PRICE']);
        self::assertNull($row['NOTE']);
    }

    // -----------------------------------------------------------------------
    // fetchNumeric: WIN1252 plain string decoding
    // -----------------------------------------------------------------------

    #[DataProvider('amicronSpecialStringProvider')]
    public function testFetchNumericDecodesWin1252ToUtf8(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchNumeric')->willReturn([$win1252Bytes, 42, null]);

        $mw  = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $row = $mw->fetchNumeric();

        self::assertIsArray($row);
        self::assertSame($utf8String, $row[0]);
        self::assertSame(42, $row[1]);
        self::assertNull($row[2]);
    }

    // -----------------------------------------------------------------------
    // fetchAllAssociative: WIN1252 plain string decoding across multiple rows
    // -----------------------------------------------------------------------

    #[DataProvider('amicronSpecialStringProvider')]
    public function testFetchAllAssociativeDecodesWin1252ToUtf8(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAllAssociative')->willReturn([
            ['NAME' => $win1252Bytes, 'ID' => 1],
            ['NAME' => $win1252Bytes, 'ID' => 2],
        ]);

        $mw   = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $rows = $mw->fetchAllAssociative();

        self::assertCount(2, $rows);
        self::assertSame($utf8String, $rows[0]['NAME']);
        self::assertSame($utf8String, $rows[1]['NAME']);
    }

    // -----------------------------------------------------------------------
    // fetchAllNumeric: WIN1252 plain string decoding
    // -----------------------------------------------------------------------

    #[DataProvider('amicronSpecialStringProvider')]
    public function testFetchAllNumericDecodesWin1252ToUtf8(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAllNumeric')->willReturn([[$win1252Bytes, 99]]);

        $mw   = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $rows = $mw->fetchAllNumeric();

        self::assertCount(1, $rows);
        self::assertSame($utf8String, $rows[0][0]);
        self::assertSame(99, $rows[0][1]);
    }

    // -----------------------------------------------------------------------
    // fetchFirstColumn: WIN1252 plain string decoding
    // -----------------------------------------------------------------------

    #[DataProvider('amicronSpecialStringProvider')]
    public function testFetchFirstColumnDecodesWin1252ToUtf8(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchFirstColumn')->willReturn([$win1252Bytes, $win1252Bytes]);

        $mw     = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $values = $mw->fetchFirstColumn();

        self::assertCount(2, $values);
        self::assertSame($utf8String, $values[0]);
        self::assertSame($utf8String, $values[1]);
    }

    // -----------------------------------------------------------------------
    // BLOB TEXT stream handling in fetchNumeric
    // -----------------------------------------------------------------------

    public function testFetchNumericDecodesStreamResourceColumn(): void
    {
        $utf8String   = 'Hällo Wörld - Blob Text';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $win1252Bytes);
        rewind($stream);

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchNumeric')->willReturn([$stream, 42]);

        $mw  = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $row = $mw->fetchNumeric();

        self::assertIsArray($row);
        self::assertSame($utf8String, $row[0]);
        self::assertSame(42, $row[1]);

        fclose($stream);
    }

    // -----------------------------------------------------------------------
    // BLOB TEXT stream handling in fetchAssociative
    // -----------------------------------------------------------------------

    public function testFetchAssociativeDecodesStreamResourceColumn(): void
    {
        $utf8String   = 'Blob Text: Müller & Söhne - €uro';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $win1252Bytes);
        rewind($stream);

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAssociative')->willReturn(['TEXT' => $stream, 'ID' => 7]);

        $mw  = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $row = $mw->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame($utf8String, $row['TEXT']);
        self::assertSame(7, $row['ID']);

        fclose($stream);
    }

    // -----------------------------------------------------------------------
    // BLOB TEXT stream handling in fetchAllNumeric
    // -----------------------------------------------------------------------

    public function testFetchAllNumericDecodesStreamResourceColumn(): void
    {
        $utf8String   = 'Straße 123 - Blob';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $win1252Bytes);
        rewind($stream);

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAllNumeric')->willReturn([[$stream]]);

        $mw   = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $rows = $mw->fetchAllNumeric();

        self::assertCount(1, $rows);
        self::assertSame($utf8String, $rows[0][0]);

        fclose($stream);
    }

    // -----------------------------------------------------------------------
    // BLOB TEXT stream handling in fetchAllAssociative
    // -----------------------------------------------------------------------

    public function testFetchAllAssociativeDecodesStreamResourceColumn(): void
    {
        $utf8String   = 'Ärger mit Öl';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $win1252Bytes);
        rewind($stream);

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchAllAssociative')->willReturn([['DESCRIPTION' => $stream]]);

        $mw   = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $rows = $mw->fetchAllAssociative();

        self::assertIsArray($rows[0]);
        self::assertSame($utf8String, $rows[0]['DESCRIPTION']);

        fclose($stream);
    }

    // -----------------------------------------------------------------------
    // BLOB TEXT stream handling in fetchFirstColumn
    // -----------------------------------------------------------------------

    public function testFetchFirstColumnDecodesStreamResourceValues(): void
    {
        $utf8String   = 'Faßbrause für 30€?';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $win1252Bytes);
        rewind($stream);

        $resultMock = $this->createMock(DriverResult::class);
        $resultMock->method('fetchFirstColumn')->willReturn([$stream]);

        $mw     = new CharsetResultMiddleware($resultMock, 'Windows-1252', 'UTF-8');
        $values = $mw->fetchFirstColumn();

        self::assertCount(1, $values);
        self::assertSame($utf8String, $values[0]);

        fclose($stream);
    }

    // -----------------------------------------------------------------------
    // LIKE / search: bindValue encoding for WHERE clause parameters
    // -----------------------------------------------------------------------

    /**
     * Verifies that LIKE search parameters (STRING type) are encoded before
     * being sent to Firebird, enabling correct WHERE col LIKE :param queries.
     */
    #[DataProvider('amicronSpecialStringProvider')]
    public function testBindValueEncodesSearchParameterToWin1252ForLike(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $inner = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('bindValue')
            ->with(1, $win1252Bytes, ParameterType::STRING);

        // DBAL4: bindValue() returns void
        $stmt = new CharsetStatementMiddleware($inner, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, $utf8String, ParameterType::STRING);
    }

    /**
     * Verifies that search params are encoded via bindValue() for WHERE clause parameters.
     * DBAL4: inline execute([...]) params removed - use bindValue() instead.
     */
    #[DataProvider('amicronSpecialStringProvider')]
    public function testExecuteInlineParamEncodesSearchTermToWin1252(string $utf8String): void
    {
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $innerResult = $this->createMock(DriverResult::class);
        $inner       = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('bindValue')
            ->with(1, $win1252Bytes, ParameterType::STRING);
        $inner->expects(self::once())
            ->method('execute')
            ->willReturn($innerResult);

        $stmt = new CharsetStatementMiddleware($inner, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, $utf8String, ParameterType::STRING);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Connection::quote() encoding
    // -----------------------------------------------------------------------

    public function testQuoteEncodesSpecialCharsToWin1252(): void
    {
        $utf8String   = 'Müller & Söhne';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('quote')
            ->with($win1252Bytes)
            ->willReturn("'" . $win1252Bytes . "'");

        // DBAL4: quote(string $value) - only 1 parameter
        $conn = new CharsetConnectionMiddleware($innerConnection, 'Windows-1252', 'UTF-8');

        $quoted = $conn->quote($utf8String);
        // The quoted value wraps WIN1252 bytes (database-side quoting)
        self::assertStringContainsString($win1252Bytes, (string) $quoted);
    }
}
