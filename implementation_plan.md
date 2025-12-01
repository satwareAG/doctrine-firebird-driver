# Implementation Plan - PHP Firebird Extension Crash During Functional Tests

[Overview]
Fix the silent crash that occurs in the Functional test suite when `BlobTest::testBindParamProcessesStream` runs after other tests, caused by corrupted transaction state during test teardown operations.

The php-firebird extension 6.2.0 crashes (hard exit) when `fbird_prepare()` or `fbird_execute()` is called with an invalid or null `$sql` parameter, or when the connection/transaction resources are in an inconsistent state. This happens because the test framework's `disconnect()` method attempts cleanup operations on an already-corrupted connection.

**Root Cause Analysis:**
1. Tests with blob operations call `markConnectionNotReusable()` in tearDown
2. During `disconnect()`, the framework tries to rollback transactions on an invalid handle
3. The `rollBack()` method in Connection.php (line 417) warns about invalid transaction handle
4. Subsequent `dropTableIfExists()` calls try to prepare statements with corrupted state
5. `fbird_prepare()` receives null/invalid SQL or connection, causing a hard crash

**Key Evidence from Debug Output:**
- `fbird_rollback(): invalid transaction handle (expecting explicit transaction start)` 
- `fbird_prepare(): Query argument missing or not a string`
- These warnings appear in teardown sequence, before the crash

[Types]
No new type definitions required for this fix.

[Files]
Files to be modified:

1. **src/Driver/Firebird/Connection.php**
   - Line ~220 (`prepare` method): Add null/empty check for `$sql` parameter before calling `fbird_prepare()`
   - Line ~417 (`rollBack` method): Add more defensive check for transaction resource validity
   - Overall: Add validation for connection and transaction resource states before operations

2. **tests/Test/FunctionalTestCase.php**
   - Line ~160-185 (`disconnect` method): Add more defensive cleanup with proper state validation
   - Line ~40-85 (`dropTableIfExists` method): Add connection validity check before operations

3. **src/Driver/Firebird/Statement.php**
   - Line ~321 (`execute` method): Add `@` error suppression to `fbird_execute()` like we did for `fbird_prepare()` to prevent warnings from bubbling up when queries fail on non-existent tables during cleanup

[Functions]
Functions to modify:

1. **Connection::prepare(string $sql)** in `src/Driver/Firebird/Connection.php`
   - Add validation: Check `$sql` is non-empty string before calling `fbird_prepare()`
   - Add validation: Check `$this->connection` and `$this->firebirdActiveTransaction` are valid resources
   - Throw meaningful exception instead of allowing crash

2. **Connection::rollBack()** in `src/Driver/Firebird/Connection.php`
   - Add defensive check: If transaction resource is invalid, reset state without attempting rollback
   - Return true/false gracefully instead of crashing

3. **FunctionalTestCase::disconnect()** in `tests/Test/FunctionalTestCase.php`
   - Add connection validity check before cleanup operations
   - Wrap `dropTableIfExists` calls in more defensive try-catch
   - Check if `$this->connection` is still connected before operations

4. **FunctionalTestCase::dropTableIfExists(string $name)** in `tests/Test/FunctionalTestCase.php`
   - Add early return if connection is closed/invalid
   - Add check for Firebird connection resource validity

5. **Statement::execute()** in `src/Driver/Firebird/Statement.php`
   - Suppress warning from `fbird_execute()` when operation fails (similar to `fbird_prepare()`)

[Classes]
No new classes required. Modifications to existing classes:

1. **Connection** (src/Driver/Firebird/Connection.php)
   - Add `isConnectionValid(): bool` method to check resource validity
   - Add `isTransactionValid(): bool` method to check transaction resource validity

2. **Statement** (src/Driver/Firebird/Statement.php)
   - No structural changes, just defensive code in execute()

3. **FunctionalTestCase** (tests/Test/FunctionalTestCase.php)
   - Add `isConnectionAvailable(): bool` helper method

[Dependencies]
No new dependencies required.

[Testing]
Test strategy for verifying the fix:

1. **Isolated Test**: Run `BlobTest::testBindParamProcessesStream` alone
   ```bash
   ./tests/phpunit.sh --filter "testBindParamProcessesStream" --testsuite Functional --debug
   ```

2. **Sequential Test**: Run entire BlobTest class
   ```bash
   ./tests/phpunit.sh --filter "BlobTest" --testsuite Functional --debug
   ```

3. **Full Functional Suite**:
   ```bash
   ./tests/phpunit.sh --stop-on-error --stop-on-warning --testsuite Functional
   ```

4. **All Test Suites**:
   ```bash
   ./tests/phpunit.sh --stop-on-error --stop-on-warning
   ```

**Success Criteria:**
- No hard crashes (silent failures)
- No warnings with `--stop-on-warning` 
- All assertions pass
- Tests pass both in isolation and in sequence

[Implementation Order]
Implement changes in this order to minimize risk and allow incremental testing:

1. **Step 1: Add defensive validation to Connection::prepare()**
   - Add null/empty check for `$sql` parameter
   - Add resource validity check for connection and transaction
   - Test: Run isolated BlobTest

2. **Step 2: Add error suppression to Statement::execute()**
   - Add `@` to `fbird_execute()` call (consistent with `fbird_prepare()`)
   - Test: Run BlobTest suite

3. **Step 3: Improve Connection::rollBack() defensiveness**
   - Add transaction resource validity check before rollback
   - Handle invalid state gracefully
   - Test: Run BlobTest + BinaryDataAccessTest together

4. **Step 4: Add connection validity helpers**
   - Add `isConnectionValid()` and `isTransactionValid()` methods
   - Test: Unit test the new methods

5. **Step 5: Improve FunctionalTestCase::disconnect() cleanup**
   - Add connection validity check
   - Make cleanup operations more defensive
   - Test: Run full Functional suite

6. **Step 6: Improve FunctionalTestCase::dropTableIfExists()**
   - Add early return for invalid connection
   - Test: Run full Functional suite with `--stop-on-warning`

7. **Step 7: Final verification**
   - Run complete test suite: Integration + Functional
   - Verify no warnings, no crashes
   - Test all Firebird versions (2.5, 3, 4, 5)
