<?php
namespace Kafoso\DoctrineFirebirdDriver;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Type;

use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Connection;

use function array_fill;
use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function is_int;
use function key;
use function ksort;
use function preg_match_all;
use function sprintf;
use function strlen;
use function substr;

use const PREG_OFFSET_CAPTURE;

/**
 * Utility class that parses sql statements with regard to types and parameters.
 *
 * @internal
 */
class SQLParserUtils
{
    /**#@+
     *
     * @deprecated Will be removed as internal implementation details.
     */
    public const POSITIONAL_TOKEN = '\?';
    public const NAMED_TOKEN      = '(?<!:):[a-zA-Z_][a-zA-Z0-9_]*';
    // Quote characters within string literals can be preceded by a backslash.
    public const ESCAPED_SINGLE_QUOTED_TEXT   = "(?:'(?:\\\\)+'|'(?:[^'\\\\]|\\\\'?|'')*')";
    public const ESCAPED_DOUBLE_QUOTED_TEXT   = '(?:"(?:\\\\)+"|"(?:[^"\\\\]|\\\\"?)*")';
    public const ESCAPED_BACKTICK_QUOTED_TEXT = '(?:`(?:\\\\)+`|`(?:[^`\\\\]|\\\\`?)*`)';
    /**#@-*/

    private const ESCAPED_BRACKET_QUOTED_TEXT = '(?<!\b(?i:ARRAY))\[(?:[^\]])*\]';

    /**
     * Gets an array of the placeholders in an sql statements as keys and their positions in the query string.
     *
     * For a statement with positional parameters, returns a zero-indexed list of placeholder position.
     * For a statement with named parameters, returns a map of placeholder positions to their parameter names.
     *
     * @deprecated Will be removed as internal implementation detail.
     *
     * @param string $statement
     * @param bool   $isPositional
     *
     * @return int[]|string[]
     */
    public static function getPlaceholderPositions($statement, $isPositional = true)
    {
        return $isPositional
            ? self::getPositionalPlaceholderPositions($statement)
            : self::getNamedPlaceholderPositions($statement);
    }

    /**
     * Returns a zero-indexed list of placeholder position.
     *
     * @return list<int>
     */
    private static function getPositionalPlaceholderPositions(string $statement): array
    {
        return self::collectPlaceholders(
            $statement,
            '?',
            self::POSITIONAL_TOKEN,
            static function (string $_, int $placeholderPosition, int $fragmentPosition, array &$carry): void {
                $carry[] = $placeholderPosition + $fragmentPosition;
            }
        );
    }

    /**
     * Returns a map of placeholder positions to their parameter names.
     *
     * @return array<int,string>
     */
    private static function getNamedPlaceholderPositions(string $statement): array
    {
        return self::collectPlaceholders(
            $statement,
            ':',
            self::NAMED_TOKEN,
            static function (
                string $placeholder,
                int $placeholderPosition,
                int $fragmentPosition,
                array &$carry
            ): void {
                $carry[$placeholderPosition + $fragmentPosition] = substr($placeholder, 1);
            }
        );
    }

    /**
     * @return mixed[]
     */
    private static function collectPlaceholders(
        string $statement,
        string $match,
        string $token,
        callable $collector
    ): array {
        if (!str_contains($statement, $match)) {
            return [];
        }

        $carry = [];

        foreach (self::getUnquotedStatementFragments($statement) as $fragment) {
            preg_match_all('/' . $token . '/', (string) $fragment[0], $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $placeholder) {
                $collector($placeholder[0], $placeholder[1], $fragment[1], $carry);
            }
        }

        return $carry;
    }

    /**
     * Slice the SQL statement around pairs of quotes and
     * return string fragments of SQL outside of quoted literals.
     * Each fragment is captured as a 2-element array:
     *
     * 0 => matched fragment string,
     * 1 => offset of fragment in $statement
     *
     * @param string $statement
     *
     * @return mixed[][]
     */
    private static function getUnquotedStatementFragments($statement)
    {
        $literal    = self::ESCAPED_SINGLE_QUOTED_TEXT . '|' .
            self::ESCAPED_DOUBLE_QUOTED_TEXT . '|' .
            self::ESCAPED_BACKTICK_QUOTED_TEXT . '|' .
            self::ESCAPED_BRACKET_QUOTED_TEXT;
        $expression = sprintf('/((.+(?i:ARRAY)\\[.+\\])|([^\'"`\\[]+))(?:%s)?/s', $literal);

        preg_match_all($expression, $statement, $fragments, PREG_OFFSET_CAPTURE);

        return $fragments[1];
    }
}
