<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

/**
 * Tests that FirebirdDriver correctly applies configuration to platform instances.
 *
 * @covers \Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver
 */
final class FirebirdDriverConfigurationTest extends TestCase
{
    private TestableFirebirdDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new TestableFirebirdDriver();
    }

    public function testCreateDatabasePlatformForVersionFirebird25WithDefaultConfiguration(): void
    {
        $platform = $this->driver->createDatabasePlatformForVersion('LI-V2.5.9.27139');

        self::assertInstanceOf(FirebirdPlatform::class, $platform);
        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testCreateDatabasePlatformForVersionFirebird30WithDefaultConfiguration(): void
    {
        $platform = $this->driver->createDatabasePlatformForVersion('LI-V3.0.10.33601');

        self::assertInstanceOf(Firebird3Platform::class, $platform);
        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testCreateDatabasePlatformForVersionFirebird40WithDefaultConfiguration(): void
    {
        $platform = $this->driver->createDatabasePlatformForVersion('LI-V4.0.5.3116');

        self::assertInstanceOf(Firebird4Platform::class, $platform);
        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testCreateDatabasePlatformForVersionFirebird50WithDefaultConfiguration(): void
    {
        $platform = $this->driver->createDatabasePlatformForVersion('LI-V5.0.2.1533');

        self::assertInstanceOf(Firebird5Platform::class, $platform);
        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testCreateDatabasePlatformForVersionWithCustomConfiguration(): void
    {
        $this->driver->setFirebirdOptions(['like_cast_length' => 1000]);

        $platform = $this->driver->createDatabasePlatformForVersion('LI-V4.0.5.3116');

        self::assertInstanceOf(Firebird4Platform::class, $platform);
        self::assertSame(1000, $platform->getLikeCastLength());
    }

    public function testGetDatabasePlatformWithDefaultConfiguration(): void
    {
        $platform = $this->driver->getDatabasePlatform();

        self::assertInstanceOf(FirebirdPlatform::class, $platform);
        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testGetDatabasePlatformWithCustomConfiguration(): void
    {
        $this->driver->setFirebirdOptions(['like_cast_length' => 500]);

        $platform = $this->driver->getDatabasePlatform();

        self::assertInstanceOf(FirebirdPlatform::class, $platform);
        self::assertSame(500, $platform->getLikeCastLength());
    }

    public function testMultipleCallsToCreatePlatformUseSameConfiguration(): void
    {
        $this->driver->setFirebirdOptions(['like_cast_length' => 750]);

        $platform1 = $this->driver->createDatabasePlatformForVersion('LI-V3.0.10.33601');
        $platform2 = $this->driver->createDatabasePlatformForVersion('LI-V4.0.5.3116');

        self::assertSame(750, $platform1->getLikeCastLength());
        self::assertSame(750, $platform2->getLikeCastLength());
    }

    public function testEmptyFirebirdOptionsArrayUsesDefaults(): void
    {
        $this->driver->setFirebirdOptions([]);

        $platform = $this->driver->createDatabasePlatformForVersion('LI-V4.0.5.3116');

        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testConfigurationPersistsAcrossPlatformCreations(): void
    {
        $this->driver->setFirebirdOptions(['like_cast_length' => 1500]);

        $versionedPlatform = $this->driver->createDatabasePlatformForVersion('LI-V5.0.2.1533');
        $defaultPlatform   = $this->driver->getDatabasePlatform();

        self::assertSame(1500, $versionedPlatform->getLikeCastLength());
        self::assertSame(1500, $defaultPlatform->getLikeCastLength());
    }
}

/**
 * Test double for FirebirdDriver that allows setting firebirdOptions directly.
 */
final class TestableFirebirdDriver extends FirebirdDriver
{
    /**
     * Expose firebirdOptions for testing.
     *
     * @param array<string, mixed> $options
     */
    public function setFirebirdOptions(array $options): void
    {
        $this->firebirdOptions = $options;
    }

    public function connect(array $params): never
    {
        throw new \BadMethodCallException('connect() not implemented in test double');
    }
}
