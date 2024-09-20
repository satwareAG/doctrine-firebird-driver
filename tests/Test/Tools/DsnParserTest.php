<?php

namespace Satag\DoctrineFirebirdDriver\Test\Tools;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

use function get_class;
use function ksort;

/** @psalm-import-type Params from DriverManager */
final class DsnParserTest extends TestCase
{
    public function testDatabaseUrl(string $dsn = '', array $expected = []): void
    {
        $parser = new DsnParser(['firebird' => Driver::class, 'firebird3' => Driver::class]);
        $actual = $parser->parse('firebird://user:password@192.168.1.10:3050/var/db/mydatabase.fdb?charset=UTF8&role=admin');

        $expected = [
            'host' => '192.168.1.10',
            'port' => 3050,
            'user' => 'user',
            'password' => 'password',
            'driverClass' => 'Satag\\DoctrineFirebirdDriver\\Driver\\Firebird\\Driver',
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


}
