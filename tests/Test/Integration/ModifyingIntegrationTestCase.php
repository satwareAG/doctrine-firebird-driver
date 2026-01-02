<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration;

use PHPUnit\Framework\Attributes\Large;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

/**
 * ModifyingIntegrationTestCase for tests that need direct transaction control.
 *
 * Unlike AbstractIntegrationTestCase (which uses transaction isolation pattern),
 * this class allows tests to control their own transactions (beginTransaction,
 * commit, rollback) without an outer transaction wrapper.
 *
 * Use cases:
 * - Testing transaction commit/rollback behavior
 * - Tests that create/drop temporary tables per test method
 * - Tests that need DDL operations (CREATE TABLE, DROP TABLE)
 *
 * OPTIMIZATION: Still uses setUpBeforeClass for base schema installation,
 * but each test method may create additional tables as needed.
 */
#[Large]
abstract class ModifyingIntegrationTestCase extends AbstractIntegrationTestCase
{
    /**
     * Override setUp to NOT begin a transaction.
     * Tests extending this class control their own transactions.
     */
    public function setUp(): void
    {
        // Initialize EntityManager (without beginning transaction)
        $this->setUpEntityManager();
        // DO NOT call beginTransaction - tests control their own transactions
    }

    /**
     * Override tearDown to mark connection not reusable.
     * Since tests may create/modify tables, connection cannot be reused.
     */
    public function tearDown(): void
    {
        // Ensure any active transaction is rolled back before closing
        $fbirdConnection = $this->getFirebirdConnection();
        if ($fbirdConnection !== null && $fbirdConnection->isConnectionValid()) {
            try {
                @$fbirdConnection->rollBack();
            } catch (\Throwable) {
                // Ignore rollback errors
            }
        }

        // Mark connection not reusable - tests may have modified schema
        $this->markConnectionNotReusable();
    }
}
