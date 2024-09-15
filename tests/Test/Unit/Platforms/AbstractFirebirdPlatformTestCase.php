<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

abstract class AbstractFirebirdPlatformTestCase extends AbstractIntegrationTestCase
{
    const TEST_INCOMPLETE_FOR_DBAL3 = 'Needs Update for DBAL 3';
    protected $_platform;
    protected $_platform3;

    public function setUp(): void
    {
        $this->_platform = $this->connection->getDatabasePlatform();
    }
}
