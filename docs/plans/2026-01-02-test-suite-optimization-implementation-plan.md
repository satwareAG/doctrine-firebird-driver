# Implementation Plan: Test Suite Performance Optimization

[Overview]
Optimize the doctrine-firebird-driver test suite from ~266s to ~200s target.

This plan addresses three remaining optimization opportunities after Step 1 (removing double cleanup from SchemaManagerFunctionalTestCase) was completed in commit `ece8b38`. The optimizations follow Doctrine DBAL's established test patterns: using `setUpBeforeClass()` for expensive one-time setup, transaction-based test isolation, and efficient resource cleanup.

**Current State:**
- Test suite: 1,585 tests, 84.31% coverage
- Duration: ~254s (after Step 1 optimization)
- 15 test classes extend `AbstractIntegrationTestCase` (per-test DB reinstall)
- 5 test classes extend `ReadOnlyIntegrationTestCase` (efficient pattern)

**Target Savings:**
| Optimization | Expected Savings |
|-------------|------------------|
| Step 2: Optimize cleanupSchemaTestTables() | ~12s |
| Step 3: Move installFirebirdDatabase to setUpBeforeClass | ~30s |
| Step 4: Create ModifyingIntegrationTestCase | ~15s |
| **Total** | **~57s (22% reduction)** |

[Types]
No new types required - existing PHPUnit test infrastructure is sufficient.

[Files]
File modifications for test optimization.

**Files to Modify:**

1. `tests/Test/Functional/Schema/SchemaManagerFunctionalTestCase.php`
   - Optimize `cleanupSchemaTestTables()` method to check existence before drop
   - Add caching of existing table names via single metadata query

2. `tests/Test/Integration/AbstractIntegrationTestCase.php`
   - Move `installFirebirdDatabase()` call from `setUp()` to `setUpBeforeClass()`
   - Add `beginTransaction()` in `setUp()` for test isolation
   - Modify `tearDown()` to rollback transaction instead of marking connection non-reusable

3. `tests/Test/Integration/ReadOnlyIntegrationTestCase.php`
   - Minor cleanup: Remove redundant comments, ensure pattern consistency

**Files to Create:**

1. `tests/Test/Integration/ModifyingIntegrationTestCase.php`
   - New base class for tests that MUST modify data (e.g., TransactionTest)
   - Uses per-unique-table pattern (MD5 hash) instead of shared fixtures
   - Does NOT reinstall database per test

[Functions]
Function modifications for optimization.

**Modified Functions:**

1. `SchemaManagerFunctionalTestCase::cleanupSchemaTestTables()` 
   - **File:** `tests/Test/Functional/Schema/SchemaManagerFunctionalTestCase.php`
   - **Change:** Add existence check before attempting drops
   - **Current:** Iterates 60+ tables, attempts drop, catches exceptions
   - **New:** Query `listTableNames()` once, filter to existing tables only, then drop

2. `AbstractIntegrationTestCase::setUp()`
   - **File:** `tests/Test/Integration/AbstractIntegrationTestCase.php`
   - **Change:** Remove `installFirebirdDatabase()` call, add `beginTransaction()`
   - **Signature:** `public function setUp(): void`

3. `AbstractIntegrationTestCase::tearDown()`
   - **File:** `tests/Test/Integration/AbstractIntegrationTestCase.php`
   - **Change:** Call `rollBack()` instead of `markConnectionNotReusable()`
   - **Signature:** `public function tearDown(): void`

**New Functions:**

1. `AbstractIntegrationTestCase::setUpBeforeClass()`
   - **File:** `tests/Test/Integration/AbstractIntegrationTestCase.php`
   - **Purpose:** One-time database installation per test class
   - **Signature:** `public static function setUpBeforeClass(): void`

[Classes]
Class modifications for test base class hierarchy.

**Modified Classes:**

1. `AbstractIntegrationTestCase`
   - **File:** `tests/Test/Integration/AbstractIntegrationTestCase.php`
   - **Changes:**
     - Add `setUpBeforeClass()` with database installation
     - Modify `setUp()` to use transaction isolation
     - Modify `tearDown()` to rollback instead of connection disposal

2. `SchemaManagerFunctionalTestCase`
   - **File:** `tests/Test/Functional/Schema/SchemaManagerFunctionalTestCase.php`
   - **Changes:**
     - Optimize `cleanupSchemaTestTables()` with existence check

**New Classes:**

1. `ModifyingIntegrationTestCase`
   - **File:** `tests/Test/Integration/ModifyingIntegrationTestCase.php`
   - **Purpose:** Base class for tests that create unique tables per test
   - **Inheritance:** `extends AbstractIntegrationTestCase`
   - **Key Methods:**
     - `setUp()`: Standard entity manager setup, no transaction wrapping
     - `tearDown()`: Drop test-created tables using naming pattern
   - **Usage:** `TransactionTest` will extend this instead of `AbstractIntegrationTestCase`

[Dependencies]
No new dependencies required.

All optimizations use existing PHPUnit lifecycle hooks and Doctrine DBAL's SchemaManager.

[Testing]
Testing approach for optimization validation.

**Validation Strategy:**

Each optimization step must pass the full quality check:
```bash
./tests/docker-cqc.sh --coverage
```

**Success Criteria:**
- All 1,585 tests pass (OK or skipped)
- Coverage remains ≥80% (currently 84.31%)
- No new PHPStan Level 8 errors
- No PSR-12 violations

**Timing Measurement:**
Compare timing from PHPUnit output before/after each step:
```
Time: 04:08.018  # Baseline after Step 1
```

**Test Files to Verify (High-Impact):**
1. `tests/Test/Functional/Schema/Firebird3SchemaManagerTest.php` (87 tests, 48.29s)
2. `tests/Test/Integration/Doctrine/DBAL/Database/TransactionTest.php` (11 tests, 22.38s)
3. `tests/Test/Integration/Doctrine/ORM/QueryBuilder/AlbumTest.php` (15 tests, 27.36s)

[Implementation Order]
Sequential implementation to minimize conflicts.

**Step 2: Optimize cleanupSchemaTestTables() (Low Risk)**

1. Read current `cleanupSchemaTestTables()` implementation
2. Add `$existingTables = $this->schemaManager->listTableNames();` at start
3. Filter `$orderedTables` to only include tables that exist
4. Filter views similarly before dropping
5. Run: `./tests/docker-cqc.sh --coverage`
6. Commit: `perf(tests): Optimize cleanupSchemaTestTables with existence check`

**Step 3: Refactor AbstractIntegrationTestCase (Medium Risk)**

1. Add `setUpBeforeClass()` method with database installation logic
2. Modify `setUp()`:
   - Remove `installFirebirdDatabase()` call
   - Add `$this->connection->beginTransaction();` after entity manager setup
3. Modify `tearDown()`:
   - Replace `$this->markConnectionNotReusable();` with transaction rollback
4. Run: `./tests/docker-cqc.sh --coverage`
5. Commit: `perf(tests): Move installFirebirdDatabase to setUpBeforeClass`

**Step 4: Handle ModifyingIntegrationTestCase (Medium Risk)**

1. Create new `ModifyingIntegrationTestCase` class
2. Implement per-test isolation suitable for TransactionTest pattern
3. Migrate `TransactionTest` to extend new base class
4. Verify TransactionTest still works correctly
5. Run: `./tests/docker-cqc.sh --coverage`
6. Commit: `perf(tests): Add ModifyingIntegrationTestCase for transaction tests`

**Post-Implementation:**

1. Verify total timing improvement matches expectations
2. Push optimization branch: `git push origin optimize/test-suite-performance`
3. Create PR for review
