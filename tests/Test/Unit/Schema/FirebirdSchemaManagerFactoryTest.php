<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Schema;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManagerFactory;

/**
 * Unit tests for FirebirdSchemaManagerFactory.
 * Covers lines 36, 38.
 */
final class FirebirdSchemaManagerFactoryTest extends TestCase
{
    public function testCreateSchemaManagerReturnsFirebirdSchemaManager(): void
    {
        $platform   = $this->createMock(FirebirdPlatform::class);
        $connection = $this->createMock(Connection::class);

        $connection
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $factory = new FirebirdSchemaManagerFactory();
        $manager = $factory->createSchemaManager($connection);

        self::assertInstanceOf(FirebirdSchemaManager::class, $manager);
    }
}
