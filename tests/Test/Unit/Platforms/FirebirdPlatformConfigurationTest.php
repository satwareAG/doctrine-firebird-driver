<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Exception\InvalidConfigurationException;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;

/**
 * Unit tests for FirebirdPlatformConfiguration.
 *
 * Tests configuration validation, default values, and error handling.
 */
final class FirebirdPlatformConfigurationTest extends TestCase
{
    public function testConstructorWithEmptyArrayUsesDefaults(): void
    {
        $config = new FirebirdPlatformConfiguration([]);

        self::assertSame(255, $config->getLikeCastLength());
    }

    public function testConstructorWithNoParametersUsesDefaults(): void
    {
        $config = new FirebirdPlatformConfiguration();

        self::assertSame(255, $config->getLikeCastLength());
    }

    #[DataProvider('provideValidLikeCastLengths')]
    public function testConstructorAcceptsValidLikeCastLength(int $length): void
    {
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => $length]);

        self::assertSame($length, $config->getLikeCastLength());
    }

    public static function provideValidLikeCastLengths(): Iterator
    {
        yield 'minimum value (1)' => [1];
        yield 'small value (50)' => [50];
        yield 'default value (255)' => [255];
        yield 'medium value (1000)' => [1000];
        yield 'large value (4000)' => [4000];
        yield 'maximum value (8191)' => [8191];
    }

    #[DataProvider('provideInvalidLikeCastLengthTypes')]
    public function testConstructorThrowsExceptionForInvalidType(mixed $value, string $expectedType): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Configuration parameter "like_cast_length" must be of type integer, ' . $expectedType . ' given.',
        );

        new FirebirdPlatformConfiguration(['like_cast_length' => $value]);
    }

    public static function provideInvalidLikeCastLengthTypes(): Iterator
    {
        yield 'string value' => ['255', 'string'];
        yield 'float value' => [255.0, 'float'];
        yield 'null value' => [null, 'null'];
        yield 'boolean value' => [true, 'bool'];
        yield 'array value' => [[255], 'array'];
    }

    #[DataProvider('provideOutOfRangeLikeCastLengths')]
    public function testConstructorThrowsExceptionForOutOfRangeValues(int $value): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Configuration parameter "like_cast_length" must be between 1 and 8191, ' . $value . ' given.',
        );

        new FirebirdPlatformConfiguration(['like_cast_length' => $value]);
    }

    public static function provideOutOfRangeLikeCastLengths(): Iterator
    {
        yield 'zero' => [0];
        yield 'negative value (-1)' => [-1];
        yield 'negative value (-100)' => [-100];
        yield 'above maximum (8192)' => [8192];
        yield 'above maximum (10000)' => [10000];
        yield 'very large value' => [999999];
    }

    public function testGetLikeCastLengthReturnsConfiguredValue(): void
    {
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 500]);

        $result = $config->getLikeCastLength();

        self::assertIsInt($result);
        self::assertSame(500, $result);
    }

    public function testConfigurationIsImmutableAfterConstruction(): void
    {
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 100]);

        // Verify initial value
        self::assertSame(100, $config->getLikeCastLength());

        // Configuration object is immutable (no setter methods)
        // Multiple calls return same value
        self::assertSame(100, $config->getLikeCastLength());
        self::assertSame(100, $config->getLikeCastLength());
    }

    public function testUnrecognizedOptionsAreIgnored(): void
    {
        $config = new FirebirdPlatformConfiguration([
            'like_cast_length' => 300,
            'unknown_option' => 'ignored',
            'another_option' => 123,
        ]);

        // Should not throw exception, unknown options ignored
        self::assertSame(300, $config->getLikeCastLength());
    }

    public function testBoundaryValueOne(): void
    {
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 1]);

        self::assertSame(1, $config->getLikeCastLength());
    }

    public function testBoundaryValueMaximum(): void
    {
        $config = new FirebirdPlatformConfiguration(['like_cast_length' => 8191]);

        self::assertSame(8191, $config->getLikeCastLength());
    }
}
