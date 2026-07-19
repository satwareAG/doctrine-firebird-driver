<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

abstract class AbstractFirebirdPlatformTestCase extends TestCase
{
    protected $_platform;
    protected $connection;

    public function setUp(): void
    {
        $this->_platform = new Firebird3Platform();

        $sm = $this->createMock(AbstractSchemaManager::class);
        $sm->method('createComparator')->willReturn(new Comparator($this->_platform));

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn($this->_platform);
        $this->connection->method('createSchemaManager')->willReturn($sm);
    }
}
