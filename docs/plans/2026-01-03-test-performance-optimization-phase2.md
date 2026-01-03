# Implementation Plan: Test Performance Optimization Phase 2

[Overview]
Comprehensive test suite optimization including CQC verification, duplicate test removal, and slowest test profiling.

This plan executes all remaining optimizations for the doctrine-firebird-driver test suite, following the successful completion of Phase 1 (Steps 1-4 from the original optimization plan). The goal is to ensure all tests pass with current optimizations, remove remaining duplicate tests, and identify/optimize the slowest-running tests before merging to 3.0.x.

**Current State (2026-01-03):**
- Branch: `optimize/test-suite-performance`
- Tests: ~1,579 (after 5 Statement duplicates removed)
- Coverage target: ≥80%
- PHPStan: ✅ Level 8 passing
- PHPCS: ✅ Clean

**Phase 2 Objectives:**
1. Run full Docker CQC to verify all tests pass
2. Remove 2 remaining duplicate Transaction tests
3. Profile and optimize slowest tests (Schema tests ~48s)
4. Final verification and merge preparation

[Types]
No new types required - using existing PHPUnit infrastructure.

[Files]
File modifications for Phase 2 optimization.

**Files to Verify (CQC):**
- All 127 test files in `tests/Test/`
- Static analysis: `src/` directory (27 files)

**Files to Modify (Deduplication):**
1. `tests/Test/Functional/Driver/Firebird/TransactionTest.php`
   - Remove `testBeginTransactionCommit` (covered by Integration tests)
   
2. `tests/Test/Functional/Connection/TransactionNestingTest.php`
   - Remove `testNestedStructureSuccess` (covered by Integration multi-transaction tests)

**Files to Analyze (Profiling):**
1. `tests/Test/Functional/Schema/Firebird3SchemaManagerTest.php` (~48s, 87 tests)
2. `tests/Test/Integration/Doctrine/DBAL/Database/TransactionTest.php` (~22s, 11 tests)
3. `tests/Test/Integration/Doctrine/ORM/QueryBuilder/AlbumTest.php` (~27s, 15 tests)

[Functions]
Function modifications for test deduplication.

**Functions to Remove:**

1. `TransactionTest::testBeginTransactionCommit()`
   - **File:** `tests/Test/Functional/Driver/Firebird/TransactionTest.php`
   - **Reason:** Covered by Integration `testCanSuccessfullyCommitASingleTransactionForInsert/Update`
   
2. `TransactionNestingTest::testNestedStructureSuccess()`
   - **File:** `tests/Test/Functional/Connection/TransactionNestingTest.php`
   - **Reason:** Covered by Integration multi-transaction tests

**Functions to Profile:**

1. `SchemaManagerFunctionalTestCase::setUp()` 
   - **File:** `tests/Test/Functional/Schema/SchemaManagerFunctionalTestCase.php`
   - **Current:** Calls `cleanupSchemaTestTables()` which may be slow
   
2. `SchemaManagerFunctionalTestCase::cleanupSchemaTestTables()`
   - **Optimization:** Already optimized with existence check - verify performance

[Classes]
No new classes required - modifications to existing test classes only.

**Classes to Modify:**

1. `TransactionTest` - Remove 1 duplicate test method
2. `TransactionNestingTest` - Remove 1 duplicate test method

[Dependencies]
No new dependencies required.

**Required Docker Infrastructure:**
- Firebird 2.5, 3.0, 4.0, 5.0 containers
- PHP 8.3 with ext-firebird (php-firebird v7.0.0-rc.37)
- PCOV for coverage

[Testing]
Validation approach for Phase 2.

**Step 1: Full CQC Verification**
```bash
cd tests && ./docker-cqc.sh --coverage
```

Success criteria:
- All tests pass (OK or skipped)
- Coverage ≥80%
- PHPStan Level 8: No errors
- PHPCS: No violations

**Step 2: Post-Deduplication Verification**
```bash
# After removing 2 Transaction tests
vendor/bin/phpunit -c tests/phpunit.xml --filter "TransactionTest|TransactionNestingTest"
cd tests && ./docker-cqc.sh --coverage
```

**Step 3: Profiling**
```bash
# Generate timing report
vendor/bin/phpunit -c tests/phpunit.xml --log-junit tests/var/reports/timing.xml 2>&1 | grep -E "Time:|OK|ERRORS"
```

[Implementation Order]
Sequential implementation with checkpoints.

**Step 1: Full Docker CQC Run (Priority: CRITICAL)**
1. Start Docker containers for Firebird 2.5, 3.0, 4.0, 5.0
2. Execute: `cd tests && ./docker-cqc.sh`
3. Capture timing baseline and test count
4. Document any failures or warnings
5. If failures: STOP and fix before proceeding

**Step 2: Remove Duplicate Transaction Tests (Priority: HIGH)**
1. Read `tests/Test/Functional/Driver/Firebird/TransactionTest.php`
2. Remove `testBeginTransactionCommit()` method
3. Read `tests/Test/Functional/Connection/TransactionNestingTest.php`
4. Remove `testNestedStructureSuccess()` method
5. Run: `vendor/bin/phpunit -c tests/phpunit.xml --filter "Transaction"`
6. Verify coverage still ≥80%
7. Commit: `refactor(tests): remove 2 duplicate Transaction tests`

**Step 3: Profile Slowest Tests (Priority: MEDIUM)**
1. Run PHPUnit with timing output
2. Identify tests taking >5s individually
3. Document findings in timing report
4. Identify optimization opportunities:
   - setUp/tearDown overhead
   - Database fixture creation
   - Table existence checks
   - Connection establishment

**Step 4: Apply Targeted Optimizations (Priority: MEDIUM)**
Based on profiling, potential optimizations:
1. Schema test fixture caching
2. Reduce metadata queries in cleanupSchemaTestTables
3. Batch table drops instead of individual
4. Transaction isolation for read-only tests

**Step 5: Final Verification (Priority: HIGH)**
1. Run full CQC: `cd tests && ./docker-cqc.sh --coverage`
2. Compare timing to baseline from Step 1
3. Document improvements
4. Prepare merge summary

**Step 6: Merge Preparation (Priority: HIGH)**
1. Squash/rebase if needed
2. Update CHANGELOG.md with final metrics
3. Push final state to origin
4. Create PR or prepare direct merge to 3.0.x
