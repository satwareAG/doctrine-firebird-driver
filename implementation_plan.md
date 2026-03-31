# Implementation Plan

[Overview]
Fix three DBAL-layer test failures in doctrine-firebird-driver caused by connection handle lifecycle issues and OO wrapper incompatibilities with php-firebird v10.3.9.

The php-firebird C extension v10.3.8/v10.3.9 fixes work correctly at the procedural API level (verified via `tests/debug/verify-v10.3.9-fixes.php`). The remaining failures are in our Doctrine DBAL driver layer:

1. **Integration-ReadOnly 9 errors**: `fbird_trans_start()` throws "Connection has no OO API handle" because the native resource's internal `fbc_connection` C struct pointer has been nullified by a prior `fbird_close()` call from `Connection::__destruct()`.

2. **BatchTest 3 errors**: The OO wrapper `Batch::fromQuery()` uses a different code path than the procedural `fbird_batch_create()` and fails with "invalid batch handle".

3. **Full-suite SIGSEGV at ~65%**: PHPUnit crashes during integration test setup - likely accumulated resource corruption from Bug 1.

**Root Cause Analysis (Bug 1)**: When DBAL's `Connection::close()` is called (or when a DBAL Connection object is garbage-collected), it drops the reference to the driver-level `Connection`. Our `Connection::__destruct()` then calls `fbird_close($this->connection)`, which sets the C-level `fbc_connection = NULL` (in `fbird_connection.c:176`). Any subsequent `fbird_trans_start()` call on this resource triggers the error because it dereferences the now-NULL pointer. The fix is to prevent `fbird_close()` from being called on shared resources and add a static connection registry for reference tracking.

**Architecture**: The DBAL middleware chain is:
- `ConnectionWrapper` (extends `Doctrine\DBAL\Connection`) - DBAL level
- `Portability\Connection` (extends `AbstractConnectionMiddleware`) - from `Middleware(0, ColumnCase::UPPER)`
- `Connection` (our driver, implements `ServerInfoAwareConnection`) - holds `fbird_connect()` resource
- `TransactionManager` - manages `fbird_trans_start()` / `fbird_commit()` / `fbird_rollback()`

The test lifecycle uses shared connections:
- `TestUtil::$sharedConnection` - cached DBAL Connection
- `FunctionalTestCase::$sharedConnection` - per-test-class cached reference
- `AbstractIntegrationTestCase::$integrationConnection` - per-integration-suite cached reference

[Types]
No new types are introduced. Existing enum `ExecutionMode` and exception classes remain unchanged.

The only type-level change is adding a static `array<int, int>` property to `Connection` for the reference counting registry, keyed by resource ID with reference count as value.

[Files]
Six files will be modified and two new diagnostic/test files created.

**Files to modify:**

1. `src/Driver/Firebird/Connection.php` - Core fix: add static connection registry for reference counting to prevent premature `fbird_close()` in `__destruct()`. Also replace `Batch::fromQuery()` with procedural `fbird_batch_create()` call.

2. `src/Driver/Firebird/TransactionManager.php` - Add defensive reconnection in `createTransaction()` when native resource has been invalidated (detect "Unknown" resource type and throw descriptive exception instead of passing invalid resource to `fbird_trans_start()`).

3. `tests/Test/FunctionalTestCase.php` - Remove aggressive `close()` call in `connect()` validation path; replace with graceful reconnection that doesn't destroy shared resources.

4. `tests/Test/Integration/AbstractIntegrationTestCase.php` - Add defensive connection validation in `setUp()` before `beginTransaction()` with automatic reconnection fallback.

5. `tests/Test/Functional/BatchTest.php` - Remove `skipIfKnownBatchHandleIssue()` workaround after batch fix is verified.

6. `NEXT_STEPS.md` - Update test baselines and mark Phase 2.5 items as completed.

**New files:**

7. `tests/debug/trace-handle-lifecycle.php` - Diagnostic script that reproduces the exact connection lifecycle of integration tests, logging every `fbird_close()` and `fbird_trans_start()` call with resource IDs.

8. `tests/debug/compare-batch-paths.php` - Diagnostic script that compares `Batch::fromQuery()` vs `fbird_batch_create()` on the same prepared statement.

[Functions]
Core function modifications to fix all three bugs.

**New functions in `src/Driver/Firebird/Connection.php`:**

1. `Connection::registerResource(resource $resource): void` (private static)
   - Registers a native fbird resource in the static registry
   - Increments reference count for the resource ID
   - Called in `__construct()` after successful connection

2. `Connection::unregisterResource(resource $resource): bool` (private static)
   - Decrements reference count for the resource ID
   - Returns `true` if count reaches 0 (safe to close), `false` otherwise
   - Called in `__destruct()` before deciding whether to call `fbird_close()`

3. `Connection::getResourceRefCount(resource $resource): int` (public static)
   - Returns current reference count for a resource (for diagnostics/testing)
   - Used by diagnostic scripts to verify registry behavior

**Modified functions in `src/Driver/Firebird/Connection.php`:**

4. `Connection::__construct()` - Add `self::registerResource($this->connection)` call after storing the connection resource.

5. `Connection::__destruct()` - Replace direct `fbird_close()` call with `if (self::unregisterResource($this->connection))` guard. Only close when reference count reaches 0.

6. `Connection::createBatch(string $sql, TransactionManager|null $transaction): Batch` - Replace `Batch::fromQuery($query)` with `fbird_batch_create($query)` procedural call, wrapped in a thin `Batch` adapter if the return type requires it. If `Batch::fromQuery()` is the required return type, create a fallback path that tries OO first, then procedural.

**Modified functions in `src/Driver/Firebird/TransactionManager.php`:**

7. `TransactionManager::createTransaction()` - Add pre-check: after `$conn = $this->connection->getNativeConnection()`, verify `get_resource_type($conn)` is not `"Unknown"` before passing to `fbird_trans_start()`. Throw descriptive `DriverException` if resource is invalidated, rather than letting the C extension crash.

**Modified functions in `tests/Test/FunctionalTestCase.php`:**

8. `FunctionalTestCase::connect()` - When detecting an invalid Firebird connection, DO NOT call `self::$sharedConnection->close()`. Instead, set `self::$sharedConnection = null` and `TestUtil::resetSharedConnection()` (new method) to properly clear the cache without triggering `fbird_close()` on the shared resource.

**New function in `tests/Test/TestUtil.php`:**

9. `TestUtil::resetSharedConnection(): void` (public static) - Nulls out `self::$sharedConnection` without calling `close()`, preventing cascading `__destruct` calls that invalidate shared resources.

**Modified functions in `tests/Test/Integration/AbstractIntegrationTestCase.php`:**

10. `AbstractIntegrationTestCase::setUp()` - Add try/catch around `$this->connection->beginTransaction()`. On "OO API handle" or "Unknown" resource error, attempt to reset and recreate the driver-level transaction before failing.

[Classes]
No new classes are introduced. All changes are method-level modifications to existing classes.

**Modified classes:**

1. `Connection` (`src/Driver/Firebird/Connection.php`)
   - Add `private static array $resourceRegistry = []` property
   - Add 3 new static methods for reference counting (`registerResource`, `unregisterResource`, `getResourceRefCount`)
   - Modify `__construct()` to register resources
   - Modify `__destruct()` to check reference count before closing
   - Modify `createBatch()` to use procedural API fallback

2. `TransactionManager` (`src/Driver/Firebird/TransactionManager.php`)
   - Modify `createTransaction()` to validate resource type before `fbird_trans_start()`

3. `FunctionalTestCase` (`tests/Test/FunctionalTestCase.php`)
   - Modify `connect()` to avoid destructive `close()` on shared connections

4. `AbstractIntegrationTestCase` (`tests/Test/Integration/AbstractIntegrationTestCase.php`)
   - Modify `setUp()` with defensive connection recovery

5. `TestUtil` (`tests/Test/TestUtil.php`)
   - Add `resetSharedConnection()` static method

6. `BatchTest` (`tests/Test/Functional/BatchTest.php`)
   - Remove `skipIfKnownBatchHandleIssue()` workaround (after fix verification)

[Dependencies]
No dependency changes required.

The fix operates entirely within the existing dependency set:
- `doctrine/dbal: ^3.6` (unchanged)
- `ext-firebird: ^10.3.2` (unchanged, using v10.3.9)
- No new Composer packages
- No new PHP extensions

The procedural functions `fbird_batch_create()`, `fbird_batch_add()`, `fbird_batch_execute()` are already available in php-firebird v10.3.9 and already imported in the codebase stubs (`stubs/firebird-stubs.php`).

[Testing]
Three-phase testing strategy: diagnostic scripts first, then unit/functional verification, then full suite regression.

**Phase 1: Diagnostic Scripts (pre-fix verification)**

1. `tests/debug/trace-handle-lifecycle.php` - Run inside Docker to confirm the exact point where `fbird_close()` invalidates the shared resource. Must be run BEFORE any code changes to establish baseline.

2. `tests/debug/compare-batch-paths.php` - Run inside Docker to confirm `Batch::fromQuery()` fails while `fbird_batch_create()` succeeds on the same prepared statement.

**Phase 2: Existing test suite verification (post-fix)**

Run these suites to verify each fix:

```bash
# Bug 1 verification - should go from 9 errors to 0
./tests/phpunit.sh -v 4 -- --testsuite Integration-ReadOnly

# Bug 2 verification - should go from 3 errors to 0
./tests/phpunit.sh -v 4 -- --filter BatchTest

# Unit tests - must remain at 1569 OK
./tests/phpunit.sh -v 4 -- --testsuite Unit
```

**Phase 3: Full suite regression (after Bug 1 + Bug 2 fixes)**

```bash
# Bug 3 verification - should complete without SIGSEGV
./tests/phpunit.sh -v 4 --
```

**Phase 4: Static analysis**

```bash
# PHPStan Level 8 must remain at 0 errors
docker compose -f tests/docker-compose.yml run --rm -T app \
  php vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=512M
```

**Modified test files:**
- `tests/Test/Functional/BatchTest.php` - Remove `skipIfKnownBatchHandleIssue()` calls from all 3 test methods after batch fix is verified working.

**Success criteria:**

| Metric | Current | Target |
|--------|---------|--------|
| Integration-ReadOnly errors | 9 | 0 |
| BatchTest errors | 3 | 0 |
| Full-suite SIGSEGV | Crash at ~65% | Complete run |
| Unit tests | 1569 OK | 1569 OK |
| PHPStan Level 8 | 0 errors | 0 errors |

[Implementation Order]
Ordered sequence to minimize conflicts: diagnostics first, then Bug 1 (highest impact), Bug 2 (independent), then Bug 3 verification.

1. **Create diagnostic scripts** (`tests/debug/trace-handle-lifecycle.php` and `tests/debug/compare-batch-paths.php`). Run both inside Docker to confirm hypotheses before making code changes.

2. **Add connection reference registry to `Connection`** - Add `$resourceRegistry` static property and `registerResource()`, `unregisterResource()`, `getResourceRefCount()` methods. Modify `__construct()` to register. Modify `__destruct()` to check count before `fbird_close()`.

3. **Add resource validation to `TransactionManager::createTransaction()`** - Check `get_resource_type($conn) !== 'Unknown'` before calling `fbird_trans_start()`. Throw descriptive `DriverException` on invalid resource.

4. **Fix `FunctionalTestCase::connect()` reconnection path** - Replace destructive `close()` with graceful `null` assignment. Add `TestUtil::resetSharedConnection()` method.

5. **Add defensive recovery to `AbstractIntegrationTestCase::setUp()`** - Wrap `beginTransaction()` in try/catch with resource recovery fallback.

6. **Run Integration-ReadOnly suite** - Verify 24 tests pass with 0 errors.

7. **Fix `Connection::createBatch()` to use procedural API** - Replace `Batch::fromQuery($query)` with `fbird_batch_create($query)` fallback path.

8. **Run BatchTest suite** - Verify 3 tests pass with 0 errors. Remove `skipIfKnownBatchHandleIssue()` workaround.

9. **Run full test suite** - Verify no SIGSEGV. If crash persists, investigate with `--stop-on-error` and GDB.

10. **Run PHPStan Level 8** - Verify 0 errors.

11. **Update `NEXT_STEPS.md`** - Mark Phase 2.5 items 11-14 as completed, update test baselines.

12. **Commit and push** - Atomic commit per fix (Bug 1, Bug 2, cleanup) on `001-quality-improvements` branch.
