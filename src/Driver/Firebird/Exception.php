<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Exception as BaseException;
use Throwable;

use function fbird_sqlstate;
use function function_exists;

/**
 * Firebird driver exception.
 *
 * Implements Doctrine\DBAL\Driver\Exception directly instead of extending
 * the internal AbstractException class (which is deprecated for external use).
 *
 * Enhanced in php-firebird v7.0.0+ with native SQLSTATE support via fbird_sqlstate().
 * SQLSTATE codes provide standardized (SQL:2003) error classification for better
 * error handling across different database systems.
 *
 * @psalm-immutable
 */
class Exception extends BaseException implements DriverException
{
    /**
     * The SQLSTATE of the driver.
     *
     * SQLSTATE is a 5-character code defined by SQL:2003 standard.
     * Format: Class (2 chars) + Subclass (3 chars)
     * Examples:
     *   - '23000' = Integrity constraint violation
     *   - '42000' = Syntax error or access violation
     *   - '08006' = Connection failure
     *   - 'HY000' = General error (when no specific code applies)
     */
    private string|null $sqlState = null;

    /**
     * @param string         $message  The driver error message.
     * @param string|null    $sqlState The SQLSTATE the driver is in at the time the error occurred, if any.
     * @param int            $code     The driver specific error code if any.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    public function __construct(string $message, string|null $sqlState = null, int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->sqlState = $sqlState;
    }

    /**
     * Create exception from Firebird error information.
     *
     * Enhanced in php-firebird v7.0.0+ to automatically fetch SQLSTATE code
     * via fbird_sqlstate() for standardized error classification.
     *
     * @param string $message The error message from fbird_errmsg()
     * @param int    $code    The error code from fbird_errcode()
     */
    public static function fromErrorInfo(string $message, int $code): Exception
    {
        $sqlState = self::fetchSqlState();

        return new self($message, $sqlState, $code);
    }

    /**
     * Create exception from Firebird\Exception (Exception Mode API).
     *
     * When Exception Mode is enabled (php-firebird v7.0.0-rc.6+), Firebird API
     * functions throw Firebird\Exception instead of returning false. This factory
     * method converts these native exceptions to Doctrine DriverException.
     *
     * The Firebird\Exception class provides getSqlState() method directly,
     * making error classification more reliable than fetching via fbird_sqlstate().
     *
     * @param \Firebird\Exception $exception The native Firebird exception
     * @phpstan-param \Throwable $exception
     * @phpstan-ignore parameter.notFound
     */
    public static function fromFirebirdException(\Firebird\Exception $exception): Exception
    {
        // Use getSqlState() from Firebird\Exception if available (more reliable)
        /** @phpstan-ignore method.nonObject */
        $sqlState = method_exists($exception, 'getSqlState')
            /** @phpstan-ignore method.nonObject */
            ? $exception->getSqlState()
            : self::fetchSqlState();

        return new self(
            /** @phpstan-ignore method.nonObject */
            $exception->getMessage(),
            $sqlState,
            /** @phpstan-ignore method.nonObject */
            $exception->getCode(),
            $exception,
        );
    }

    /**
     * Get the SQLSTATE error code.
     *
     * Returns a 5-character SQLSTATE code if available, or null if:
     * - No error occurred
     * - php-firebird version < 7.0.0 (fbird_sqlstate not available)
     * - Firebird version doesn't support SQLSTATE for this error
     */
    public function getSQLState(): string|null
    {
        return $this->sqlState;
    }

    /**
     * Fetch the current SQLSTATE from the Firebird extension.
     *
     * Uses fbird_sqlstate() (php-firebird v7.0.0+) to get the 5-character
     * SQLSTATE code for the last error. Returns null if the function is
     * not available or no error occurred.
     *
     * @return string|null The 5-character SQLSTATE code or null
     */
    private static function fetchSqlState(): string|null
    {
        // fbird_sqlstate() is available in php-firebird v7.0.0+
        if (! function_exists('fbird_sqlstate')) {
            return null;
        }

        $state = fbird_sqlstate();

        // fbird_sqlstate() returns false if no error or empty string
        if ($state === false || $state === '') {
            return null;
        }

        return $state;
    }
}
