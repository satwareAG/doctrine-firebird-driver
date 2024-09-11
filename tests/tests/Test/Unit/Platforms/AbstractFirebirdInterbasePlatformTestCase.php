<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Kafoso\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

abstract class AbstractFirebirdInterbasePlatformTestCase extends AbstractIntegrationTestCase
{
    const TEST_INCOMPLETE_FOR_DBAL3 = 'Needs Update for DBAL 3';
    protected $_platform;
    protected $_platform3;

    public function setUp(): void
    {
        $this->_platform = new FirebirdInterbasePlatform;
        $this->_platform3 = new Firebird3Platform;
    }
}
