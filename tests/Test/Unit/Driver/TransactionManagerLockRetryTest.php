<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\TransactionManager;
use Throwable;
use stdClass;

use function microtime;
use function usleep;

/**
 * Unit tests for the bounded lock-conflict retry on implicit auto-commit
 * transaction restarts (#163).
 *
 * The retry helper wraps the raw createTransaction() call, so it is exercised
 * through a scripted operation closure bound over the private method - no
 * database connection required.
 */
#[CoversClass(TransactionManager::class)]
final class TransactionManagerLockRetryTest extends TestCase
{
    public function testRetriesLockConflictAndSucceeds(): void
    {
        $tm         = $this->createManager();
        $attempts   = 0;
        $successful = new stdClass();

        $result = $this->invokeRetry($tm, static function () use (&$attempts, $successful): stdClass {
            $attempts++;

            if ($attempts < 3) {
                throw DriverException::fromErrorInfo('lock conflict on no wait transaction', -913);
            }

            return $successful;
        });

        self::assertSame(3, $attempts, 'Must retry until success within budget');
        self::assertSame($successful, $result);
    }

    public function testGivesUpAfterMaxRetriesAndSurfacesLockConflict(): void
    {
        $tm       = $this->createManager();
        $attempts = 0;
        $start    = microtime(true);

        try {
            $this->invokeRetry($tm, static function () use (&$attempts): void {
                $attempts++;
                throw DriverException::fromErrorInfo('lock conflict on no wait transaction', -913);
            });
            self::fail('Expected DriverException after exhausting retries');
        } catch (DriverException $e) {
            self::assertSame(3, $attempts, 'Exactly max-retry attempts expected');
            self::assertStringContainsString('lock conflict', $e->getMessage());
        }

        // Backoff floor: 50ms + 100ms sleeps between 3 attempts.
        self::assertGreaterThanOrEqual(0.15, microtime(true) - $start);
    }

    public function testDoesNotRetryNonLockConflictErrors(): void
    {
        $tm       = $this->createManager();
        $attempts = 0;

        try {
            $this->invokeRetry($tm, static function () use (&$attempts): void {
                $attempts++;
                throw DriverException::fromErrorInfo('unsuccessful metadata update', -607);
            });
            self::fail('Expected DriverException');
        } catch (DriverException $e) {
            self::assertSame(1, $attempts, 'Non-lock-conflict errors must surface immediately');
            self::assertStringContainsString('metadata update', $e->getMessage());
        }
    }

    /**
     * Invoke the private retry helper with a scripted operation.
     */
    private function invokeRetry(TransactionManager $manager, callable $operation): mixed
    {
        $bound = Closure::bind(
            static fn (TransactionManager $tm, callable $op): mixed => $tm->withAutoCommitRestartRetry($op),
            null,
            TransactionManager::class,
        );

        return $bound($manager, $operation);
    }

    private function createManager(): TransactionManager
    {
        $reflectionClass = new ReflectionClass(TransactionManager::class);

        return $reflectionClass->newInstanceWithoutConstructor();
    }
}
