<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

abstract class AbstractFirebirdPlatformTestCase extends AbstractIntegrationTestCase
{
    protected $_platform;

    public function setUp(): void
    {
        $this->_platform = $this->connection->getDatabasePlatform();
    }
}
