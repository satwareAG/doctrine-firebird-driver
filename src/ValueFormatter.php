<?php
namespace Kafoso\DoctrineFirebirdDriver;

abstract class ValueFormatter
{
    /**
     * Ensures a string value is always returned so that e.g. null won't hide.  are encapsulated in double quotes and
     * backslahses and existing double quotes are escaped by additional backslashes. E.g. 'foo"\bar' becomes
     * 'foo\"\\bar'.
     *
     * @param mixed $value                      Any data type.
     * @return string
     */
    public static function cast(mixed $value)
    {
        if (is_object($value)) {
            return sprintf(
                "\\%s",
                $value::class
            );
        }
        if (is_array($value)) {
            return sprintf("Array(%d)", count($value));
        }
        if (is_bool($value)) {
            return ($value ? "true" : "false");
        }
        if (is_null($value)) {
            return "null";
        }
        if (is_resource($value)) {
            return "#{$value}";
        }
        if (is_string($value)) {
            return static::escapeAndQuote($value);
        }
        return @strval($value);
    }

    /**
     * @param string $str
     * @return string
     */
    public static function escapeAndQuote(string $str): string
    {
        $quotingCharacters = '"';
        $splitCharacters = preg_split('//', '\\' . $quotingCharacters);
        $escape = implode("", array_unique($splitCharacters !== false ? $splitCharacters : []));
        return $quotingCharacters . addcslashes($str, $escape) . $quotingCharacters;
    }

    /**
     * Shows the data type and the flattened value as a string. Strings are encapsulated in double quotes and
     * backslahses and existing double quotes are escaped by additional backslashes. E.g. 'foo"\bar' becomes
     * 'foo\"\\bar'.
     *
     * @param mixed $value                      Any data type.
     * @return string
     */
    public static function found(mixed $value)
    {
        if (is_object($value)) {
            return sprintf(
                "(object) \\%s",
                $value::class
            );
        }
        if (is_array($value)) {
            return sprintf("(array) Array(%d)", count($value));
        }
        if (is_bool($value)) {
            return sprintf(
                "(boolean) %s",
                ($value ? "true" : "false")
            );
        }
        if (is_null($value)) {
            return "(null) null";
        }
        if (is_resource($value)) {
            return "(resource) {$value}";
        }
        if (is_string($value)) {
            return sprintf(
                "(string) %s",
                static::escapeAndQuote($value)
            );
        }
        if (is_float($value)) {
            return sprintf(
                "(float) %s",
                strval($value)
            );
        }
        return sprintf(
            "(%s) %s",
            gettype($value),
            @strval($value)
        );
    }
}
