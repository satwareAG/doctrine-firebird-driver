<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Driver;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

use function get_class;

class VersionAwarePlatformDriverTest extends TestCase
{
    use VerifyDeprecations;

    /** @dataProvider FirebirdVersionProvider */
    public function testFirebird(string $version, string $expectedClass): void
    {
        $this->assertDriverInstantiatesDatabasePlatform(new \Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver(), $version, $expectedClass);
    }


    /**
     * See: https://firebirdsql.org/en/release-policy/
     * @return array<array{string, class-string<AbstractPlatform>}>
     *
     */
    public static function FirebirdVersionProvider(): array
    {
        return [
            ['WI-V2.1.4.18393', FirebirdPlatform::class],
            ['LI-V2.1.4.18393', FirebirdPlatform::class],
            ['LI-V2.999.999.99999', FirebirdPlatform::class],
            ['LI-T3.0.0.29316', Firebird3Platform::class],
            ['LI-V3.1.2.34567', Firebird3Platform::class],
            ['LI-V4.1.2.34567', Firebird4Platform::class],
            ['LI-V5.1.2.34567', Firebird5Platform::class],
            ['LI-V6.1.2.34567', Firebird5Platform::class],
        ];
    }

    private function assertDriverInstantiatesDatabasePlatform(
        VersionAwarePlatformDriver $driver,
        string $version,
        string $expectedClass,
        ?string $deprecation = null,
        ?bool $expectDeprecation = null
    ): void {
        if ($deprecation !== null) {
            if ($expectDeprecation ?? true) {
                $this->expectDeprecationWithIdentifier($deprecation);
            } else {
                $this->expectNoDeprecationWithIdentifier($deprecation);
            }
        }

        $platform = $driver->createDatabasePlatformForVersion($version);

        self::assertSame($expectedClass, get_class($platform));
    }
}
