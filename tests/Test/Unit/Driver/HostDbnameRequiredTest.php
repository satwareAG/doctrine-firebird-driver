<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\HostDbnameRequired;

/**
 * Unit tests for HostDbnameRequired exception class.
 */
#[CoversClass(HostDbnameRequired::class)]
class HostDbnameRequiredTest extends TestCase
{
    public function testNew(): void
    {
        $exception = HostDbnameRequired::new();

        self::assertInstanceOf(HostDbnameRequired::class, $exception);
        self::assertStringContainsString('host', $exception->getMessage());
        self::assertStringContainsString('dbname', $exception->getMessage());
    }

    public function testInvalidPort(): void
    {
        $exception = HostDbnameRequired::invalidPort();

        self::assertInstanceOf(HostDbnameRequired::class, $exception);
        self::assertStringContainsString('port', $exception->getMessage());
        self::assertStringContainsString('valid Port number', $exception->getMessage());
    }
}
