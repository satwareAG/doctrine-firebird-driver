<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Tools;
use PHPUnit\Framework\TestCase;
class FailingTest extends TestCase {
    public function testFailing(): void {
        $this->fail('Intentional failure for verification');
    }
}