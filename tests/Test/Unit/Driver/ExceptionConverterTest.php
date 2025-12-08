<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Exception\DatabaseObjectExistsException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter;

/**
 * Unit tests for ExceptionConverter.
 *
 * Tests the mapping of Firebird error codes to DBAL exception types.
 */
class ExceptionConverterTest extends TestCase
{
    private ExceptionConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ExceptionConverter();
    }

    /**
     * @param class-string<DriverException> $expectedClass
     *
     * @dataProvider provideExceptionMappings
     */
    public function testConvertException(int $errorCode, string $message, string $expectedClass): void
    {
        $driverException = $this->createDriverException($errorCode, $message);
        $result = $this->converter->convert($driverException, null);

        self::assertInstanceOf($expectedClass, $result);
    }

    /**
     * @return iterable<string, array{int, string, class-string<DriverException>}>
     */
    public static function provideExceptionMappings(): iterable
    {
        // -104: Syntax Error
        yield 'syntax error' => [-104, 'Unexpected token near SELECT', SyntaxErrorException::class];

        // -204: Object not found
        yield 'table unknown' => [-204, 'Table unknown: MYTABLE', TableNotFoundException::class];
        yield 'ambiguous field name' => [-204, 'Ambiguous field name between TABLE1 and TABLE2', NonUniqueFieldNameException::class];
        yield 'generic object not found' => [-204, 'Procedure MYPROC not found', DatabaseObjectNotFoundException::class];

        // -206: Column unknown
        yield 'column unknown' => [-206, 'Column unknown: MYCOLUMN', InvalidFieldNameException::class];
        yield 'other -206 error' => [-206, 'Some other error', DriverException::class];

        // Foreign key violations
        yield 'FK -303' => [-303, 'arithmetic exception', ForeignKeyConstraintViolationException::class];
        yield 'FK -315' => [-315, 'Cannot change datatype', ForeignKeyConstraintViolationException::class];
        yield 'FK -406' => [-406, 'Subscript out of bounds', ForeignKeyConstraintViolationException::class];
        yield 'FK -413' => [-413, 'Conversion error from string', ForeignKeyConstraintViolationException::class];
        yield 'FK -501' => [-501, 'Subscript error', ForeignKeyConstraintViolationException::class];
        yield 'FK -530' => [-530, 'Foreign key violation', ForeignKeyConstraintViolationException::class];

        // -607: Object exists/not exists
        yield 'table already exists' => [-607, 'Table MYTABLE already exist', TableExistsException::class];
        yield 'table does not exist' => [-607, 'Table MYTABLE does not exist', TableNotFoundException::class];
        yield 'object not found' => [-607, 'Object not found', DatabaseObjectNotFoundException::class];
        yield 'object not defined' => [-607, 'Generator not defined', DatabaseObjectNotFoundException::class];
        yield 'other -607 error' => [-607, 'Some other error', DriverException::class];

        // -625: NOT NULL violation
        yield 'null value violation' => [-625, 'validation error for column, value "*** null ***"', NotNullConstraintViolationException::class];
        yield 'other -625 error' => [-625, 'Some other validation error', DriverException::class];

        // -803: Unique constraint
        yield 'unique constraint' => [-803, 'Unique constraint violation', UniqueConstraintViolationException::class];

        // -804: Data type / NOT NULL
        yield 'data type unknown' => [-804, 'Data type unknown for MYCOLUMN', DriverException::class];
        yield 'not null -804' => [-804, 'Some NOT NULL violation', NotNullConstraintViolationException::class];

        // -901: General engine error
        yield 'file not found' => [-901, 'Unable to open database: No such file or directory', DatabaseDoesNotExist::class];
        yield 'transaction deadlock -901' => [-901, 'Transaction deadlock detected', DeadlockException::class];
        yield 'other -901 connection error' => [-901, 'Database engine error', ConnectionException::class];

        // -902: Internal errors, connection issues
        yield 'no file -902' => [-902, 'No such file or directory', DatabaseDoesNotExist::class];
        yield 'deadlock -902' => [-902, 'Transaction deadlock', DeadlockException::class];
        yield 'connection error -902' => [-902, 'Internal error', ConnectionException::class];

        // -913: Deadlock
        yield 'deadlock -913' => [-913, 'Deadlock detected', DeadlockException::class];

        // -922: Connection error
        yield 'connection error -922' => [-922, 'Database connection error', ConnectionException::class];

        // -955: Object already exists
        yield 'object already exists' => [-955, 'Object already exists: MYINDEX', DatabaseObjectExistsException::class];
        yield 'other -955 error' => [-955, 'Some other error', DriverException::class];

        // -979: Lock wait timeout
        yield 'lock wait timeout' => [-979, 'Lock wait timeout', LockWaitTimeoutException::class];

        // Unknown error codes -> generic DriverException
        yield 'unknown error' => [-9999, 'Unknown error', DriverException::class];
    }

    public function testConvertWithQuery(): void
    {
        $query = new Query('SELECT * FROM users WHERE id = ?', [1], []);
        $driverException = $this->createDriverException(-104, 'Syntax error');

        $result = $this->converter->convert($driverException, $query);

        self::assertInstanceOf(SyntaxErrorException::class, $result);
    }

    public function testExceptionContainsCaseInsensitive(): void
    {
        // Should match "Table unknown" regardless of case
        $driverException = $this->createDriverException(-204, 'TABLE UNKNOWN: MYTABLE');
        $result = $this->converter->convert($driverException, null);

        self::assertInstanceOf(TableNotFoundException::class, $result);
    }

    private function createDriverException(int $code, string $message): DriverExceptionInterface
    {
        return new class ($message, $code) extends \Exception implements DriverExceptionInterface {
            public function __construct(string $message, int $code)
            {
                parent::__construct($message, $code);
            }

            public function getSQLState(): ?string
            {
                return null;
            }
        };
    }
}
