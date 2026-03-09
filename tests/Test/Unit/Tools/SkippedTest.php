<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Tools;
use PHPUnit\Framework\TestCase;
class SkippedTest extends TestCase {
    public function testSkipped(): void {
        $this->markTestSkipped('Verification of exit code 0');
    }
}