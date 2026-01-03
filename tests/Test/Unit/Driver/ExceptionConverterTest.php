<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException as DBALDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter;

/**
 * Unit tests for ExceptionConverter class.
 *
 * Tests the conversion of Firebird-specific error codes to
 * Doctrine DBAL exception types.
 */
#[CoversClass(ExceptionConverter::class)]
class ExceptionConverterTest extends TestCase
{
    private ExceptionConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ExceptionConverter();
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('exceptionConversionProvider')]
    public function testExceptionConversion(int $errorCode, string $message, string $expectedExceptionClass): void
    {
        $exception = new TestDriverException($message, $errorCode);
        $query = new Query('SELECT 1', [], []);

        $result = $this->converter->convert($exception, $query);

        self::assertInstanceOf($expectedExceptionClass, $result);
    }

    /**
     * Provides Firebird SQLCODE values for exception conversion testing.
     *
     * Firebird uses negative SQLCODE values (-104, -204, etc.) not ISC error codes.
     * Some codes require specific message text for proper classification.
     *
     * @return array<string, array{int, string, class-string}>
     */
    public static function exceptionConversionProvider(): array
    {
        return [
            // Syntax errors (-104)
            'syntax error' => [-104, 'Syntax error in SQL statement', SyntaxErrorException::class],

            // Table not found (-204 with "table unknown" in message)
            'table not found' => [-204, 'Table unknown: TEST_TABLE', TableNotFoundException::class],

            // Ambiguous field name (-204 with "ambiguous field name")
            'ambiguous field name' => [-204, 'Ambiguous field name between tables', NonUniqueFieldNameException::class],

            // Invalid field name (-206 with "column unknown")
            'invalid field name' => [-206, 'Column unknown: INVALID_COL', InvalidFieldNameException::class],

            // Foreign key violation (-530)
            'foreign key violation' => [-530, 'Foreign key constraint violation', ForeignKeyConstraintViolationException::class],

            // Table exists (-607 with "already exist")
            'table exists' => [-607, 'Table TEST already exists', TableExistsException::class],

            // Table not found (-607 with "does not exist")
            'table does not exist' => [-607, 'Table does not exist', TableNotFoundException::class],

            // Not null constraint (-625 with specific message)
            'not null constraint' => [-625, 'validation error for column, value "*** null ***"', NotNullConstraintViolationException::class],

            // Unique constraint violation (-803)
            'unique constraint' => [-803, 'Unique constraint violation', UniqueConstraintViolationException::class],

            // Not null from -804
            'not null from 804' => [-804, 'Null value not allowed', NotNullConstraintViolationException::class],

            // Database not found (-902 with "no such file")
            'database not found' => [-902, 'No such file or directory', DatabaseDoesNotExist::class],

            // Deadlock (-902 with "transaction deadlock")
            'deadlock from 902' => [-902, 'Transaction deadlock detected', DeadlockException::class],

            // Connection error (-902 general)
            'connection error 902' => [-902, 'Unable to connect', ConnectionException::class],

            // Deadlock (-913)
            'deadlock' => [-913, 'Deadlock detected', DeadlockException::class],

            // Connection error (-922)
            'connection error 922' => [-922, 'Connection refused', ConnectionException::class],

            // Default fallback (unknown code)
            'unknown error' => [-999999, 'Unknown error', DBALDriverException::class],
        ];
    }

    public function testConvertWithNullQuery(): void
    {
        // -104 is Firebird SQLCODE for syntax error
        $exception = new TestDriverException('Syntax error in statement', -104);

        $result = $this->converter->convert($exception, null);

        self::assertInstanceOf(SyntaxErrorException::class, $result);
    }

    public function testConvertUnknownErrorFallsBackToDriverException(): void
    {
        // Unknown error code should fall back to generic DriverException
        $exception = new TestDriverException('Unknown error occurred', -12345);
        $query = new Query('INSERT INTO test VALUES (1)', [], []);

        $result = $this->converter->convert($exception, $query);

        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    public function testConvertDataTypeUnknownError(): void
    {
        // -804 with "data type unknown" message should return DriverException
        $exception = new TestDriverException('data type unknown', -804);
        $query = new Query('SELECT * FROM test', [], []);

        $result = $this->converter->convert($exception, $query);

        self::assertInstanceOf(DBALDriverException::class, $result);
    }
}
