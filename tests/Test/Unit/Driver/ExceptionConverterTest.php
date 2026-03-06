<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DatabaseObjectExistsException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException as DBALDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter;

/**
 * Unit tests for ExceptionConverter - covers SQLCODE-based and SQLSTATE-based exception mapping.
 */
final class ExceptionConverterTest extends TestCase
{
    private ExceptionConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ExceptionConverter();
    }

    /** Create a DriverException stub with given code and message via anonymous class. */
    private function makeException(int $code, string $message = ''): DriverException
    {
        return new class ($code, $message) extends \RuntimeException implements DriverException {
            public function __construct(int $code, string $message)
            {
                parent::__construct($message, $code);
            }

            public function getSQLState(): ?string
            {
                return null;
            }
        };
    }

    // -------------------------------------------------------------------------
    // SQLCODE-based paths (switch statement in convert())
    // -------------------------------------------------------------------------

    public function testConvertSyntaxError(): void
    {
        $result = $this->converter->convert($this->makeException(-104), null);
        self::assertInstanceOf(SyntaxErrorException::class, $result);
    }

    public function testConvertTableNotFoundException(): void
    {
        $result = $this->converter->convert($this->makeException(-204, 'table unknown: MYTABLE'), null);
        self::assertInstanceOf(TableNotFoundException::class, $result);
    }

    public function testConvertNonUniqueFieldNameException(): void
    {
        $result = $this->converter->convert($this->makeException(-204, 'ambiguous field name'), null);
        self::assertInstanceOf(\Doctrine\DBAL\Exception\NonUniqueFieldNameException::class, $result);
    }

    public function testConvertDatabaseObjectNotFoundException(): void
    {
        $result = $this->converter->convert($this->makeException(-204, 'procedure unknown'), null);
        self::assertInstanceOf(DatabaseObjectNotFoundException::class, $result);
    }

    public function testConvertInvalidFieldNameException(): void
    {
        $result = $this->converter->convert($this->makeException(-206, 'column unknown: BADCOL'), null);
        self::assertInstanceOf(InvalidFieldNameException::class, $result);
    }

    public function testConvertCode206FallsThrough(): void
    {
        // -206 without 'column unknown' falls through to generic DriverException
        $result = $this->converter->convert($this->makeException(-206, 'some other -206 error'), null);
        self::assertInstanceOf(DBALDriverException::class, $result);
        self::assertNotInstanceOf(InvalidFieldNameException::class, $result);
    }

    /** @return array<string, array{int, class-string}> */
    public static function foreignKeyCodeProvider(): array
    {
        return [
            'arithmetic exception -303' => [-303, ForeignKeyConstraintViolationException::class],
            'cannot change datatype -315' => [-315, ForeignKeyConstraintViolationException::class],
            'subscript -406' => [-406, ForeignKeyConstraintViolationException::class],
            'conversion error -413' => [-413, ForeignKeyConstraintViolationException::class],
            'subscript -501' => [-501, ForeignKeyConstraintViolationException::class],
            'foreign key violation -530' => [-530, ForeignKeyConstraintViolationException::class],
        ];
    }

    #[DataProvider('foreignKeyCodeProvider')]
    public function testConvertForeignKeyVariants(int $code, string $expectedClass): void
    {
        $result = $this->converter->convert($this->makeException($code), null);
        self::assertInstanceOf($expectedClass, $result);
    }

    public function testConvertTableExistsException(): void
    {
        $result = $this->converter->convert($this->makeException(-607, 'already exist'), null);
        self::assertInstanceOf(TableExistsException::class, $result);
    }

    public function testConvertTableNotFoundOn607(): void
    {
        $result = $this->converter->convert($this->makeException(-607, 'does not exist'), null);
        self::assertInstanceOf(TableNotFoundException::class, $result);
    }

    public function testConvertDatabaseObjectNotFoundOn607(): void
    {
        $result = $this->converter->convert($this->makeException(-607, 'not found'), null);
        self::assertInstanceOf(DatabaseObjectNotFoundException::class, $result);
    }

    public function testConvert607FallsThrough(): void
    {
        $result = $this->converter->convert($this->makeException(-607, 'some unknown code 607 error'), null);
        self::assertInstanceOf(DBALDriverException::class, $result);
        self::assertNotInstanceOf(TableExistsException::class, $result);
        self::assertNotInstanceOf(TableNotFoundException::class, $result);
    }

    public function testConvertNotNullOn625(): void
    {
        $result = $this->converter->convert($this->makeException(-625, 'value "*** null ***" in column'), null);
        self::assertInstanceOf(NotNullConstraintViolationException::class, $result);
    }

    public function testConvert625FallsThrough(): void
    {
        $result = $this->converter->convert($this->makeException(-625, 'other validation error'), null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    public function testConvertUniqueConstraintViolation(): void
    {
        $result = $this->converter->convert($this->makeException(-803), null);
        self::assertInstanceOf(UniqueConstraintViolationException::class, $result);
    }

    public function testConvertDataTypeUnknownOn804(): void
    {
        $result = $this->converter->convert($this->makeException(-804, 'data type unknown'), null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    public function testConvertNotNullOn804(): void
    {
        $result = $this->converter->convert($this->makeException(-804, 'null constraint violation'), null);
        self::assertInstanceOf(NotNullConstraintViolationException::class, $result);
    }

    public function testConvertDatabaseDoesNotExist(): void
    {
        $result = $this->converter->convert($this->makeException(-902, 'no such file or directory'), null);
        self::assertInstanceOf(\Doctrine\DBAL\Exception\DatabaseDoesNotExist::class, $result);
    }

    public function testConvertTransactionDeadlockOn901(): void
    {
        $result = $this->converter->convert($this->makeException(-901, 'transaction deadlock'), null);
        self::assertInstanceOf(DeadlockException::class, $result);
    }

    public function testConvertConnectionLostOn901NetWrite(): void
    {
        $result = $this->converter->convert($this->makeException(-901, 'net write error'), null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertConnectionLostOn901NetRead(): void
    {
        $result = $this->converter->convert($this->makeException(-901, 'net read error'), null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertConnectionLostOn901LostRemote(): void
    {
        $result = $this->converter->convert($this->makeException(-901, 'lost remote part of database'), null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertConnectionLostOn901BrokenPipe(): void
    {
        $result = $this->converter->convert($this->makeException(-901, 'broken pipe'), null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertConnectionExceptionOn901(): void
    {
        $result = $this->converter->convert($this->makeException(-901, 'general engine error'), null);
        self::assertInstanceOf(ConnectionException::class, $result);
    }

    public function testConvertConnectionExceptionOn902(): void
    {
        $result = $this->converter->convert($this->makeException(-902, 'database connection error'), null);
        self::assertInstanceOf(ConnectionException::class, $result);
    }

    public function testConvertConnectionLostOn922NetWrite(): void
    {
        $result = $this->converter->convert($this->makeException(-922, 'net write error'), null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertConnectionLostOn922NetRead(): void
    {
        $result = $this->converter->convert($this->makeException(-922, 'net read error'), null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertConnectionExceptionOn922(): void
    {
        $result = $this->converter->convert($this->makeException(-922, 'general connection error'), null);
        self::assertInstanceOf(ConnectionException::class, $result);
    }

    public function testConvertDeadlockOn913(): void
    {
        $result = $this->converter->convert($this->makeException(-913), null);
        self::assertInstanceOf(DeadlockException::class, $result);
    }

    public function testConvertDatabaseObjectExistsOn955(): void
    {
        $result = $this->converter->convert($this->makeException(-955, 'already exists'), null);
        self::assertInstanceOf(DatabaseObjectExistsException::class, $result);
    }

    public function testConvert955FallsThrough(): void
    {
        $result = $this->converter->convert($this->makeException(-955, 'other error'), null);
        self::assertInstanceOf(DBALDriverException::class, $result);
        self::assertNotInstanceOf(DatabaseObjectExistsException::class, $result);
    }

    public function testConvertLockWaitTimeoutOn979(): void
    {
        $result = $this->converter->convert($this->makeException(-979), null);
        self::assertInstanceOf(LockWaitTimeoutException::class, $result);
    }

    public function testConvertUnknownCodeReturnsDriverException(): void
    {
        $result = $this->converter->convert($this->makeException(-999), null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    // -------------------------------------------------------------------------
    // SQLSTATE-based paths via \Firebird\Exception (requires php-firebird 7.x)
    // -------------------------------------------------------------------------

    /**
     * Create a stub that is both \Firebird\Exception AND Doctrine\DBAL\Driver\Exception.
     * Returns null if the \Firebird\Exception class is not available (extension not loaded).
     *
     * The anonymous class extends \Firebird\Exception (so instanceof checks pass in convert())
     * AND implements DriverException so it satisfies the convert() parameter type.
     */
    private function makeFirebirdException(string $sqlState, int $code = 0, string $message = ''): ?DriverException
    {
        if (!class_exists(\Firebird\Exception::class)) {
            return null;
        }

        // \Firebird\Exception implements Throwable+Stringable. PHP method names are
        // case-insensitive, so getSqlState() satisfies DriverException::getSQLState().
        // We add explicit "implements DriverException" and override getSqlState() only.
        return new class ($sqlState) extends \Firebird\Exception implements DriverException {
            private string $testSqlState;

            public function __construct(string $sqlState)
            {
                $this->testSqlState = $sqlState;
                // Do NOT call parent::__construct
            }

            public function getSqlState(): string
            {
                return $this->testSqlState;
            }
            // getSQLState() is satisfied case-insensitively by getSqlState() above
        };
    }

    public function testConvertBySqlState_08006_ConnectionLost(): void
    {
        $e = $this->makeFirebirdException('08006');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertBySqlState_08007_ConnectionLost(): void
    {
        $e = $this->makeFirebirdException('08007');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(ConnectionLost::class, $result);
    }

    public function testConvertBySqlState_08000_ConnectionException(): void
    {
        $e = $this->makeFirebirdException('08000');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(ConnectionException::class, $result);
    }

    public function testConvertBySqlState_21000_DriverException(): void
    {
        $e = $this->makeFirebirdException('21000');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    public function testConvertBySqlState_22000_DriverException(): void
    {
        $e = $this->makeFirebirdException('22001');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    public function testConvertBySqlState_23502_NotNullViolation(): void
    {
        $e = $this->makeFirebirdException('23502');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(NotNullConstraintViolationException::class, $result);
    }

    public function testConvertBySqlState_23503_ForeignKeyViolation(): void
    {
        $e = $this->makeFirebirdException('23503');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(ForeignKeyConstraintViolationException::class, $result);
    }

    public function testConvertBySqlState_23505_UniqueViolation(): void
    {
        $e = $this->makeFirebirdException('23505');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(UniqueConstraintViolationException::class, $result);
    }

    public function testConvertBySqlState_23000_GenericConstraintViolation(): void
    {
        $e = $this->makeFirebirdException('23000');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        // 23000 is generic - maps to base DriverException (not a specific subtype)
        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    public function testConvertBySqlState_28000_AuthorizationException(): void
    {
        $e = $this->makeFirebirdException('28000');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(ConnectionException::class, $result);
    }

    public function testConvertBySqlState_40001_Deadlock(): void
    {
        $e = $this->makeFirebirdException('40001');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(DeadlockException::class, $result);
    }

    public function testConvertBySqlState_42000_SyntaxError(): void
    {
        $e = $this->makeFirebirdException('42000');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(SyntaxErrorException::class, $result);
    }

    public function testConvertBySqlState_HY000_FallsBackToSqlCode(): void
    {
        // HY class falls back to SQLCODE; with code 0, returns generic DriverException
        $e = $this->makeFirebirdException('HY000', 0);
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }

    public function testConvertBySqlState_UnknownClass_FallsBackToSqlCode(): void
    {
        // Unknown SQLSTATE class → convertBySqlState returns null → falls back to SQLCODE
        // Anonymous stub has getCode()=0 (no parent::__construct) → generic DriverException
        $e = $this->makeFirebirdException('99999');
        if ($e === null) {
            self::markTestSkipped('\Firebird\Exception not available');
        }

        $result = $this->converter->convert($e, null);
        self::assertInstanceOf(DBALDriverException::class, $result);
    }
}
