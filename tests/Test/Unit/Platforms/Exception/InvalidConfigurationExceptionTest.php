<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms\Exception;

use Exception;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Exception\InvalidConfigurationException;

/**
 * Unit tests for InvalidConfigurationException.
 *
 * Tests exception creation, error messages, and exception hierarchy.
 */
final class InvalidConfigurationExceptionTest extends TestCase
{
    public function testExtendsException(): void
    {
        $exception = InvalidConfigurationException::typeMismatch('test_param', 'integer', 'string');

        self::assertInstanceOf(Exception::class, $exception);
        self::assertInstanceOf(InvalidConfigurationException::class, $exception);
    }

    #[DataProvider('provideTypeMismatchData')]
    public function testTypeMismatchCreatesCorrectMessage(
        string $paramName,
        string $expectedType,
        string $actualType,
        string $expectedMessage,
    ): void {
        $exception = InvalidConfigurationException::typeMismatch($paramName, $expectedType, $actualType);

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    public static function provideTypeMismatchData(): Iterator
    {
        yield 'integer expected, string given' => [
            'like_cast_length',
            'integer',
            'string',
            'Configuration parameter "like_cast_length" must be of type integer, string given.',
        ];

        yield 'string expected, integer given' => [
            'some_param',
            'string',
            'integer',
            'Configuration parameter "some_param" must be of type string, integer given.',
        ];

        yield 'boolean expected, null given' => [
            'flag',
            'boolean',
            'null',
            'Configuration parameter "flag" must be of type boolean, null given.',
        ];

        yield 'array expected, object given' => [
            'config',
            'array',
            'object',
            'Configuration parameter "config" must be of type array, object given.',
        ];
    }

    #[DataProvider('provideRangeValidationData')]
    public function testRangeValidationCreatesCorrectMessage(
        string $paramName,
        int $min,
        int $max,
        int $actualValue,
        string $expectedMessage,
    ): void {
        $exception = InvalidConfigurationException::rangeValidation($paramName, $min, $max, $actualValue);

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    public static function provideRangeValidationData(): Iterator
    {
        yield 'value below minimum' => [
            'like_cast_length',
            1,
            8191,
            0,
            'Configuration parameter "like_cast_length" must be between 1 and 8191, 0 given.',
        ];

        yield 'value above maximum' => [
            'like_cast_length',
            1,
            8191,
            8192,
            'Configuration parameter "like_cast_length" must be between 1 and 8191, 8192 given.',
        ];

        yield 'negative value' => [
            'like_cast_length',
            1,
            8191,
            -100,
            'Configuration parameter "like_cast_length" must be between 1 and 8191, -100 given.',
        ];

        yield 'very large value' => [
            'like_cast_length',
            1,
            8191,
            999999,
            'Configuration parameter "like_cast_length" must be between 1 and 8191, 999999 given.',
        ];

        yield 'different range' => [
            'page_size',
            1,
            100,
            150,
            'Configuration parameter "page_size" must be between 1 and 100, 150 given.',
        ];
    }

    public function testTypeMismatchReturnsSameExceptionClass(): void
    {
        $exception = InvalidConfigurationException::typeMismatch('param', 'int', 'string');

        self::assertInstanceOf(InvalidConfigurationException::class, $exception);
    }

    public function testRangeValidationReturnsSameExceptionClass(): void
    {
        $exception = InvalidConfigurationException::rangeValidation('param', 1, 10, 20);

        self::assertInstanceOf(InvalidConfigurationException::class, $exception);
    }

    public function testTypeMismatchMessageIncludesAllComponents(): void
    {
        $exception = InvalidConfigurationException::typeMismatch('test', 'expected', 'actual');

        $message = $exception->getMessage();

        self::assertStringContainsString('test', $message);
        self::assertStringContainsString('expected', $message);
        self::assertStringContainsString('actual', $message);
        self::assertStringContainsString('must be of type', $message);
    }

    public function testRangeValidationMessageIncludesAllComponents(): void
    {
        $exception = InvalidConfigurationException::rangeValidation('param', 5, 15, 20);

        $message = $exception->getMessage();

        self::assertStringContainsString('param', $message);
        self::assertStringContainsString('5', $message);
        self::assertStringContainsString('15', $message);
        self::assertStringContainsString('20', $message);
        self::assertStringContainsString('must be between', $message);
    }

    public function testExceptionCanBeCaughtAsException(): void
    {
        try {
            throw InvalidConfigurationException::typeMismatch('test', 'int', 'string');
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidConfigurationException::class, $e);
            self::assertStringContainsString('test', $e->getMessage());
        }
    }

    public function testExceptionCanBeCaughtAsInvalidConfigurationException(): void
    {
        try {
            throw InvalidConfigurationException::rangeValidation('test', 1, 10, 20);
        } catch (InvalidConfigurationException $e) {
            self::assertStringContainsString('test', $e->getMessage());
            self::assertStringContainsString('must be between', $e->getMessage());
        }
    }
}
