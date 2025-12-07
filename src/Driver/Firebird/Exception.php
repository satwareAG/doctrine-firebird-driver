<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Exception as BaseException;
use Throwable;

/**
 * Firebird driver exception.
 *
 * Implements Doctrine\DBAL\Driver\Exception directly instead of extending
 * the internal AbstractException class (which is deprecated for external use).
 *
 * @psalm-immutable
 */
class Exception extends BaseException implements DriverException
{
    /**
     * The SQLSTATE of the driver.
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

    public static function fromErrorInfo(string $message, int $code): Exception
    {
        return new self($message, null, $code);
    }

    public function getSQLState(): string|null
    {
        return $this->sqlState;
    }
}
