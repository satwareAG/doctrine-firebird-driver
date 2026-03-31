<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Satag\DoctrineFirebirdDriver\Driver\Firebird\Enum\ExecutionMode;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Enum\TransactionState;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\TransactionManager;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TransactionManagerTest extends TestCase
{
    private Connection $connection;
    private TransactionManager $manager;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(Connection::class);
        $this->connection = $reflection->newInstanceWithoutConstructor();
        $this->manager = new TransactionManager($this->connection);
    }

    public function testDefaultValues(): void
    {
        self::assertSame(0, $this->manager->getLevel());
        self::assertSame(ExecutionMode::AUTO_COMMIT, $this->manager->getExecutionMode());
    }

    public function testSetGetIsolationLevel(): void
    {
        $this->manager->setIsolationLevel(1);
        self::assertSame(1, $this->manager->getIsolationLevel());
    }

    public function testSetGetWaitTimeout(): void
    {
        $this->manager->setWaitTimeout(10);
        self::assertSame(10, $this->manager->getWaitTimeout());
    }

    public function testSetGetExecutionMode(): void
    {
        $this->manager->setExecutionMode(ExecutionMode::MANUAL_COMMIT);
        self::assertSame(ExecutionMode::MANUAL_COMMIT, $this->manager->getExecutionMode());
    }
}
