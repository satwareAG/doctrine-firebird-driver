<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\TransactionManager;
use stdClass;

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
        $slept    = [];
        $this->setSleeper($tm, static function (int $microseconds) use (&$slept): void {
            $slept[] = $microseconds;
        });

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

        self::assertSame([50000, 100000], $slept, 'Backoff must be exactly 50ms then 100ms between attempts');
    }

    public function testBackoffScheduleIsDeterministicViaInjectedSleeper(): void
    {
        $tm      = $this->createManager();
        $slept   = [];
        $this->setSleeper($tm, static function (int $microseconds) use (&$slept): void {
            $slept[] = $microseconds;
        });
        $attempts = 0;

        try {
            $this->invokeRetry($tm, static function () use (&$attempts): void {
                $attempts++;
                throw DriverException::fromErrorInfo('lock conflict on no wait transaction', -913);
            });
            self::fail('Expected DriverException after exhausting retries');
        } catch (DriverException $e) {
            self::assertSame(3, $attempts, 'Exactly max-retry attempts expected');
        }

        // Deterministic: the observed delay schedule is the assertion, not
        // elapsed wall time (Windows runners undershoot usleep, #275).
        self::assertSame([50000, 100000], $slept, 'Injected sleeper must observe 50ms then 100ms');
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

    /**
     * Inject the retry delay callable (test seam, #275). The default is a
     * real usleep; tests observe the exact backoff schedule instead of
     * measuring wall time.
     */
    private function setSleeper(TransactionManager $manager, callable $sleeper): void
    {
        $property = new ReflectionProperty(TransactionManager::class, 'sleeper');
        $property->setValue($manager, Closure::fromCallable($sleeper));
    }
}
