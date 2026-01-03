<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;

/**
 * Unit tests for Firebird4Platform class.
 */
#[CoversClass(Firebird4Platform::class)]
class Firebird4PlatformTest extends TestCase
{
    private Firebird4Platform $platform;

    protected function setUp(): void
    {
        $this->platform = new Firebird4Platform();
    }

    public function testGetDateTimeTzFormatString(): void
    {
        // Firebird 4+ returns TIMESTAMP WITH TIME ZONE as "Y-m-d H:i:s <TimezoneIdentifier>"
        self::assertSame('Y-m-d H:i:s e', $this->platform->getDateTimeTzFormatString());
    }

    public function testGetTimeTzFormatString(): void
    {
        // Firebird 4+ returns TIME WITH TIME ZONE as "H:i:s <TimezoneIdentifier>"
        self::assertSame('H:i:s e', $this->platform->getTimeTzFormatString());
    }

    public function testGetDateTimeTzTypeDeclarationSQL(): void
    {
        self::assertSame('TIMESTAMP WITH TIME ZONE', $this->platform->getDateTimeTzTypeDeclarationSQL([]));
    }

    public function testGetTimeTzTypeDeclarationSQL(): void
    {
        self::assertSame('TIME WITH TIME ZONE', $this->platform->getTimeTzTypeDeclarationSQL([]));
    }
}
