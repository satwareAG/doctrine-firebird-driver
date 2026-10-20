<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\Deprecations\Deprecation;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * Verifies platform instantiation is free of DBAL identifier-folding deprecations.
 *
 * DBAL 4.x (dbal#6823) deprecates both constructing AbstractPlatform without an
 * UnquotedIdentifierFolding argument and lazy-initializing it via the getter.
 * Driver issue #200 threads the setting through FirebirdPlatformConfiguration.
 */
final class FirebirdPlatformDeprecationTest extends TestCase
{
    public function testFirebirdPlatformInstantiationEmitsNoIdentifierFoldingDeprecation(): void
    {
        $platform     = null;
        $deprecations = $this->collectDeprecationsDuring(
            static function () use (&$platform): void {
                $platform = new FirebirdPlatform();
            },
        );

        self::assertInstanceOf(FirebirdPlatform::class, $platform);
        self::assertSame([], $deprecations);

        $getterDeprecations = $this->collectDeprecationsDuring(
            static function () use ($platform): void {
                $platform->getUnquotedIdentifierFolding();
            },
        );

        self::assertSame(UnquotedIdentifierFolding::UPPER, $platform->getUnquotedIdentifierFolding());
        self::assertSame([], $getterDeprecations);
    }

    public function testVersionedPlatformInstantiationEmitsNoIdentifierFoldingDeprecation(): void
    {
        $deprecations = $this->collectDeprecationsDuring(static function (): void {
            new Firebird3Platform();
            new Firebird4Platform();
            new Firebird5Platform();
        });

        self::assertSame([], $deprecations);
    }

    public function testConfiguredFoldingIsForwardedToParent(): void
    {
        $platform     = null;
        $deprecations = $this->collectDeprecationsDuring(
            static function () use (&$platform): void {
                $platform = new FirebirdPlatform(new FirebirdPlatformConfiguration([
                    'unquoted_identifier_folding' => UnquotedIdentifierFolding::NONE,
                ]));
            },
        );

        self::assertInstanceOf(FirebirdPlatform::class, $platform);
        self::assertSame([], $deprecations);
        self::assertSame(UnquotedIdentifierFolding::NONE, $platform->getUnquotedIdentifierFolding());
    }

    /**
     * Runs $operation with doctrine/deprecations trigger mode and an E_USER_DEPRECATED collector installed.
     *
     * DBAL raises dbal#6823 deprecations via Deprecation::trigger(), which is a
     * no-op unless trigger mode is enabled; enableWithTriggerError() + disable()
     * restore the default no-op state for the rest of the process.
     *
     * @param callable(): void $operation
     *
     * @return list<string> Deprecation messages emitted during $operation
     */
    private function collectDeprecationsDuring(callable $operation): array
    {
        $collected = [];
        Deprecation::enableWithTriggerError();
        set_error_handler(static function (int $errno, string $errstr) use (&$collected): bool {
            if ($errno === E_USER_DEPRECATED) {
                $collected[] = $errstr;
            }

            return true;
        });

        try {
            $operation();
        } finally {
            restore_error_handler();
            Deprecation::disable();
        }

        return $collected;
    }
}
