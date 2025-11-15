<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Tests configurable LIKE CAST length functionality.
 *
 * Verifies that the firebird.like_cast_length configuration parameter
 * allows users to customize the VARCHAR length used in CAST operations
 * for LIKE expressions.
 */
final class ConfigurableLikeCastLengthTest extends FunctionalTestCase
{
    /**
     * Test default CAST length (255) when no configuration provided.
     */
    public function testDefaultCastLength(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        
        self::assertSame(255, $platform->getLikeCastLength());
    }

    /**
     * Test custom CAST length (100) via configuration.
     */
    public function testCustomCastLength100(): void
    {
        $params = $this->connection->getParams();
        $params['firebird']['like_cast_length'] = 100;
        
        $connection = DriverManager::getConnection($params);
        $platform   = $connection->getDatabasePlatform();
        
        self::assertSame(100, $platform->getLikeCastLength());
        
        $this->markConnectionNotReusable();
        $connection->close();
    }

    /**
     * Test custom CAST length (500) via configuration.
     */
    public function testCustomCastLength500(): void
    {
        $params = $this->connection->getParams();
        $params['firebird']['like_cast_length'] = 500;
        
        $connection = DriverManager::getConnection($params);
        $platform   = $connection->getDatabasePlatform();
        
        self::assertSame(500, $platform->getLikeCastLength());
        
        $this->markConnectionNotReusable();
        $connection->close();
    }

    /**
     * Test custom CAST length (1000) via configuration.
     */
    public function testCustomCastLength1000(): void
    {
        $params = $this->connection->getParams();
        $params['firebird']['like_cast_length'] = 1000;
        
        $connection = DriverManager::getConnection($params);
        $platform   = $connection->getDatabasePlatform();
        
        self::assertSame(1000, $platform->getLikeCastLength());
        
        $this->markConnectionNotReusable();
        $connection->close();
    }

    /**
     * Test maximum valid CAST length (8191 - Firebird VARCHAR limit).
     */
    public function testMaximumCastLength(): void
    {
        $params = $this->connection->getParams();
        $params['firebird']['like_cast_length'] = 8191;
        
        $connection = DriverManager::getConnection($params);
        $platform   = $connection->getDatabasePlatform();
        
        self::assertSame(8191, $platform->getLikeCastLength());
        
        $this->markConnectionNotReusable();
        $connection->close();
    }

    /**
     * Test that configured length is actually used in SQL generation.
     */
    public function testConfiguredLengthUsedInSQL(): void
    {
        $params = $this->connection->getParams();
        $params['firebird']['like_cast_length'] = 500;
        
        $connection = DriverManager::getConnection($params);
        
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from('test_table')
            ->where('column1 LIKE :search');
        
        $sql = $queryBuilder->getSQL();
        
        // Verify CAST with configured length (500) instead of default (255)
        self::assertStringContainsString('CAST(column1 AS VARCHAR(500))', $sql);
        
        $this->markConnectionNotReusable();
        $connection->close();
    }
}
