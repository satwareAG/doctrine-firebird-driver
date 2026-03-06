<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;

/**
 * Tests for Exception::fromThrowable covering branching paths.
 */
#[CoversClass(Exception::class)]
class ExceptionFromThrowableTest extends TestCase
{
    public function testFromThrowableReturnsIdentityWhenAlreadyFirebirdException(): void
    {
        $original = new Exception('already a firebird exception', '42000', 0);

        $result = Exception::fromThrowable($original);

        // Must be the exact same instance (identity check)
        self::assertSame($original, $result);
    }

    public function testFromThrowableWrapsGenericException(): void
    {
        $generic = new RuntimeException('some error', 99);

        $result = Exception::fromThrowable($generic);

        self::assertNotSame($generic, $result);
        self::assertInstanceOf(Exception::class, $result);
        self::assertSame('some error', $result->getMessage());
        self::assertSame(99, $result->getCode());
        self::assertSame($generic, $result->getPrevious());
    }

    public function testFromThrowableExtractsSqlStateFromExceptionWithGetSqlStateMethod(): void
    {
        // Create an anonymous class that has a getSqlState() method
        $withSqlState = new class ('error with sqlstate') extends RuntimeException {
            public function getSqlState(): string
            {
                return '23000';
            }
        };

        $result = Exception::fromThrowable($withSqlState);

        self::assertInstanceOf(Exception::class, $result);
        // SQLSTATE '23000' should have been extracted via getSqlState()
        self::assertSame('23000', $result->getSQLState());
    }
}
