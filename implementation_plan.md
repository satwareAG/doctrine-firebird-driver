# Implementation Plan

[Overview]
Optimize the PHPUnit test suite by refactoring integration tests to use shared database fixtures, implementing test classification attributes, and splitting test suites for parallel execution potential.

The current test suite takes ~258 seconds to run, with 72% of the time spent in 10 test classes. The primary bottleneck is `AbstractIntegrationTestCase`, which rebuilds the entire database schema (8 tables) and seeds data for *every single test method*. This results in over 70 full database rebuilds for tests that are largely read-only (SELECT queries). By moving this initialization to `setUpBeforeClass` for read-only tests and wrapping them in transactions, we can reduce the integration test runtime by ~68%. Additionally, we will modernize the suite with PHPUnit 10 attributes and better suite organization.

[Types]
No new PHP types or interfaces are required.

[Files]
Refactor existing test base classes and configuration files.

Detailed breakdown:
- **New File**: `tests/Test/Integration/ReadOnlyIntegrationTestCase.php`
  - Purpose: Base class for integration tests that only read data or can run inside a transaction rollback.
  - Extends: `AbstractIntegrationTestCase`
- **Modified File**: `tests/Test/Integration/AbstractIntegrationTestCase.php`
  - Changes: Refactor `installFirebirdDatabase` to be static or accessible from static context. Add support for shared connection management.
- **Modified File**: `tests/Test/Integration/Doctrine/ORM/QueryBuilder/AlbumTest.php`
  - Changes: Extend `ReadOnlyIntegrationTestCase`.
- **Modified File**: `tests/Test/Integration/Doctrine/ORM/EntityManager/Repository/FindTest.php`
  - Changes: Extend `ReadOnlyIntegrationTestCase`.
- **Modified File**: `tests/Test/Integration/Doctrine/ORM/EntityManager/Repository/FindAllTest.php`
  - Changes: Extend `ReadOnlyIntegrationTestCase`.
- **Modified File**: `tests/Test/Integration/Doctrine/ORM/EntityManager/Repository/FindByTest.php`
  - Changes: Extend `ReadOnlyIntegrationTestCase`.
- **Modified File**: `tests/Test/Integration/Doctrine/ORM/EntityManager/Repository/FindOneByTest.php`
  - Changes: Extend `ReadOnlyIntegrationTestCase`.
- **Modified File**: `tests/phpunit.xml`
  - Changes: Add test suite definitions for `Integration-ReadOnly`, `Integration-Write`, `Schema`. Add extensions configuration if needed.

[Functions]
Refactor setup and teardown logic.

Detailed breakdown:
- **New Function**: `ReadOnlyIntegrationTestCase::setUpBeforeClass()`
  - Purpose: Initialize the database once for the class.
- **New Function**: `ReadOnlyIntegrationTestCase::tearDownAfterClass()`
  - Purpose: Clean up the database after all tests in the class.
- **Modified Function**: `AbstractIntegrationTestCase::installFirebirdDatabase()`
  - Change: Make static or compatible with static context.
- **Modified Function**: `AbstractIntegrationTestCase::setUp()`
  - Change: Check if database is already initialized.

[Classes]
Refactor test inheritance hierarchy.

Detailed breakdown:
- **New Class**: `Satag\DoctrineFirebirdDriver\Test\Integration\ReadOnlyIntegrationTestCase`
  - Extends: `AbstractIntegrationTestCase`
  - Key Methods: `setUpBeforeClass`, `tearDownAfterClass`, `setUp`, `tearDown`
- **Modified Class**: `Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase`
  - Modifications: Add static properties for shared connection and initialization state.

[Dependencies]
No new package dependencies.

[Testing]
Verify performance improvements and test isolation.

Test file requirements:
- Run `tests/docker-cqc.sh` to ensure all tests still pass.
- Compare execution time before and after changes.
- Verify that `AlbumTest` runs significantly faster (target < 5s vs current 25s).

[Implementation Order]
Step-by-step execution plan.

1.  Refactor `AbstractIntegrationTestCase` to support static database initialization.
2.  Create `ReadOnlyIntegrationTestCase` implementing `setUpBeforeClass` logic.
3.  Update `AlbumTest` to extend `ReadOnlyIntegrationTestCase` and verify performance/correctness.
4.  Update other read-only repository tests (`FindTest`, `FindAllTest`, etc.) to extend `ReadOnlyIntegrationTestCase`.
5.  Update `phpunit.xml` to define granular test suites.
6.  Add PHPUnit attributes (`#[Small]`, `#[Medium]`, `#[Large]`) to key test classes.
