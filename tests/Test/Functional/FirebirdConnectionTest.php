<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\DBAL\FirebirdConnection;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

/**
 * Tests for FirebirdConnection wrapper class.
 *
 * This test verifies that the FirebirdConnection wrapper correctly passes
 * configuration options from connection params to the platform, which is the
 * recommended DBAL 4.x-compatible approach.
 */
class FirebirdConnectionTest extends TestCase
{
    public function testFirebirdConnectionConfiguresPlatformWithCustomLikeCastLength(): void
    {
        $params                 = TestUtil::getConnectionParams();
        $params['wrapperClass'] = FirebirdConnection::class;
        $params['firebird']     = ['like_cast_length' => 5000];

        $connection = DriverManager::getConnection($params);

        $platform = $connection->getDatabasePlatform();

        self::assertInstanceOf(FirebirdPlatform::class, $platform);
        self::assertSame(5000, $platform->getLikeCastLength());
    }

    public function testFirebirdConnectionWithDefaultConfiguration(): void
    {
        $params                 = TestUtil::getConnectionParams();
        $params['wrapperClass'] = FirebirdConnection::class;
        // No 'firebird' options - should use default

        $connection = DriverManager::getConnection($params);

        $platform = $connection->getDatabasePlatform();

        self::assertInstanceOf(FirebirdPlatform::class, $platform);
        // Default is 255 (defined in FirebirdPlatformConfiguration)
        self::assertSame(255, $platform->getLikeCastLength());
    }

    public function testFirebirdConnectionIsInstanceOfDbalConnection(): void
    {
        $params                 = TestUtil::getConnectionParams();
        $params['wrapperClass'] = FirebirdConnection::class;

        $connection = DriverManager::getConnection($params);

        self::assertInstanceOf(FirebirdConnection::class, $connection);
        self::assertInstanceOf(Connection::class, $connection);
    }

    public function testGetDatabasePlatformCalledMultipleTimesReturnsSamePlatform(): void
    {
        $params                 = TestUtil::getConnectionParams();
        $params['wrapperClass'] = FirebirdConnection::class;
        $params['firebird']     = ['like_cast_length' => 3000];

        $connection = DriverManager::getConnection($params);

        $platform1 = $connection->getDatabasePlatform();
        $platform2 = $connection->getDatabasePlatform();

        self::assertSame($platform1, $platform2);
        self::assertSame(3000, $platform1->getLikeCastLength());
    }
}
