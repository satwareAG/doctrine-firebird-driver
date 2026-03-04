<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Tools;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

use function ksort;

/** @psalm-import-type OverrideParams from DriverManager */
final class DsnParserTest extends TestCase
{
    public function testDatabaseUrl(): void
    {
        $parser = new DsnParser(['firebird' => Driver::class, 'firebird3' => Driver::class]);
        $actual = $parser->parse('firebird://user:password@192.168.1.10:3050/var/db/mydatabase.fdb?charset=UTF8&role=admin');

        $expected = [
            'host' => '192.168.1.10',
            'port' => 3050,
            'user' => 'user',
            'password' => 'password',
            'driverClass' => Driver::class,
            'dbname' => 'var/db/mydatabase.fdb',
            'charset' => 'UTF8',
            'role' => 'admin',
        ];
        // We don't care about the order of the array keys, so let's normalize both
        // arrays before comparing them.
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * TCP/IP DSN without explicit port — DBAL omits the port key when not specified.
     * Closes #79
     */
    public function testDatabaseUrlWithoutPort(): void
    {
        $parser = new DsnParser(['firebird' => Driver::class]);
        $actual = $parser->parse('firebird://SYSDBA:masterkey@localhost/var/db/myapp.fdb');

        $expected = [
            'host'        => 'localhost',
            'user'        => 'SYSDBA',
            'password'    => 'masterkey',
            'driverClass' => Driver::class,
            'dbname'      => 'var/db/myapp.fdb',
        ];

        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * TCP/IP DSN with explicit port 3050.
     * Closes #79
     */
    public function testDatabaseUrlWithExplicitPort(): void
    {
        $parser = new DsnParser(['firebird' => Driver::class]);
        $actual = $parser->parse('firebird://SYSDBA:masterkey@localhost:3050/var/db/myapp.fdb');

        $expected = [
            'host'        => 'localhost',
            'port'        => 3050,
            'user'        => 'SYSDBA',
            'password'    => 'masterkey',
            'driverClass' => Driver::class,
            'dbname'      => 'var/db/myapp.fdb',
        ];

        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * DSN with charset query parameter only.
     * Closes #79
     */
    public function testDatabaseUrlWithCharsetOnly(): void
    {
        $parser = new DsnParser(['firebird' => Driver::class]);
        $actual = $parser->parse('firebird://SYSDBA:masterkey@localhost:3050/var/db/myapp.fdb?charset=WIN1252');

        $expected = [
            'host'        => 'localhost',
            'port'        => 3050,
            'user'        => 'SYSDBA',
            'password'    => 'masterkey',
            'driverClass' => Driver::class,
            'dbname'      => 'var/db/myapp.fdb',
            'charset'     => 'WIN1252',
        ];

        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * firebird3 alias resolves to the same Driver class.
     * Closes #79
     */
    public function testFirebird3AliasResolvesToSameDriver(): void
    {
        $parser  = new DsnParser(['firebird' => Driver::class, 'firebird3' => Driver::class]);
        $actual3 = $parser->parse('firebird3://SYSDBA:masterkey@localhost:3050/var/db/myapp.fdb');

        self::assertSame(Driver::class, $actual3['driverClass']);
        self::assertSame('localhost', $actual3['host']);
        self::assertSame(3050, $actual3['port']);
    }
}
