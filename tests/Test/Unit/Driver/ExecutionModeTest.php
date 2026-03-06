<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExecutionMode;

/**
 * Unit tests for ExecutionMode.
 */
class ExecutionModeTest extends TestCase
{
    public function testDefaultIsAutoCommitEnabled(): void
    {
        $mode = new ExecutionMode();
        self::assertTrue($mode->isAutoCommitEnabled());
    }

    public function testDisableAutoCommit(): void
    {
        $mode = new ExecutionMode();
        $mode->disableAutoCommit();
        self::assertFalse($mode->isAutoCommitEnabled());
    }

    public function testEnableAutoCommitRestores(): void
    {
        $mode = new ExecutionMode();
        $mode->disableAutoCommit();
        $mode->enableAutoCommit();
        self::assertTrue($mode->isAutoCommitEnabled());
    }
}
