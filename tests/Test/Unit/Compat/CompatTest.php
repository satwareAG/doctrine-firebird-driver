<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Compat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Compat\Compat;

#[CoversClass(Compat::class)]
final class CompatTest extends TestCase
{
    // ===== JSON Validation Tests =====

    #[DataProvider('validJsonProvider')]
    public function testJsonValidateWithValidJson(string $json): void
    {
        self::assertTrue(Compat::jsonValidate($json));
    }

    /** @return iterable<string, array{string}> */
    public static function validJsonProvider(): iterable
    {
        yield 'empty object' => ['{}'];
        yield 'empty array' => ['[]'];
        yield 'simple object' => ['{"key": "value"}'];
        yield 'nested object' => ['{"level1": {"level2": {"level3": "value"}}}'];
        yield 'array of numbers' => ['[1, 2, 3, 4, 5]'];
        yield 'array of strings' => ['["a", "b", "c"]'];
        yield 'mixed array' => ['[1, "two", true, null]'];
        yield 'boolean true' => ['true'];
        yield 'boolean false' => ['false'];
        yield 'null' => ['null'];
        yield 'number' => ['42'];
        yield 'negative number' => ['-123'];
        yield 'float' => ['3.14'];
        yield 'string' => ['"hello"'];
    }

    #[DataProvider('invalidJsonProvider')]
    public function testJsonValidateWithInvalidJson(string $json): void
    {
        self::assertFalse(Compat::jsonValidate($json));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidJsonProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'plain text' => ['hello'];
        yield 'missing quotes' => ['{key: "value"}'];
        yield 'single quotes' => ["{'key': 'value'}"];
        yield 'trailing comma' => ['{"key": "value",}'];
        yield 'invalid unicode' => ['"\uXXXX"'];
        yield 'unquoted string' => ['unquoted'];
    }

    public function testJsonValidateWithCustomDepth(): void
    {
        // Deeply nested JSON
        $deepJson = '{"a":{"b":{"c":{"d":"value"}}}}';
        
        // Should pass with sufficient depth
        self::assertTrue(Compat::jsonValidate($deepJson, 10));
        
        // Should fail with insufficient depth
        self::assertFalse(Compat::jsonValidate($deepJson, 2));
    }

    // ===== Array Find Tests =====

    public function testArrayFindReturnsMatchingElement(): void
    {
        $array = [1, 2, 3, 4, 5];
        $result = Compat::arrayFind($array, fn($v) => $v > 3);
        
        self::assertSame(4, $result);
    }

    public function testArrayFindReturnsNullWhenNoMatch(): void
    {
        $array = [1, 2, 3];
        $result = Compat::arrayFind($array, fn($v) => $v > 10);
        
        self::assertNull($result);
    }

    public function testArrayFindWithEmptyArray(): void
    {
        $result = Compat::arrayFind([], fn($v) => true);
        
        self::assertNull($result);
    }

    public function testArrayFindWithAssociativeArray(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Compat::arrayFind($array, fn($v) => $v === 2);
        
        self::assertSame(2, $result);
    }

    // ===== Array Find Key Tests =====

    public function testArrayFindKeyReturnsMatchingKey(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Compat::arrayFindKey($array, fn($v) => $v === 2);
        
        self::assertSame('b', $result);
    }

    public function testArrayFindKeyReturnsNullWhenNoMatch(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $result = Compat::arrayFindKey($array, fn($v) => $v > 10);
        
        self::assertNull($result);
    }

    public function testArrayFindKeyWithNumericKeys(): void
    {
        $array = [10 => 'a', 20 => 'b', 30 => 'c'];
        $result = Compat::arrayFindKey($array, fn($v) => $v === 'b');
        
        self::assertSame(20, $result);
    }

    // ===== Array Any Tests =====

    public function testArrayAnyReturnsTrueWhenMatchExists(): void
    {
        $array = [1, 2, 3, 4, 5];
        $result = Compat::arrayAny($array, fn($v) => $v > 3);
        
        self::assertTrue($result);
    }

    public function testArrayAnyReturnsFalseWhenNoMatch(): void
    {
        $array = [1, 2, 3];
        $result = Compat::arrayAny($array, fn($v) => $v > 10);
        
        self::assertFalse($result);
    }

    public function testArrayAnyWithEmptyArray(): void
    {
        $result = Compat::arrayAny([], fn($v) => true);
        
        self::assertFalse($result);
    }

    // ===== Array All Tests =====

    public function testArrayAllReturnsTrueWhenAllMatch(): void
    {
        $array = [2, 4, 6, 8];
        $result = Compat::arrayAll($array, fn($v) => $v % 2 === 0);
        
        self::assertTrue($result);
    }

    public function testArrayAllReturnsFalseWhenNotAllMatch(): void
    {
        $array = [2, 4, 5, 8];
        $result = Compat::arrayAll($array, fn($v) => $v % 2 === 0);
        
        self::assertFalse($result);
    }

    public function testArrayAllWithEmptyArray(): void
    {
        // Empty array should return true (vacuous truth)
        $result = Compat::arrayAll([], fn($v) => false);
        
        self::assertTrue($result);
    }

    // ===== Multibyte String Pad Tests =====

    public function testMbStrPadRight(): void
    {
        $result = Compat::mbStrPad('test', 10);
        
        self::assertSame('test      ', $result);
        self::assertSame(10, mb_strlen($result));
    }

    public function testMbStrPadLeft(): void
    {
        $result = Compat::mbStrPad('test', 10, ' ', STR_PAD_LEFT);
        
        self::assertSame('      test', $result);
    }

    public function testMbStrPadBoth(): void
    {
        $result = Compat::mbStrPad('test', 10, ' ', STR_PAD_BOTH);
        
        self::assertSame('   test   ', $result);
    }

    public function testMbStrPadWithCustomPadString(): void
    {
        $result = Compat::mbStrPad('test', 10, '-', STR_PAD_RIGHT);
        
        self::assertSame('test------', $result);
    }

    public function testMbStrPadWithMultibyteCharacters(): void
    {
        // UTF-8 multibyte characters
        $result = Compat::mbStrPad('日本', 5, '字');
        
        self::assertSame(5, mb_strlen($result));
    }

    public function testMbStrPadNoChangeWhenLengthSufficient(): void
    {
        $result = Compat::mbStrPad('testing', 5);
        
        self::assertSame('testing', $result);
    }

    // ===== String Helper Tests =====

    #[DataProvider('strStartsWithProvider')]
    public function testStrStartsWith(string $haystack, string $needle, bool $expected): void
    {
        self::assertSame($expected, Compat::strStartsWith($haystack, $needle));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function strStartsWithProvider(): iterable
    {
        yield 'starts with' => ['hello world', 'hello', true];
        yield 'does not start' => ['hello world', 'world', false];
        yield 'empty needle' => ['hello', '', true];
        yield 'empty haystack' => ['', 'hello', false];
        yield 'both empty' => ['', '', true];
        yield 'exact match' => ['hello', 'hello', true];
        yield 'case sensitive' => ['Hello', 'hello', false];
    }

    #[DataProvider('strEndsWithProvider')]
    public function testStrEndsWith(string $haystack, string $needle, bool $expected): void
    {
        self::assertSame($expected, Compat::strEndsWith($haystack, $needle));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function strEndsWithProvider(): iterable
    {
        yield 'ends with' => ['hello world', 'world', true];
        yield 'does not end' => ['hello world', 'hello', false];
        yield 'empty needle' => ['hello', '', true];
        yield 'empty haystack' => ['', 'hello', false];
        yield 'both empty' => ['', '', true];
        yield 'exact match' => ['hello', 'hello', true];
    }

    #[DataProvider('strContainsProvider')]
    public function testStrContains(string $haystack, string $needle, bool $expected): void
    {
        self::assertSame($expected, Compat::strContains($haystack, $needle));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function strContainsProvider(): iterable
    {
        yield 'contains at start' => ['hello world', 'hello', true];
        yield 'contains at end' => ['hello world', 'world', true];
        yield 'contains in middle' => ['hello world', 'o wo', true];
        yield 'does not contain' => ['hello world', 'foo', false];
        yield 'empty needle' => ['hello', '', true];
        yield 'empty haystack' => ['', 'hello', false];
        yield 'case sensitive' => ['Hello', 'hello', false];
    }

    // ===== Array Is List Tests =====

    #[DataProvider('arrayIsListProvider')]
    public function testArrayIsList(array $array, bool $expected): void
    {
        self::assertSame($expected, Compat::arrayIsList($array));
    }

    /** @return iterable<string, array{array<mixed>, bool}> */
    public static function arrayIsListProvider(): iterable
    {
        yield 'sequential list' => [[1, 2, 3], true];
        yield 'empty array' => [[], true];
        yield 'single element' => [['a'], true];
        yield 'associative' => [['a' => 1, 'b' => 2], false];
        yield 'non-zero start' => [[1 => 'a', 2 => 'b'], false];
        yield 'gap in keys' => [[0 => 'a', 2 => 'c'], false];
        yield 'mixed keys' => [[0 => 'a', 'b' => 'c'], false];
        yield 'string numeric keys' => [['0' => 'a', '1' => 'b'], true];
    }
}
