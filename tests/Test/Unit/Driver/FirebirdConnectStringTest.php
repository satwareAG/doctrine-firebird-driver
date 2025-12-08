<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\FirebirdConnectString;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\HostDbnameRequired;

/**
 * Unit tests for FirebirdConnectString.
 *
 * This class generates Firebird connection strings from DBAL parameters.
 */
#[CoversClass(FirebirdConnectString::class)]
final class FirebirdConnectStringTest extends TestCase
{
    // ===== Direct Connect String Tests =====

    public function testFromConnectionParametersWithConnectString(): void
    {
        $params = ['connectstring' => 'localhost:/var/lib/firebird/data/test.fdb'];
        
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        self::assertSame('localhost:/var/lib/firebird/data/test.fdb', (string) $connectString);
    }

    public function testFromConnectionParametersWithConnectStringTakesPrecedence(): void
    {
        // Even if host and dbname are provided, connectstring takes precedence
        $params = [
            'connectstring' => 'custom:connection/string',
            'host' => 'ignored',
            'dbname' => '/ignored/path.fdb',
        ];
        
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        self::assertSame('custom:connection/string', (string) $connectString);
    }

    // ===== Host and Database Name Tests =====

    public function testFromConnectionParametersWithHostAndDbname(): void
    {
        $params = [
            'host' => 'localhost',
            'dbname' => '/var/lib/firebird/data/test.fdb',
        ];
        
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        self::assertSame('localhost:/var/lib/firebird/data/test.fdb', (string) $connectString);
    }

    public function testFromConnectionParametersWithHostDbnameAndPort(): void
    {
        $params = [
            'host' => 'firebird.example.com',
            'port' => '3050',
            'dbname' => '/data/mydb.fdb',
        ];
        
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        self::assertSame('firebird.example.com/3050:/data/mydb.fdb', (string) $connectString);
    }

    public function testFromConnectionParametersWithNumericPort(): void
    {
        $params = [
            'host' => 'localhost',
            'port' => 3051,
            'dbname' => '/db/test.fdb',
        ];
        
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        self::assertSame('localhost/3051:/db/test.fdb', (string) $connectString);
    }

    // ===== Exception Tests =====

    public function testFromConnectionParametersThrowsWhenNoHostOrDbname(): void
    {
        $params = [];
        
        $this->expectException(HostDbnameRequired::class);
        
        FirebirdConnectString::fromConnectionParameters($params);
    }

    public function testFromConnectionParametersThrowsWhenOnlyHost(): void
    {
        $params = ['host' => 'localhost'];
        
        $this->expectException(HostDbnameRequired::class);
        
        FirebirdConnectString::fromConnectionParameters($params);
    }

    public function testFromConnectionParametersThrowsWhenOnlyDbname(): void
    {
        $params = ['dbname' => '/path/to/db.fdb'];
        
        $this->expectException(HostDbnameRequired::class);
        
        FirebirdConnectString::fromConnectionParameters($params);
    }

    public function testFromConnectionParametersThrowsWhenHostEmpty(): void
    {
        $params = [
            'host' => '',
            'dbname' => '/path/to/db.fdb',
        ];
        
        $this->expectException(HostDbnameRequired::class);
        
        FirebirdConnectString::fromConnectionParameters($params);
    }

    public function testFromConnectionParametersThrowsWhenDbnameEmpty(): void
    {
        $params = [
            'host' => 'localhost',
            'dbname' => '',
        ];
        
        $this->expectException(HostDbnameRequired::class);
        
        FirebirdConnectString::fromConnectionParameters($params);
    }

    public function testFromConnectionParametersThrowsWhenPortEmpty(): void
    {
        $params = [
            'host' => 'localhost',
            'port' => '',
            'dbname' => '/path/to/db.fdb',
        ];
        
        $this->expectException(HostDbnameRequired::class);
        
        FirebirdConnectString::fromConnectionParameters($params);
    }

    public function testFromConnectionParametersThrowsWhenPortNotNumeric(): void
    {
        $params = [
            'host' => 'localhost',
            'port' => 'abc',
            'dbname' => '/path/to/db.fdb',
        ];
        
        $this->expectException(HostDbnameRequired::class);
        
        FirebirdConnectString::fromConnectionParameters($params);
    }

    // ===== Data Provider Tests =====

    #[DataProvider('validConnectionParamsProvider')]
    public function testValidConnectionParams(array $params, string $expected): void
    {
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        self::assertSame($expected, (string) $connectString);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function validConnectionParamsProvider(): iterable
    {
        yield 'localhost with absolute path' => [
            ['host' => 'localhost', 'dbname' => '/var/lib/firebird/data/test.fdb'],
            'localhost:/var/lib/firebird/data/test.fdb',
        ];

        yield 'remote host' => [
            ['host' => '192.168.1.100', 'dbname' => '/data/production.fdb'],
            '192.168.1.100:/data/production.fdb',
        ];

        yield 'hostname with domain' => [
            ['host' => 'db.example.com', 'dbname' => '/firebird/data/app.fdb'],
            'db.example.com:/firebird/data/app.fdb',
        ];

        yield 'with custom port' => [
            ['host' => 'server', 'port' => '3055', 'dbname' => '/db/test.fdb'],
            'server/3055:/db/test.fdb',
        ];

        yield 'Windows-style path' => [
            ['host' => 'localhost', 'dbname' => 'C:\\Firebird\\data\\test.fdb'],
            'localhost:C:\\Firebird\\data\\test.fdb',
        ];

        yield 'direct connectstring' => [
            ['connectstring' => 'xnet://localhost/C:/data/test.fdb'],
            'xnet://localhost/C:/data/test.fdb',
        ];

        yield 'embedded database' => [
            ['connectstring' => 'embedded:/path/to/database.fdb'],
            'embedded:/path/to/database.fdb',
        ];
    }

    // ===== toString Tests =====

    public function testToStringReturnsConnectionString(): void
    {
        $params = ['host' => 'localhost', 'dbname' => '/test.fdb'];
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        // Test explicit toString()
        self::assertSame('localhost:/test.fdb', $connectString->__toString());
        
        // Test string cast
        self::assertSame('localhost:/test.fdb', (string) $connectString);
    }

    public function testStringableInterface(): void
    {
        $params = ['connectstring' => 'test:connection'];
        $connectString = FirebirdConnectString::fromConnectionParameters($params);
        
        // Verify it implements Stringable
        self::assertInstanceOf(\Stringable::class, $connectString);
    }
}
