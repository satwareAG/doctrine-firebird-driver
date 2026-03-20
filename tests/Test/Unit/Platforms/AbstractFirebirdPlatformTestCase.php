<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

abstract class AbstractFirebirdPlatformTestCase extends TestCase
{
    protected $_platform;

    public function setUp(): void
    {
        $this->_platform = new Firebird3Platform();
    }
}
