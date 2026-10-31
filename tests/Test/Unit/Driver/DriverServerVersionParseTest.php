<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

/**
 * Unit tests for the R4 server-version extraction (issue #257).
 *
 * fbird_server_version() returns different shapes across php-firebird
 * releases (raw isc_info_firebird_version buffer, post-#696 clean string
 * like "LI-V3.0.14.33856 Firebird 3.0", and clump-garbled intermediate
 * output). The extraction must find the first version-like token in any
 * of them and reject input without one (caller keeps the '3.0' default).
 */
#[CoversClass(Driver::class)]
final class DriverServerVersionParseTest extends TestCase
{
    public static function versionStringsProvider(): iterable
    {
        yield 'clean string php-firebird >= v13.3.1 (#696)' => [
            'LI-V3.0.14.33856 Firebird 3.0',
            '3.0.14.33856',
        ];

        yield 'clean string Windows / FB4' => [
            'WI-V4.0.5.3116 Firebird 4.0',
            '4.0.5.3116',
        ];

        yield 'clean string / FB5' => [
            'LI-V5.0.4.1683 Firebird 5.0',
            '5.0.4.1683',
        ];

        yield 'classic build without spaces (LI-T)' => [
            'LI-T3.0.0.29316',
            '3.0.0.29316',
        ];

        yield 'plain numeric docker format' => [
            '5.0.3.1683',
            '5.0.3.1683',
        ];

        yield 'two-part version' => [
            'Firebird 3.0',
            '3.0',
        ];

        yield 'raw buffer with binary prefix bytes (pre-#696)' => [
            "\x02\x1BLI-V3.0.14.33856 Firebird 3.0",
            '3.0.14.33856',
        ];

        yield 'clump garble with length prefix bytes' => [
            "\x01\x18LI-V4.0.7.3271 Firebird 4.0\x02\x15WI-V6.0.0.1 localhost",
            '4.0.7.3271',
        ];

        yield 'three-part version' => [
            'WI-V4.0.5',
            '4.0.5',
        ];
    }

    #[DataProvider('versionStringsProvider')]
    public function testExtractsFirstVersionToken(string $rawVersion, string $expected): void
    {
        self::assertSame($expected, $this->extract($rawVersion));
    }

    public static function nonParseableProvider(): iterable
    {
        yield 'extension failure (false)' => [false];
        yield 'empty string' => [''];
        yield 'no version token' => ['no version information available'];
        yield 'digits without dot structure' => ['garbage 12345 bytes'];
    }

    #[DataProvider('nonParseableProvider')]
    public function testReturnsNullWithoutVersionToken(string|false $rawVersion): void
    {
        self::assertNull($this->extract($rawVersion));
    }

    private function extract(string|false $rawVersion): ?string
    {
        $method = new ReflectionMethod(Driver::class, 'extractServerVersionToken');

        return $method->invoke(null, $rawVersion);
    }
}
