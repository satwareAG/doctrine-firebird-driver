<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Kafoso\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTest;

abstract class AbstractFirebirdInterbasePlatformTest extends AbstractIntegrationTest
{
    protected $_platform;
    protected $_platform3;

    public function setUp(): void
    {
        $this->_platform = new FirebirdInterbasePlatform;
        $this->_platform3 = new Firebird3Platform;
    }
}
