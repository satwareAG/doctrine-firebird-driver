<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration;

use PHPUnit\Framework\Attributes\Medium;

/**
 * ReadOnlyIntegrationTestCase is now a simple alias for AbstractIntegrationTestCase.
 *
 * AbstractIntegrationTestCase was optimized to use the same pattern:
 * - setUpBeforeClass() installs DB once per class
 * - setUp() begins transaction
 * - tearDown() rolls back transaction
 *
 * This class is kept for backward compatibility with existing tests.
 *
 * @deprecated Use AbstractIntegrationTestCase directly - it now has the same optimization.
 */
#[Medium]
abstract class ReadOnlyIntegrationTestCase extends AbstractIntegrationTestCase
{
    // No overrides needed - parent class now uses transaction isolation pattern
}
