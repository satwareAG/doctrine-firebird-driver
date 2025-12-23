<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;

/**
 * Unit tests for the Exception class.
 *
 * Tests SQLSTATE support (php-firebird v7.0.0+) and basic exception functionality.
 */
#[CoversClass(Exception::class)]
class ExceptionTest extends TestCase
{
    // ==========================================================================
    // Constructor Tests
    // ==========================================================================

    public function testConstructorWithAllParameters(): void
    {
        $previous = new \RuntimeException('Previous exception');
        $exception = new Exception('Test message', '42000', 123, $previous);

        self::assertSame('Test message', $exception->getMessage());
        self::assertSame('42000', $exception->getSQLState());
        self::assertSame(123, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $exception = new Exception('Minimal message');

        self::assertSame('Minimal message', $exception->getMessage());
        self::assertNull($exception->getSQLState());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testConstructorWithNullSqlState(): void
    {
        $exception = new Exception('Message', null, 456);

        self::assertSame('Message', $exception->getMessage());
        self::assertNull($exception->getSQLState());
        self::assertSame(456, $exception->getCode());
    }

    // ==========================================================================
    // getSQLState() Tests
    // ==========================================================================

    #[DataProvider('sqlStateProvider')]
    public function testGetSQLStateReturnsCorrectValue(string|null $sqlState): void
    {
        $exception = new Exception('Test', $sqlState);

        self::assertSame($sqlState, $exception->getSQLState());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function sqlStateProvider(): array
    {
        return [
            'integrity constraint' => ['23000'],
            'syntax error' => ['42000'],
            'connection failure' => ['08006'],
            'general error' => ['HY000'],
            'no data' => ['02000'],
            'successful completion' => ['00000'],
            'null state' => [null],
        ];
    }

    // ==========================================================================
    // fromErrorInfo() Tests
    // ==========================================================================

    public function testFromErrorInfoCreatesException(): void
    {
        // Note: fbird_sqlstate() won't be called in unit test context
        // since no actual Firebird error exists
        $exception = Exception::fromErrorInfo('Error message', 335544345);

        self::assertSame('Error message', $exception->getMessage());
        self::assertSame(335544345, $exception->getCode());
        // SQLSTATE will be null since no actual Firebird error state
        // (fbird_sqlstate() returns false when no error)
    }

    public function testFromErrorInfoWithZeroCode(): void
    {
        $exception = Exception::fromErrorInfo('No error', 0);

        self::assertSame('No error', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    public function testFromErrorInfoWithEmptyMessage(): void
    {
        $exception = Exception::fromErrorInfo('', 100);

        self::assertSame('', $exception->getMessage());
        self::assertSame(100, $exception->getCode());
    }

    // ==========================================================================
    // SQLSTATE Format Tests
    // ==========================================================================

    #[DataProvider('validSqlStateFormatProvider')]
    public function testValidSqlStateFormats(string $sqlState): void
    {
        $exception = new Exception('Test', $sqlState);

        // SQLSTATE should be exactly 5 characters
        self::assertSame(5, strlen($exception->getSQLState() ?? ''));
        self::assertSame($sqlState, $exception->getSQLState());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validSqlStateFormatProvider(): array
    {
        // Standard SQLSTATE codes from SQL:2003
        return [
            'class 00 - success' => ['00000'],
            'class 01 - warning' => ['01000'],
            'class 02 - no data' => ['02000'],
            'class 07 - dynamic SQL error' => ['07000'],
            'class 08 - connection exception' => ['08006'],
            'class 21 - cardinality violation' => ['21000'],
            'class 22 - data exception' => ['22000'],
            'class 23 - integrity constraint' => ['23000'],
            'class 24 - invalid cursor' => ['24000'],
            'class 25 - invalid transaction' => ['25000'],
            'class 26 - invalid SQL name' => ['26000'],
            'class 28 - invalid authorization' => ['28000'],
            'class 40 - transaction rollback' => ['40000'],
            'class 42 - syntax/access error' => ['42000'],
            'class HY - implementation defined' => ['HY000'],
        ];
    }

    // ==========================================================================
    // Doctrine Interface Tests
    // ==========================================================================

    public function testImplementsDoctrineDriverException(): void
    {
        $exception = new Exception('Test');

        self::assertInstanceOf(\Doctrine\DBAL\Driver\Exception::class, $exception);
    }

    public function testExtendsBaseException(): void
    {
        $exception = new Exception('Test');

        self::assertInstanceOf(\Exception::class, $exception);
    }

    // ==========================================================================
    // Common Firebird Error Code Tests
    // ==========================================================================

    #[DataProvider('firebirdErrorCodeProvider')]
    public function testCommonFirebirdErrorCodes(int $errorCode, string $description): void
    {
        $exception = Exception::fromErrorInfo($description, $errorCode);

        self::assertSame($errorCode, $exception->getCode());
        self::assertSame($description, $exception->getMessage());
    }

    /**
     * Common Firebird error codes for testing.
     *
     * @return array<string, array{int, string}>
     */
    public static function firebirdErrorCodeProvider(): array
    {
        return [
            'table does not exist' => [335544580, 'Table TEST does not exist'],
            'column does not exist' => [335544569, 'Column ID is not defined in table'],
            'primary key violation' => [335544665, 'Violation of PRIMARY or UNIQUE KEY'],
            'foreign key violation' => [335544466, 'Violation of FOREIGN KEY constraint'],
            'arithmetic exception' => [335544321, 'Arithmetic exception, numeric overflow'],
            'string truncation' => [335544914, 'String truncation'],
            'null value not allowed' => [335544347, 'Validation error for column'],
            'deadlock' => [335544336, 'Deadlock'],
            'lock conflict' => [335544345, 'Lock conflict on no wait transaction'],
        ];
    }
}
