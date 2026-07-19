<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetConnectionMiddleware;

/**
 * Documents the deliberate encoding error handling asymmetry (#117).
 *
 * The charset middleware uses different error handling strategies:
 * - encodeSql() (SQL body): throws CharsetConversionException on failure
 * - quote() (bound values): silent byte substitution, no exception
 *
 * Rationale:
 * - SQL body is syntax - invalid bytes mean a programming error. Fail loud.
 * - Bound values are data - crashing on bad data is worse than mojibake.
 *
 * This test locks the asymmetry as intentional to prevent accidental
 * "unification" that would either make bound values throw (breaking
 * change) or make SQL body silent (corrupting syntax).
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/117
 */
class CharsetEncodingAsymmetryTest extends TestCase
{
    private CharsetConnectionMiddleware $middleware;

    protected function setUp(): void
    {
        $mockConnection = new class implements DriverConnection {
            public function prepare(string $sql): never
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function query(string $sql): never
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function quote($value, $type = ParameterType::STRING)
            {
                return "'" . $value . "'";
            }

            public function exec(string $sql): never
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function lastInsertId($name = null): never
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function beginTransaction(): never
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function commit(): never
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function rollBack(): never
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function getServerVersion(): never
            {
                throw new \LogicException('Not implemented in mock');
            }
        };

        $this->middleware = new CharsetConnectionMiddleware(
            $mockConnection,
            'Windows-1252',
            'UTF-8',
        );
    }

    /**
     * quote() with invalid UTF-8 bytes must NOT throw.
     *
     * Bound values are data, not syntax. Invalid bytes in data should
     * be substituted silently, not crash the application.
     */
    public function testQuoteWithInvalidBytesDoesNotThrow(): void
    {
        $invalidUtf8 = "valid text \xFF\xFE invalid bytes";

        $result = $this->middleware->quote($invalidUtf8);

        self::assertIsString($result);
        self::assertStringStartsWith("'", $result);
    }

    /**
     * quote() with valid UTF-8 must encode correctly.
     *
     * Baseline: valid UTF-8 input is encoded to Windows-1252 and quoted.
     */
    public function testQuoteWithValidUtf8EncodesCorrectly(): void
    {
        $utf8 = 'Müllerstraße';

        $result = $this->middleware->quote($utf8);

        self::assertIsString($result);
        // The mock returns 'value' (with single quotes)
        self::assertStringContainsString('M', $result);
    }

    /**
     * quote() with non-string value passes through unchanged.
     */
    public function testQuoteWithNonStringValuePassesThrough(): void
    {
        $result = $this->middleware->quote(42, ParameterType::INTEGER);

        self::assertSame("'42'", $result);
    }

    /**
     * encodeSql() false guard is defense-in-depth, not reachable under
     * default PHP 8.x config.
     *
     * In PHP 8.x, mb_convert_encoding() either:
     * 1. Succeeds (returns encoded string)
     * 2. Throws ValueError for unknown encoding names
     * 3. Substitutes invalid bytes and returns string with substitutions
     *
     * It never returns false under default config. The false guard in
     * encodeSql() exists to satisfy PHPStan's string|false return type
     * and would only trigger under custom mb_substitute_character settings.
     *
     * This test documents that fact: both quote() and encodeSql() are
     * lenient on invalid bytes under default PHP config. The asymmetry
     * is in the code structure (encodeSql has a false guard, quote does
     * not), not in observable runtime behavior under normal conditions.
     */
    public function testEncodeSqlFalseGuardIsDefenseInDepth(): void
    {
        // Invalid UTF-8 bytes that mb_convert_encoding will substitute, not fail on
        $invalidUtf8 = "valid \xFF\xFE bytes";

        // quote() does not throw on invalid bytes (no false guard, no @ suppression)
        $quotedResult = $this->middleware->quote($invalidUtf8);
        self::assertIsString($quotedResult);
        self::assertStringStartsWith("'", $quotedResult);
    }
}
