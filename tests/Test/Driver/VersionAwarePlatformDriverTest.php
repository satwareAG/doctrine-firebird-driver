<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Driver;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

class VersionAwarePlatformDriverTest extends TestCase
{
    use VerifyDeprecations;

    #[DataProvider('firebirdVersionProvider')]
    public function testFirebird(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new Driver(), $version, $expectedClass);
    }

    public function testGetExceptionConverter(): void
    {
        $driver    = new Driver();
        $converter = $driver->getExceptionConverter();

        self::assertInstanceOf(ExceptionConverter::class, $converter);
    }

    /**
     * See: https://firebirdsql.org/en/release-policy/
     *
     * @return array<array{string, class-string<AbstractPlatform>}>
     */
    public static function firebirdVersionProvider(): Iterator
    {
        // Legacy "LI|WI-V..." format (older Firebird installations)
        yield ['WI-V2.1.4.18393', FirebirdPlatform::class];
        yield ['LI-V2.1.4.18393', FirebirdPlatform::class];
        yield ['LI-V2.999.999.99999', FirebirdPlatform::class];
        yield ['LI-T3.0.0.29316', Firebird3Platform::class];
        yield ['LI-V3.1.2.34567', Firebird3Platform::class];
        yield ['LI-V4.1.2.34567', Firebird4Platform::class];
        yield ['LI-V5.1.2.34567', Firebird5Platform::class];
        yield ['LI-V6.1.2.34567', Firebird5Platform::class];
        // Plain numeric format returned by newer Firebird Docker images (firebirdsql/firebird:3, :4, :5)
        yield ['2.5.9.27139', FirebirdPlatform::class];
        yield ['3.0.13.33818', Firebird3Platform::class];
        yield ['4.0.5.3116', Firebird4Platform::class];
        yield ['5.0.3.1683', Firebird5Platform::class];
        yield ['6.0.0.1', Firebird5Platform::class];
    }

    /**
     * Assert that a driver creates the expected platform for a given version.
     *
     * @param FirebirdDriver                 $driver            The driver to test
     * @param string                         $version           The version string to test
     * @param class-string<AbstractPlatform> $expectedClass     The expected platform class
     * @param string|null                    $deprecation       Optional deprecation identifier to expect
     * @param bool|null                      $expectDeprecation Whether to expect the deprecation
     */
    private function assertDriverInstantiatesDatabasePlatform(
        FirebirdDriver $driver,
        string $version,
        string $expectedClass,
        string|null $deprecation = null,
        bool|null $expectDeprecation = null,
    ): void {
        if ($deprecation !== null) {
            if ($expectDeprecation ?? true) {
                $this->expectDeprecationWithIdentifier($deprecation);
            } else {
                $this->expectNoDeprecationWithIdentifier($deprecation);
            }
        }

        $platform = $driver->createDatabasePlatformForVersion($version);

        self::assertSame($expectedClass, $platform::class);
    }
}
