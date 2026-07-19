<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;

/**
 * Unit tests for Firebird4Platform.
 *
 * Firebird 4.0 introduces TIMESTAMP WITH TIME ZONE and TIME WITH TIME ZONE types.
 */
class Firebird4PlatformTest extends Firebird3PlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new Firebird4Platform();
    }

    public function testGetDateTimeTzTypeDeclarationSQL(): void
    {
        $sql = $this->platform->getDateTimeTzTypeDeclarationSQL([]);
        self::assertSame('TIMESTAMP WITH TIME ZONE', $sql);
    }

    public function testGetDateTimeTzTypeDeclarationSQLWithOptions(): void
    {
        // Options should be ignored for this type
        $sql = $this->platform->getDateTimeTzTypeDeclarationSQL(['precision' => 6]);
        self::assertSame('TIMESTAMP WITH TIME ZONE', $sql);
    }

    public function testGetTimeTzTypeDeclarationSQL(): void
    {
        $sql = $this->platform->getTimeTzTypeDeclarationSQL([]);
        self::assertSame('TIME WITH TIME ZONE', $sql);
    }

    public function testGetTimeTzTypeDeclarationSQLWithOptions(): void
    {
        // Options should be ignored for this type
        $sql = $this->platform->getTimeTzTypeDeclarationSQL(['precision' => 3]);
        self::assertSame('TIME WITH TIME ZONE', $sql);
    }

    public function testDoctrineTypeMappingForTimestampWithTimeZone(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('timestamp with time zone'));
        self::assertSame(Types::DATETIMETZ_MUTABLE, $this->platform->getDoctrineTypeMapping('timestamp with time zone'));
    }

    public function testDoctrineTypeMappingForTimeWithTimeZone(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('time with time zone'));
        self::assertSame(Types::TIME_MUTABLE, $this->platform->getDoctrineTypeMapping('time with time zone'));
    }

    public function testInheritsFromFirebird3Platform(): void
    {
        // Verify inheritance - should have all Firebird3 capabilities
        self::assertInstanceOf(AbstractPlatform::class, $this->platform);
        self::assertTrue($this->platform->supportsIdentityColumns());
        self::assertTrue($this->platform->supportsSavepoints());
    }

    public function testGetName(): void
    {
        // Firebird4Platform overrides getName() and returns 'Firebird4'
        // The deprecation warning is expected
        self::assertSame('Firebird4', @$this->platform->getName());
    }

    public function testStandardDoctrineTypeMappingsPreserved(): void
    {
        // Verify parent type mappings are still available
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('integer'));
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('varchar'));
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('timestamp'));
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('boolean'));
    }
}
