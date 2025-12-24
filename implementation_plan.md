# Implementation Plan

[Overview]
Fix 31 test failures and 3 errors in GitHub Actions CI caused by unhandled Firebird\Exception when php-firebird Exception Mode is enabled, and improve local testing scripts with dynamic port allocation.

The CI failures show `Firebird\Exception` being thrown directly instead of being wrapped in DBAL-compatible exceptions. When `fbird_set_exception_mode(FBIRD_EXCEPTION_MODE_THROW)` is enabled in Connection.php constructor, native Firebird functions throw `Firebird\Exception` directly BEFORE code can check return values via `checkLastApiCall()`. This bypasses the DBAL exception conversion pipeline entirely.

Additionally, the local test scripts (`docker-cqc.sh`, `act-local-test.sh`) use hardcoded port `3050:3050` bindings that can cause conflicts when Firebird is already running locally.

**Root Cause Analysis (CI Failures):**
1. Connection.php enables `FBIRD_EXCEPTION_MODE_THROW` at line ~130-131
2. Subsequent `fbird_*` calls (prepare, execute, commit, rollback, trans_start) can throw `Firebird\Exception`
3. The `@` operator (warning suppression) does NOT suppress exceptions thrown by the extension
4. `Firebird\Exception` bubbles up without conversion to DBAL exceptions
5. Tests expect `Doctrine\DBAL\Exception\*` but receive `Firebird\Exception`

**Evidence from CI logs:**
- `Expected: 'Doctrine\DBAL\Exception\SyntaxErrorException'`
- `Actual: 'Firebird\Exception'`

[Types]
No new types required. The fix involves wrapping existing `Firebird\Exception` in the driver's own `Exception` class which already implements `Doctrine\DBAL\Driver\Exception`.

[Files]
Modify 4 existing PHP files and 2 shell scripts.

**PHP Files to Modify:**

1. **`src/Driver/Firebird/Connection.php`** (~15 changes)
   - Add `use` statement for `Firebird\Exception as FirebirdNativeException`
   - Wrap all `fbird_*` function calls in try-catch blocks to catch `Firebird\Exception`
   - Convert caught `Firebird\Exception` to `Driver\Firebird\Exception` with proper error code/message

2. **`src/Driver/Firebird/Statement.php`** (~3 changes)
   - Add `use` statement for `Firebird\Exception as FirebirdNativeException`
   - Wrap `fbird_execute()` and related calls in try-catch blocks
   - Convert caught exceptions to DBAL-compatible exceptions

3. **`src/Driver/Firebird/Result.php`** (~5 changes - need to verify)
   - May need similar try-catch wrapping for `fbird_fetch_*` calls
   - Review fetch operations for exception handling

4. **`src/Driver/Firebird/Exception.php`** (~1 change)
   - Add static factory method `fromFirebirdException(Firebird\Exception $e)` for clean conversion

**Shell Scripts to Modify:**

5. **`tests/docker-compose.yml`**
   - Remove hardcoded port mapping `- "3050:3050"` from firebird3 service
   - Use Docker internal networking (containers communicate by hostname)
   - Add optional dynamic port mapping via environment variable

6. **`tests/act-local-test.sh`**
   - Replace hardcoded `-p 3050:3050` with dynamic port allocation
   - Use `FIREBIRD_PORT` environment variable with fallback to automatic port
   - Update health check to use dynamic port

[Functions]
Modify exception handling in critical functions across Connection.php and Statement.php.

**New Functions:**

1. **`Exception::fromFirebirdException(Firebird\Exception $e): self`** (in `src/Driver/Firebird/Exception.php`)
   - Static factory method to convert native Firebird exception to DBAL-compatible
   - Extracts SQLSTATE, error code, and message from native exception
   - Location: `src/Driver/Firebird/Exception.php`

**Modified Functions (Connection.php):**

2. **`prepare(string $sql): DriverStatement`**
   - Wrap `fbird_prepare()` call in try-catch for `Firebird\Exception`
   - Current: Uses `@` suppression + return value check
   - New: Also catch and convert `Firebird\Exception`

3. **`commit(): bool`**
   - Wrap `fbird_commit()` call in try-catch
   - Current: Checks return value + manual error info
   - New: Also catch `Firebird\Exception`

4. **`rollBack(): bool`**
   - Wrap `fbird_rollback()` call in try-catch
   - Convert native exception to DBAL exception

5. **`createTransaction(): resource`** (private)
   - Wrap `fbird_trans_start()` in try-catch
   - Most critical - called frequently

6. **`autoCommit(): void`**
   - Wrap `fbird_commit_ret()` in try-catch

7. **`createSavepoint(string $savepoint): void`**
   - Wrap `fbird_savepoint()` in try-catch

8. **`releaseSavepoint(string $savepoint): void`**
   - Wrap `fbird_release_savepoint()` in try-catch

9. **`rollbackSavepoint(string $savepoint): void`**
   - Wrap `fbird_rollback_savepoint()` in try-catch

**Modified Functions (Statement.php):**

10. **`execute($params = null): ResultInterface`**
    - Wrap `fbird_execute()` at line ~235 in try-catch
    - Most important: This is the main execution path
    - Also wrap `fbird_fetch_assoc()` for RETURNING clause handling

[Classes]
Enhance `Exception` class with conversion capability.

**Modified Class:**

1. **`Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception`**
   - File: `src/Driver/Firebird/Exception.php`
   - Add `fromFirebirdException()` static factory method
   - Preserve SQLSTATE if available from native exception (`getSqlState()`)
   - Preserve SQLCODE as error code (`getCode()`)
   - Pattern: Similar to existing `fromErrorInfo()` method

[Dependencies]
No new dependencies required.

The fix uses conditional class existence checking (`class_exists(\Firebird\Exception::class, false)`) to ensure backward compatibility with systems where the Firebird extension doesn't have the Exception class (older php-interbase or pre-v7 php-firebird).

[Testing]
Verify all 31 failures and 3 errors are resolved by the exception wrapping.

**Test Verification Strategy:**

1. **Local Testing:**
   ```bash
   # Run docker-cqc.sh with coverage to verify all tests pass
   ./tests/docker-cqc.sh --coverage
   ```

2. **Specific Failed Tests to Verify:**
   - `StatementTest::testExecuteThrowsExceptionWhenSQLIsInvalid`
   - `ConnectionTest::testTransactionNestingBehavior`
   - `ConnectionTest::testTransactionNestingBehaviorWithSavepoints`
   - `ConnectionTest::testExceptionOnExecuteStatement`
   - `ConnectionTest::testExceptionOnExecuteQuery`
   - All exception-related tests in `Test/Functional/` directory

3. **Exception Conversion Tests:**
   - Syntax errors → `SyntaxErrorException`
   - Unique constraint violations → `UniqueConstraintViolationException`
   - Foreign key violations → `ForeignKeyConstraintViolationException`
   - Connection errors → `ConnectionException`

4. **Backward Compatibility:**
   - Tests should pass both with and without Exception Mode enabled
   - Verify on multiple PHP versions (8.1, 8.2, 8.3, 8.4)

[Implementation Order]
Sequential implementation to minimize risk and enable incremental testing.

1. **Step 1: Add exception conversion factory to Exception.php**
   - Add `fromFirebirdException()` static method
   - Test: Verify method exists and converts properly (unit test possible)

2. **Step 2: Wrap Connection.php high-traffic methods**
   - Start with `prepare()` and `createTransaction()`
   - These are called most frequently and cover most test cases
   - Test: Run `./tests/docker-cqc.sh --quick` for syntax validation

3. **Step 3: Wrap Connection.php transaction methods**
   - `commit()`, `rollBack()`, `autoCommit()`
   - `createSavepoint()`, `releaseSavepoint()`, `rollbackSavepoint()`
   - Test: Run transaction-related functional tests

4. **Step 4: Wrap Statement.php execute method**
   - The `execute()` method with `fbird_execute()` call
   - Also wrap `fbird_fetch_assoc()` for RETURNING handling
   - Test: Run statement execution tests

5. **Step 5: Review and wrap Result.php if needed**
   - Check for any `fbird_fetch_*` calls that might throw
   - Test: Run result fetching tests

6. **Step 6: Update docker-compose.yml for dynamic ports**
   - Remove hardcoded port, use environment variable
   - Test: `docker compose up -d` should work without port conflicts

7. **Step 7: Update act-local-test.sh for dynamic ports**
   - Implement dynamic port allocation
   - Test: `./tests/act-local-test.sh test 5` should work even if port 3050 is in use

8. **Step 8: Full CI validation**
   - Run complete test suite: `./tests/docker-cqc.sh`
   - Verify all 31 failures and 3 errors are resolved
   - Push to branch and verify GitHub Actions passes
