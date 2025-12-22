<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Result;

/**
 * Unit tests for the Result class.
 *
 * Tests basic functionality and DateTimeImmutable fetch support (php-firebird v7.0.0+).
 */
#[CoversClass(Result::class)]
class ResultTest extends TestCase
{
    // ==========================================================================
    // Basic Result Tests
    // ==========================================================================

    public function testBasics(): void
    {
        $connection = $this->createMockConnection();
        $resource = null;
        $result = new Result($resource, $connection);

        self::assertSame(0, $result->columnCount());
        self::assertSame(0, $result->rowCount());

        self::assertFalse($result->fetchNumeric());
        self::assertFalse($result->fetchAssociative());
    }

    public function testFetchNumericReturnsFalseForNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        self::assertFalse($result->fetchNumeric());
    }

    public function testFetchAssociativeReturnsFalseForNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        self::assertFalse($result->fetchAssociative());
    }

    public function testColumnCountReturnsZeroForNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        self::assertSame(0, $result->columnCount());
    }

    public function testRowCountReturnsZeroForNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        self::assertSame(0, $result->rowCount());
    }

    public function testRowCountReturnsIntegerWhenResourceIsNumeric(): void
    {
        $connection = $this->createMockConnection();
        // When a DML statement is executed, the result is the affected row count as integer
        $result = new Result(42, $connection);

        self::assertSame(42, $result->rowCount());
    }

    // ==========================================================================
    // DateTimeImmutable Fetch Tests (php-firebird v7.0.0+)
    // ==========================================================================

    public function testFetchNumericWithDateObjectsReturnsFalseForNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        self::assertFalse($result->fetchNumericWithDateObjects());
    }

    public function testFetchAssociativeWithDateObjectsReturnsFalseForNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        self::assertFalse($result->fetchAssociativeWithDateObjects());
    }

    public function testFetchAllAssociativeWithDateObjectsReturnsEmptyArrayForNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        self::assertSame([], $result->fetchAllAssociativeWithDateObjects());
    }

    public function testIsDateObjectFetchAvailableReturnsBoolean(): void
    {
        // This test just ensures the static method is callable and returns boolean
        $available = Result::isDateObjectFetchAvailable();

        self::assertIsBool($available);
    }

    public function testIsDateObjectFetchAvailableReturnsTrueWhenConstantDefined(): void
    {
        // If FBIRD_FETCH_DATE_OBJ is defined (php-firebird v7+), should return true
        // Otherwise false
        $expected = defined('FBIRD_FETCH_DATE_OBJ');

        self::assertSame($expected, Result::isDateObjectFetchAvailable());
    }

    // ==========================================================================
    // free() Tests
    // ==========================================================================

    public function testFreeHandlesNullResource(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result(null, $connection);

        // Should not throw
        $result->free();

        // After free, fetch should return false
        self::assertFalse($result->fetchNumeric());
    }

    public function testFreeHandlesNonResourceValue(): void
    {
        $connection = $this->createMockConnection();
        $result = new Result('not a resource', $connection);

        // Should not throw
        $result->free();

        // After free, column count should be 0
        self::assertSame(0, $result->columnCount());
    }

    // ==========================================================================
    // Helper Methods
    // ==========================================================================

    protected function createMockConnection(): Connection
    {
        return new Connection(null, 'dummy', false, null, []);
    }
}
