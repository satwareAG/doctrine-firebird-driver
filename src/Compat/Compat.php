<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Compat;

/**
 * Compatibility utilities for cross-version PHP support.
 *
 * This class provides polyfill-aware wrappers for functions introduced in
 * PHP 8.2, 8.3, 8.4, and 8.5, allowing the codebase to use modern PHP
 * features while maintaining PHP 8.1 compatibility.
 *
 * @see https://github.com/symfony/polyfill for polyfill implementations
 */
final class Compat
{
    /**
     * Validate JSON string without decoding (PHP 8.3+).
     *
     * Uses native json_validate() when available, falls back to
     * json_decode() for PHP < 8.3 (polyfill handles this).
     *
     * @param string $json  The JSON string to validate
     * @param positive-int $depth Maximum nesting depth (default: 512)
     *
     * @return bool True if valid JSON, false otherwise
     */
    public static function jsonValidate(string $json, int $depth = 512): bool
    {
        // Polyfill provides json_validate() on PHP < 8.3
        // Note: flags parameter removed as only 0 and JSON_INVALID_UTF8_IGNORE supported
        assert($depth >= 1, 'Depth must be at least 1');

        return json_validate($json, $depth);
    }

    /**
     * Find the first element matching a callback (PHP 8.4+).
     *
     * @template T
     *
     * @param array<T>         $array    The array to search
     * @param callable(T): bool $callback The callback returning true for match
     *
     * @return T|null The first matching element or null
     */
    public static function arrayFind(array $array, callable $callback): mixed
    {
        // Polyfill provides array_find() on PHP < 8.4
        return array_find($array, $callback);
    }

    /**
     * Find the key of the first element matching a callback (PHP 8.4+).
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue>    $array    The array to search
     * @param callable(TValue): bool $callback The callback returning true for match
     *
     * @return TKey|null The key of the first matching element or null
     */
    public static function arrayFindKey(array $array, callable $callback): int|string|null
    {
        // Polyfill provides array_find_key() on PHP < 8.4
        return array_find_key($array, $callback);
    }

    /**
     * Check if any array element matches a callback (PHP 8.4+).
     *
     * @template T
     *
     * @param array<T>         $array    The array to check
     * @param callable(T): bool $callback The callback returning true for match
     *
     * @return bool True if any element matches, false otherwise
     */
    public static function arrayAny(array $array, callable $callback): bool
    {
        // Polyfill provides array_any() on PHP < 8.4
        return array_any($array, $callback);
    }

    /**
     * Check if all array elements match a callback (PHP 8.4+).
     *
     * @template T
     *
     * @param array<T>         $array    The array to check
     * @param callable(T): bool $callback The callback returning true for match
     *
     * @return bool True if all elements match, false otherwise
     */
    public static function arrayAll(array $array, callable $callback): bool
    {
        // Polyfill provides array_all() on PHP < 8.4
        return array_all($array, $callback);
    }

    /**
     * Multibyte-safe string padding (PHP 8.3+).
     *
     * @param string $string     The input string
     * @param int    $length     The desired length
     * @param string $padString  The string to pad with (default: ' ')
     * @param int    $padType    Padding type: STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH
     * @param string $encoding   Character encoding (default: 'UTF-8')
     *
     * @return string The padded string
     */
    public static function mbStrPad(
        string $string,
        int $length,
        string $padString = ' ',
        int $padType = STR_PAD_RIGHT,
        ?string $encoding = null,
    ): string {
        // Polyfill provides mb_str_pad() on PHP < 8.3
        return mb_str_pad($string, $length, $padString, $padType, $encoding ?? 'UTF-8');
    }

    /**
     * Check if a string starts with a given substring.
     *
     * Note: This is a PHP 8.0+ function, included for completeness
     * and explicit documentation of string operations.
     *
     * @param string $haystack The string to search in
     * @param string $needle   The substring to search for
     *
     * @return bool True if haystack starts with needle
     */
    public static function strStartsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    /**
     * Check if a string ends with a given substring.
     *
     * Note: This is a PHP 8.0+ function, included for completeness
     * and explicit documentation of string operations.
     *
     * @param string $haystack The string to search in
     * @param string $needle   The substring to search for
     *
     * @return bool True if haystack ends with needle
     */
    public static function strEndsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    /**
     * Check if a string contains a given substring.
     *
     * Note: This is a PHP 8.0+ function, included for completeness
     * and explicit documentation of string operations.
     *
     * @param string $haystack The string to search in
     * @param string $needle   The substring to search for
     *
     * @return bool True if haystack contains needle
     */
    public static function strContains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * Check if an array is a list (sequential integer keys starting from 0).
     *
     * Note: This is a PHP 8.1+ function, included for completeness.
     *
     * @param array<mixed> $array The array to check
     *
     * @return bool True if array is a list
     */
    public static function arrayIsList(array $array): bool
    {
        return array_is_list($array);
    }
}
