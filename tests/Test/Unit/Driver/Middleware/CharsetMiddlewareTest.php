<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetResultMiddleware;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetStatementMiddleware;

/**
 * Unit tests for the charset middleware encoding/decoding logic.
 *
 * These tests verify that:
 * - String parameters are encoded PHP encoding → database encoding before being sent to Firebird
 * - String results are decoded database encoding → PHP encoding after being received from Firebird
 * - Non-string values (int, float, null, bool) pass through unchanged
 * - Custom encoding pairs work correctly (not just Windows-1252/UTF-8)
 */
#[CoversClass(CharsetMiddleware::class)]
#[CoversClass(CharsetResultMiddleware::class)]
#[CoversClass(CharsetStatementMiddleware::class)]
class CharsetMiddlewareTest extends TestCase
{
    // =========================================================================
    // CharsetResultMiddleware - decoding (DB → PHP), default Windows-1252/UTF-8
    // =========================================================================

    public function testDecodesFetchAssociativeStrings(): void
    {
        $win1252Row = [
            'NAME'  => \mb_convert_encoding('Faßbrause', 'Windows-1252', 'UTF-8'),
            'PREIS' => \mb_convert_encoding('30€', 'Windows-1252', 'UTF-8'),
            'LFDNR' => 42,
        ];

        $innerResult = $this->createResultMock();
        $innerResult->method('fetchAssociative')->willReturn($win1252Row);

        $middleware = new CharsetResultMiddleware($innerResult, 'Windows-1252', 'UTF-8');
        $row        = $middleware->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame('Faßbrause', $row['NAME']);
        self::assertSame('30€', $row['PREIS']);
        self::assertSame(42, $row['LFDNR']); // int passes through unchanged
    }

    public function testDecodesFetchNumericStrings(): void
    {
        $win1252Row = [
            \mb_convert_encoding('Grüner Tee', 'Windows-1252', 'UTF-8'),
            99,
            null,
        ];

        $innerResult = $this->createResultMock();
        $innerResult->method('fetchNumeric')->willReturn($win1252Row);

        $middleware = new CharsetResultMiddleware($innerResult, 'Windows-1252', 'UTF-8');
        $row        = $middleware->fetchNumeric();

        self::assertIsArray($row);
        self::assertSame('Grüner Tee', $row[0]);
        self::assertSame(99, $row[1]);
        self::assertNull($row[2]);
    }

    public function testDecodesFetchOneString(): void
    {
        $win1252Value = \mb_convert_encoding('Müller & Söhne', 'Windows-1252', 'UTF-8');

        $innerResult = $this->createResultMock();
        $innerResult->method('fetchOne')->willReturn($win1252Value);

        $middleware = new CharsetResultMiddleware($innerResult, 'Windows-1252', 'UTF-8');
        $value      = $middleware->fetchOne();

        self::assertSame('Müller & Söhne', $value);
    }

    public function testDecodesFetchAllAssociative(): void
    {
        $rows = [
            ['NAME' => \mb_convert_encoding('Ärger', 'Windows-1252', 'UTF-8'), 'ID' => 1],
            ['NAME' => \mb_convert_encoding('Öl', 'Windows-1252', 'UTF-8'), 'ID' => 2],
        ];

        $innerResult = $this->createResultMock();
        $innerResult->method('fetchAllAssociative')->willReturn($rows);

        $middleware = new CharsetResultMiddleware($innerResult, 'Windows-1252', 'UTF-8');
        $result     = $middleware->fetchAllAssociative();

        self::assertSame('Ärger', $result[0]['NAME']);
        self::assertSame(1, $result[0]['ID']);
        self::assertSame('Öl', $result[1]['NAME']);
    }

    public function testDecodesFetchFirstColumn(): void
    {
        $values = [
            \mb_convert_encoding('Straße', 'Windows-1252', 'UTF-8'),
            \mb_convert_encoding('Wörth', 'Windows-1252', 'UTF-8'),
        ];

        $innerResult = $this->createResultMock();
        $innerResult->method('fetchFirstColumn')->willReturn($values);

        $middleware = new CharsetResultMiddleware($innerResult, 'Windows-1252', 'UTF-8');
        $result     = $middleware->fetchFirstColumn();

        self::assertSame('Straße', $result[0]);
        self::assertSame('Wörth', $result[1]);
    }

    public function testNonStringValuesPassThroughUnchanged(): void
    {
        $row = [
            'INT_COL'   => 42,
            'FLOAT_COL' => 3.14,
            'NULL_COL'  => null,
            'BOOL_COL'  => true,
        ];

        $innerResult = $this->createResultMock();
        $innerResult->method('fetchAssociative')->willReturn($row);

        $middleware = new CharsetResultMiddleware($innerResult, 'Windows-1252', 'UTF-8');
        $result     = $middleware->fetchAssociative();

        self::assertIsArray($result);
        self::assertSame(42, $result['INT_COL']);
        self::assertSame(3.14, $result['FLOAT_COL']);
        self::assertNull($result['NULL_COL']);
        self::assertTrue($result['BOOL_COL']);
    }

    public function testReturnsFalseWhenNoMoreRows(): void
    {
        $innerResult = $this->createResultMock();
        $innerResult->method('fetchAssociative')->willReturn(false);
        $innerResult->method('fetchNumeric')->willReturn(false);

        $middleware = new CharsetResultMiddleware($innerResult, 'Windows-1252', 'UTF-8');

        self::assertFalse($middleware->fetchAssociative());
        self::assertFalse($middleware->fetchNumeric());
    }

    // =========================================================================
    // CharsetStatementMiddleware - encoding (PHP → DB), default Windows-1252/UTF-8
    // =========================================================================

    public function testEncodesStringParameterOnBindValue(): void
    {
        $capturedValue = null;

        $innerStatement = $this->createStatementMock();
        $innerStatement
            ->expects(self::once())
            ->method('bindValue')
            ->willReturnCallback(static function (mixed $param, mixed $value, mixed $type) use (&$capturedValue): bool {
                $capturedValue = $value;

                return true;
            });

        $middleware = new CharsetStatementMiddleware($innerStatement, 'Windows-1252', 'UTF-8');
        $middleware->bindValue(1, 'Faßbrause für 30€?', ParameterType::STRING);

        $expected = \mb_convert_encoding('Faßbrause für 30€?', 'Windows-1252', 'UTF-8');
        self::assertSame($expected, $capturedValue);
    }

    public function testDoesNotEncodeNonStringParameterOnBindValue(): void
    {
        $capturedValue = null;

        $innerStatement = $this->createStatementMock();
        $innerStatement
            ->expects(self::once())
            ->method('bindValue')
            ->willReturnCallback(static function (mixed $param, mixed $value, mixed $type) use (&$capturedValue): bool {
                $capturedValue = $value;

                return true;
            });

        $middleware = new CharsetStatementMiddleware($innerStatement, 'Windows-1252', 'UTF-8');
        $middleware->bindValue(1, 42, ParameterType::INTEGER);

        self::assertSame(42, $capturedValue);
    }

    public function testDoesNotEncodeNullParameterOnBindValue(): void
    {
        $capturedValue = 'sentinel';

        $innerStatement = $this->createStatementMock();
        $innerStatement
            ->expects(self::once())
            ->method('bindValue')
            ->willReturnCallback(static function (mixed $param, mixed $value, mixed $type) use (&$capturedValue): bool {
                $capturedValue = $value;

                return true;
            });

        $middleware = new CharsetStatementMiddleware($innerStatement, 'Windows-1252', 'UTF-8');
        $middleware->bindValue(1, null, ParameterType::STRING);

        self::assertNull($capturedValue);
    }

    public function testSpecialCharactersRoundTripWindowsToUtf8(): void
    {
        $specialChars = [
            'Faßbrause für 30€?',
            'Ärger mit Öl und Übergabe',
            'Straße 123, Wörth am Rhein',
            'Müller & Söhne GmbH',
        ];

        foreach ($specialChars as $input) {
            $encoded = \mb_convert_encoding($input, 'Windows-1252', 'UTF-8');
            $decoded = \mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');
            self::assertSame($input, $decoded, "Round-trip failed for: {$input}");
        }
    }

    // =========================================================================
    // Configurable encoding - ISO-8859-1 / UTF-8
    // =========================================================================

    public function testCustomEncodingIso88591DecodesFetchOne(): void
    {
        $iso88591Value = \mb_convert_encoding('Müller', 'ISO-8859-1', 'UTF-8');

        $innerResult = $this->createResultMock();
        $innerResult->method('fetchOne')->willReturn($iso88591Value);

        $middleware = new CharsetResultMiddleware($innerResult, 'ISO-8859-1', 'UTF-8');
        $value      = $middleware->fetchOne();

        self::assertSame('Müller', $value);
    }

    public function testCustomEncodingIso88591EncodesBindValue(): void
    {
        $capturedValue = null;

        $innerStatement = $this->createStatementMock();
        $innerStatement
            ->expects(self::once())
            ->method('bindValue')
            ->willReturnCallback(static function (mixed $param, mixed $value, mixed $type) use (&$capturedValue): bool {
                $capturedValue = $value;

                return true;
            });

        $middleware = new CharsetStatementMiddleware($innerStatement, 'ISO-8859-1', 'UTF-8');
        $middleware->bindValue(1, 'Müller', ParameterType::STRING);

        $expected = \mb_convert_encoding('Müller', 'ISO-8859-1', 'UTF-8');
        self::assertSame($expected, $capturedValue);
    }

    public function testCharsetMiddlewareDefaultEncodingsAreWindowsAndUtf8(): void
    {
        $middleware = new CharsetMiddleware();

        // Verify the middleware can be instantiated with defaults (no exception)
        self::assertInstanceOf(CharsetMiddleware::class, $middleware);
    }

    public function testCharsetMiddlewareAcceptsCustomEncodings(): void
    {
        $middleware = new CharsetMiddleware(databaseEncoding: 'ISO-8859-1', phpEncoding: 'UTF-8');

        self::assertInstanceOf(CharsetMiddleware::class, $middleware);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createResultMock(): ResultInterface&MockObject
    {
        return $this->createMock(ResultInterface::class);
    }

    private function createStatementMock(): StatementInterface&MockObject
    {
        return $this->createMock(StatementInterface::class);
    }
}
