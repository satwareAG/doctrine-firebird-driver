<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Exception as BaseException;
use Throwable;

use function fbird_sqlstate;
use function function_exists;
use function method_exists;
use Override;

/**
 * Firebird driver exception.
 *
 * Implements Doctrine\DBAL\Driver\Exception directly instead of extending
 * the internal AbstractException class (which is deprecated for external use).
 *
 * Enhanced in php-firebird v7.0.0+ with native SQLSTATE support via fbird_sqlstate().
 * SQLSTATE codes provide SQL:2003 standard error classification:
 *
 * Class 08: Connection Exception
 * Class 21: Cardinality Violation
 * Class 22: Data Exception
 * Class 23: Integrity Constraint Violation
 *   - 23502: NOT NULL violation
 *   - 23503: Foreign key violation
 *   - 23505: Unique constraint violation
 * Class 28: Invalid Authorization
 * Class 40: Transaction Rollback
 * Class 42: Syntax Error or Access Violation
 *
 * @link https://en.wikipedia.org/wiki/SQLSTATE
 * @link https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref50/firebird-50-language-reference.html#fblangref50-appx02-sqlstates
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
    private string|null $sqlState;

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
     * Create exception from any Throwable (Exception Mode compatible).
     *
     * When Exception Mode is enabled (php-firebird v7.0.0-rc.6+), Firebird API
     * functions throw Firebird\Exception instead of returning false. This factory
     * method converts these native exceptions to Doctrine DriverException.
     *
     * This method accepts \Throwable to satisfy PHPStan when the extension is
     * not loaded (CI environment). At runtime, it checks if the exception is
     * Firebird\Exception and extracts SQLSTATE if available.
     *
     * @param Throwable $exception The exception to convert
     */
    public static function fromThrowable(Throwable $exception): Exception
    {
        // If already a DriverException, wrap it
        if ($exception instanceof Exception) {
            return $exception;
        }

        // Check if it's a Firebird\Exception and extract SQLSTATE
        $sqlState = null;
        if (method_exists($exception, 'getSqlState')) {
            $sqlState = $exception->getSqlState();
        }

        if ($sqlState === null) {
            $sqlState = self::fetchSqlState();
        }

        return new self(
            $exception->getMessage(),
            $sqlState,
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
    #[Override]
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
