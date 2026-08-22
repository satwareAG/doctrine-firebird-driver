<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use ReflectionProperty;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection as FirebirdDriverConnection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function fbird_close;

/**
 * Regression tests for issue #162.
 *
 * A dead fbird link (real Firebird\Connection object whose underlying link
 * was closed underneath it - e.g. another holder ran fbird_close() during a
 * Symfony kernel reboot) must be transparently re-established on the next
 * prepare() instead of poisoning every subsequent operation with
 * 'Connection is not valid or has been closed.'
 *
 * Guard rails under test:
 *   - recovery happens ONLY outside explicit transactions
 *   - recovery is bounded to a single reconnect attempt
 *   - objects without a reconnect factory keep throwing (no behavior change)
 */
final class DeadLinkRecoveryTest extends FunctionalTestCase
{
    public function testPrepareTransparentlyRecoversFromDeadLink(): void
    {
        $driverConn = $this->getFirebirdConnection();
        self::assertNotNull($driverConn);
        self::assertTrue($driverConn->isConnectionValid());

        $closedHandle = $this->createClosedNativeHandle();

        // Inject the dead-but-real handle: exactly the issue-#162 state.
        $property = new ReflectionProperty(FirebirdDriverConnection::class, 'connection');
        $property->setValue($driverConn, $closedHandle);

        self::assertFalse($driverConn->isConnectionValid(), 'Precondition: handle reports dead');

        // Issue #162 regression: first DB operation after invalidation must
        // transparently re-establish the link instead of throwing
        // 'Connection is not valid or has been closed.'
        $result = $this->connection->executeQuery('SELECT 1 FROM RDB$DATABASE')->fetchOne();

        self::assertSame(1, $result);
        self::assertTrue($driverConn->isConnectionValid(), 'Link must be valid again after recovery');
    }

    public function testRecoverySkippedInsideExplicitTransaction(): void
    {
        $driverConn = $this->getFirebirdConnection();
        self::assertNotNull($driverConn);
        self::assertTrue($driverConn->isConnectionValid());

        // Open an explicit transaction on the HEALTHY link first.
        $this->connection->beginTransaction();

        // Kill the link underneath WHILE the transaction is open: recovery is
        // forbidden here (silent retry under transaction semantics risks data
        // loss), even though the firebird transaction handle died with it.
        $closedHandle = $this->createClosedNativeHandle();
        $property     = new ReflectionProperty(FirebirdDriverConnection::class, 'connection');
        $property->setValue($driverConn, $closedHandle);

        $thrown = null;
        try {
            $this->connection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        } catch (Throwable $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown, 'Execution must fail while explicit transaction is open');

        $chain = '';
        for ($x = $thrown; $x !== null; $x = $x->getPrevious()) {
            $chain .= $x->getMessage() . "\n";
        }

        self::assertStringContainsString('Connection is not valid', $chain);
        self::assertFalse(
            $driverConn->isConnectionValid(),
            'No silent healing allowed inside an explicit transaction',
        );

        // Teardown: rollback cannot succeed on a dead link; mark poisoned so
        // the suite rebuilds the shared connection instead of reusing it.
        try {
            $this->connection->rollBack();
        } catch (Throwable) {
        }

        $this->markConnectionNotReusable();
    }

    /**
     * Produce a REAL Firebird\Connection object whose native link has been
     * closed underneath it - the state observed after kernel-reboot/GC in
     * the original report.
     */
    private function createClosedNativeHandle(): object
    {
        $victim = (new Driver())->connect($this->connection->getParams());
        $native = $victim->getNativeConnection();
        self::assertTrue($victim->isConnectionValid(), 'Victim link starts valid');

        fbird_close($native);
        self::assertFalse($victim->isConnectionValid(), 'Victim link closed underneath');

        return $native;
    }
}
