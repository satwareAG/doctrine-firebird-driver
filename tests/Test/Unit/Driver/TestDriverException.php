<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Exception;

/**
 * Test helper exception that implements DriverException.
 *
 * PHPUnit cannot mock Throwable::getCode() as it's final in PHP.
 * This concrete class provides controllable error codes and SQL states
 * for testing exception conversion logic.
 */
class TestDriverException extends Exception implements DriverException
{
    private ?string $sqlState;

    public function __construct(
        string $message,
        int $code,
        ?string $sqlState = null
    ) {
        parent::__construct($message, $code);
        $this->sqlState = $sqlState;
    }

    public function getSQLState(): ?string
    {
        return $this->sqlState;
    }
}
