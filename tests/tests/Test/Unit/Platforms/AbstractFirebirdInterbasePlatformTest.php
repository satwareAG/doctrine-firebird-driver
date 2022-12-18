<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use PHPUnit\Framework\TestCase;

abstract class AbstractFirebirdInterbasePlatformTest extends TestCase
{
    protected $_platform;

    public function setUp($fresh_db = false): void
    {
        $this->_platform = new FirebirdInterbasePlatform;
    }
}
