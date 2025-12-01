# Implementation Plan: Fix Firebird Driver Test Failures

[Overview]
Fix 10 WriteTest failures (rowCount returning 0), 1 risky PortabilityTest (timeout test with no assertions), and 9 PHP warnings (table locking during FK constraint tests).

The test failures fall into three distinct categories that should be addressed in a specific order:

1. **WriteTest Failures (10 tests)**: All tests fail with `assertSame(1, 0)` - the `rowCount()` method returns 0 instead of the expected affected row count. The root cause is that `fbird_execute()` returns an integer for DML operations, but calling `autoCommit()` with `fbird_commit_ret()` afterward may reset or invalidate this count before the Result object can return it.

2. **PHP Warnings (9 warnings)**: "unsuccessful metadata update object TABLE is in use" errors occur during FK constraint exception tests. These happen because tables cannot be dropped during cleanup while Firebird still holds metadata locks from failed constraint operations.

3. **Risky Test (1 test)**: `PortabilityTest::testTimeout` sleeps for 11 seconds and has no meaningful assertion - it should be properly implemented or marked as incomplete.

[Types]
No new types are required for this fix.

The existing types remain unchanged:
- `Result::$firebirdResultResource` - already accepts `mixed` (int for affected rows, resource for result sets)
- `Statement::$currentResult` - Result|null

[Files]
Modifications required in 3 source files and 1 test file.

**Files to modify:**

1. `src/Driver/Firebird/Statement.php`
   - Store affected row count before calling autoCommit() to prevent loss after commit_ret
   - Pass stored count to Result constructor instead of potentially invalidated fbird_execute return

2. `src/Driver/Firebird/Result.php`
   - Ensure rowCount() properly returns stored affected row count for DML operations
   - No structural changes needed if Statement properly stores count

3. `src/Driver/Firebird/Connection.php`
   - No changes required for the core fix
   - The autoCommit() method is working correctly

4. `tests/Test/Functional/PortabilityTest.php`
   - Fix testTimeout() to either perform meaningful assertions or mark as incomplete
   - Current implementation just sleeps and returns true - invalid test

5. `tests/Test/FunctionalTestCase.php` (potential)
   - May need to improve table cleanup to handle FK constraint locked tables
   - Add retry logic or use fbird_drop_table_force() for cleanup

[Functions]
Modifications required to Statement::execute() and potential cleanup in test base class.

**Functions to modify:**

1. `Statement::execute()` in `src/Driver/Firebird/Statement.php`
   - Current: Passes `$fbirdResultRc` directly to Result constructor after autoCommit()
   - Issue: If `fbird_commit_ret()` invalidates the affected row count, we lose it
   - Fix: Store integer result from `fbird_execute()` before calling `autoCommit()`, then pass to Result
   - Lines: ~200-220

2. `Result::rowCount()` in `src/Driver/Firebird/Result.php`
   - Current: Checks `is_numeric($this->firebirdResultResource)` then casts to int
   - This should work IF the integer is preserved correctly from Statement
   - May need debugging to verify the value being passed
   - Lines: 112-122

3. `PortabilityTest::testTimeout()` in `tests/Test/Functional/PortabilityTest.php`
   - Current: `sleep(11); self::assertTrue(true);`
   - Fix: Mark as incomplete with explanation, or implement actual timeout testing
   - Lines: 104-108

4. `FunctionalTestCase::dropAndCreateTable()` in `tests/Test/FunctionalTestCase.php` (if exists)
   - May need to add retry or force-drop logic for tables with FK constraints
   - Handle "object TABLE is in use" errors during cleanup

[Classes]
No new classes required. Minor modifications to existing classes.

**Classes to modify:**

1. `Statement` class (`src/Driver/Firebird/Statement.php`)
   - Purpose: Fix affected row count preservation during execute()
   - Change: Store DML result count before autoCommit() call

2. `PortabilityTest` class (`tests/Test/Functional/PortabilityTest.php`)
   - Purpose: Fix risky test
   - Change: Proper implementation or mark incomplete

[Dependencies]
No new dependencies required.

All functionality uses existing Firebird extension functions:
- `fbird_execute()` - returns int for DML, resource for SELECT
- `fbird_commit_ret()` - commit retaining transaction
- `fbird_affected_rows()` - get affected rows from connection

[Testing]
Run specific test suite to verify fixes.

**Test command:**
```bash
tests/phpunit.sh --testsuite=Functional
```

**Expected outcomes after fix:**
1. WriteTest: All 10 tests should pass (rowCount returns 1)
2. PortabilityTest::testTimeout: Should not be risky (either passes or is skipped)
3. ExceptionTest: Should have no PHP warnings about "table in use"

**Verification steps:**
1. Run WriteTest alone: `tests/phpunit.sh --filter WriteTest`
2. Run PortabilityTest alone: `tests/phpunit.sh --filter PortabilityTest`
3. Run ExceptionTest alone: `tests/phpunit.sh --filter ExceptionTest`
4. Run full Functional suite to verify no regressions

[Implementation Order]
Execute fixes in logical order: core functionality first, then test cleanup.

**Phase 1: Fix Core rowCount Issue (WriteTest failures)**
1. Add debug logging to Statement::execute() to trace fbird_execute return value
2. Verify whether integer is being passed correctly to Result constructor
3. If autoCommit() affects the value, store it in a local variable first
4. Modify Statement::execute() to preserve affected row count before autoCommit()
5. Run WriteTest to verify 10 failures are fixed

**Phase 2: Fix Risky Test (PortabilityTest::testTimeout)**
6. Analyze intent of testTimeout() - what was it supposed to test?
7. Either implement proper timeout testing or mark test as incomplete/skipped
8. Run PortabilityTest to verify risky test is resolved

**Phase 3: Fix Table Locking Warnings (ExceptionTest warnings)**
9. Investigate FunctionalTestCase cleanup mechanisms
10. Add retry or force-drop logic for FK-constrained tables
11. Consider using rollback instead of commit before cleanup
12. Run ExceptionTest to verify warnings are eliminated

**Phase 4: Final Verification**
13. Run full Functional test suite
14. Verify: Tests: 489, Failures: 0, Warnings: 0, Skipped: 108, Risky: 0
