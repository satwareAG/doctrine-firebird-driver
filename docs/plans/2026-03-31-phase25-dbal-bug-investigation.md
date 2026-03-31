# Phase 2.5 - DBAL-Layer Bug Investigation Plan

**Date:** 2026-03-31
**Branch:** `001-quality-improvements`
**Extension:** php-firebird v10.3.9
**Status:** Planning

---

## Executive Summary

Three categories of DBAL-layer test failures remain after confirming that php-firebird
v10.3.8/v10.3.9 C-level fixes are working correctly at the extension API level. All
failures originate in our Doctrine driver layer - not the extension.

| Bug | Failures | Root Cause Hypothesis | Severity |
|-----|----------|----------------------|----------|
| Integration-ReadOnly 9 errors | `beginTransaction()` in setUp | Connection handle invalidated by `__destruct()` calling `fbird_close()` on shared resource | High |
| BatchTest 3 errors | `Batch::fromQuery()` | OO wrapper `Batch` class uses different code path than procedural `fbird_batch_create()` | Medium |
| Full-suite SIGSEGV | Crash at ~65% | Unknown - possibly accumulated resource corruption or GC timing | Critical |

## Key Finding from Root Cause Investigation

The C function `fbird_trans_start()` has parameter spec `|ra` - it accepts a **resource**
and optional array. When `fbird_close()` is called on a connection resource, the internal
C struct field `fbc_connection` is set to NULL (`fbird_connection.c:176`), and the PHP
resource type changes from `"Firebird link"` to `"Unknown"`. Any subsequent
`fbird_trans_start()` call on that resource triggers the error:

> Connection has no OO API handle

This happens because `fbird_trans_start()` dereferences `fbc_connection` which is now NULL.

---

## Bug 1: Integration-ReadOnly 9 Errors

### Symptoms

- Error: `Connection has no OO API handle` (expected expectation)
- Occurs in `AbstractIntegrationTestCase::setUp()` at `$this->connection->beginTransaction()`
- Call chain: `beginTransaction()` -> `TransactionManager::beginTransaction()` ->
  `createTransaction()` -> `fbird_trans_start($conn, $options)` (line 352)

### Hypothesis

The DBAL middleware layer creates a temporary `Connection` wrapper object. During PHP's
garbage collection between test methods, the wrapper's `__destruct()` calls
`fbird_close()` on the shared native resource. The shared `$integrationConnection` still
holds a DBAL `Connection` pointing to the now-invalidated resource.

**Key evidence:**
- `Connection::__destruct()` (line 130-170) explicitly calls `fbird_close($this->connection)`
- `isConnectionValid()` checks PHP resource type but NOT the internal C `fbc_connection` state
- After `fbird_close()`, `is_resource()` returns true briefly but `get_resource_type()` returns `"Unknown"`
- The `__destruct` fallback (line 149-152) only catches `"Unknown"` type - but there's a race window

### Diagnostic Script 1: Trace Handle Lifecycle

Create `tests/debug/trace-handle-lifecycle.php`:

```php
<?php
// Traces when Connection objects are created/destroyed and whether
// the native resource survives across test method boundaries.

// 1. Connect and store reference
// 2. Create a second DBAL Connection wrapper referencing same resource
// 3. Destroy the second wrapper (simulates GC between tests)
// 4. Check if first connection's resource is still valid
// 5. Attempt fbird_trans_start() on first connection
```

**Steps:**
1. Reproduce the exact `AbstractIntegrationTestCase` lifecycle in isolation
2. Add `__destruct` tracing to `Connection` (temporary debug build)
3. Run single Integration-ReadOnly test with `--debug` and `gc_collect_cycles()` between setUp/tearDown
4. Log every `fbird_close()` call with backtrace

### Fix Strategy

| Option | Description | Risk | Effort |
|--------|-------------|------|--------|
| A. Reference counting | Add refcount to prevent `fbird_close()` when other wrappers exist | Low | Medium |
| B. Skip close in `__destruct` for shared connections | Check if resource is shared before closing | Low | Low |
| C. Reconnect on invalid handle | Detect `"Unknown"` type and reconnect transparently | Medium | Medium |
| D. Prevent wrapper GC | Use `WeakReference` or prevent premature destruction | Low | Low |

**Recommended:** Option B first (quick fix), then Option A for robustness.

**Option B implementation sketch:**
```php
// In Connection::__destruct()
// Before calling fbird_close(), check if resource is still referenced elsewhere
// Use a static registry of active connections keyed by resource ID
private static array $activeConnections = [];
```

### Verification

```bash
./tests/phpunit.sh -v 4 -- --testsuite Integration-ReadOnly 2>&1 | head -80
```

Expected: 24 tests, 0 errors (down from 9 errors).

---

## Bug 2: BatchTest 3 Errors

### Symptoms

- All 3 BatchTest methods fail
- Error in `Batch::fromQuery()` OO wrapper path
- Direct procedural `fbird_batch_create()` works (verified in `verify-v10.3.9-fixes.php`)

### Hypothesis

The `Batch::fromQuery()` OO wrapper class uses a different internal code path than the
procedural `fbird_batch_create()`. The OO wrapper may:
1. Hold a reference to a `Firebird\Connection` object that doesn't exist (procedural API returns resources)
2. Have its own handle validation that fails differently
3. Not handle the `fbird_prepare_ex()` result correctly

### Diagnostic Script 2: Compare OO vs Procedural Batch

Create `tests/debug/compare-batch-paths.php`:

```php
<?php
// 1. Connect via fbird_connect()
// 2. Create batch via procedural: fbird_batch_create(fbird_prepare(...), ...)
// 3. Create batch via OO: Batch::fromQuery(fbird_prepare_ex(...))
// 4. Compare results and error states
// 5. Test with Connection::createBatch() (DBAL wrapper)
```

**Steps:**
1. Run procedural batch creation - confirm it works (already verified)
2. Run `Batch::fromQuery()` directly with same prepared statement
3. Run `Connection::createBatch()` through DBAL layer
4. Compare error messages at each level

### Fix Strategy

| Option | Description | Risk | Effort |
|--------|-------------|------|--------|
| A. Use procedural API directly | Bypass `Batch::fromQuery()`, call `fbird_batch_create()` | Low | Low |
| B. Fix OO wrapper | Submit upstream fix for `Batch::fromQuery()` | Medium | High |
| C. Conditional path | Use OO when available and working, fall back to procedural | Low | Medium |

**Recommended:** Option A (bypass OO wrapper for batch operations).

**Option A implementation sketch:**
```php
// In Connection::createBatch()
// Replace: return Batch::fromQuery($query);
// With:    return fbird_batch_create($query, ...);
// Wrap result in a thin adapter if Batch return type is required
```

### Verification

```bash
./tests/phpunit.sh -v 4 -- --filter BatchTest 2>&1 | head -40
```

Expected: 3 tests, 0 errors.

---

## Bug 3: Full-Suite SIGSEGV at ~65%

### Symptoms

- PHPUnit crashes with SIGSEGV during integration test setup
- Occurs at approximately 65% through full test suite (~1569/2336 tests complete)
- Different code path than the C-level shutdown fix (#183)
- Not reproducible with individual test suites

### Hypothesis

Accumulated resource corruption from:
1. Multiple connection open/close cycles without proper cleanup
2. GC finalizer order-of-destruction issues with Firebird resources
3. Memory corruption from invalid resource access (related to Bug 1)
4. PHP process-level resource table overflow

### Diagnostic Approach

**Phase 1: Identify the crashing test**
```bash
./tests/phpunit.sh -v 4 -- --stop-on-error --stop-on-failure 2>&1 | tail -20
```

**Phase 2: Binary search for trigger**
```bash
# Run first half
./tests/phpunit.sh -v 4 -- --testsuite Unit,Functional 2>&1 | tail -10

# Run second half
./tests/phpunit.sh -v 4 -- --testsuite Integration-ReadOnly,Integration 2>&1 | tail -10

# If Integration crashes, narrow down:
./tests/phpunit.sh -v 4 -- --filter "AlbumTest|ArtistTest" 2>&1 | tail -10
```

**Phase 3: Run under GDB**
```bash
# Already have tests/debug/gdb-reproduce.sh
# Modify to capture the exact test + backtrace
```

**Phase 4: Valgrind analysis**
```bash
# Inside Docker container:
valgrind --tool=memcheck --track-origins=yes \
  php vendor/bin/phpunit --testsuite Full 2>&1 | tee /tmp/valgrind-full.log
```

### Fix Strategy

This bug likely resolves itself once Bug 1 is fixed, since the handle invalidation
is the most probable source of accumulated corruption. Fix Bug 1 first, then re-test
full suite.

If SIGSEGV persists after Bug 1 fix:

| Option | Description | Risk | Effort |
|--------|-------------|------|--------|
| A. Process isolation | Run integration tests in separate PHPUnit process | Low | Low |
| B. Connection pooling | Implement proper connection lifecycle management | Low | High |
| C. Upstream report | File php-firebird issue with GDB backtrace | N/A | Medium |

### Verification

```bash
./tests/phpunit.sh -v 4 -- 2>&1 | tail -20
```

Expected: Full suite completes without SIGSEGV.

---

## Execution Order

```text
1. Create diagnostic scripts (Bug 1, Bug 2)     ~1 session
2. Run diagnostics, confirm hypotheses           ~1 session
3. Implement Bug 1 fix (Option B)                ~1 session
4. Implement Bug 2 fix (Option A)                ~1 session
5. Re-test full suite for Bug 3                  ~1 session
6. If SIGSEGV persists, investigate Bug 3        ~1-2 sessions
7. Update test baselines and NEXT_STEPS.md       ~0.5 session
```

**Total estimated:** 5-7 sessions

## Dependencies

- Bug 3 likely depends on Bug 1 fix
- Bug 2 is independent
- All bugs require Docker test environment (`tests/phpunit.sh`)

## Success Criteria

| Metric | Current | Target |
|--------|---------|--------|
| Integration-ReadOnly errors | 9 | 0 |
| BatchTest errors | 3 | 0 |
| Full-suite SIGSEGV | Crash at 65% | Complete run |
| PHPStan Level 8 | 0 errors | 0 errors |

---

## Files to Modify

| File | Change | Bug |
|------|--------|-----|
| `src/Driver/Firebird/Connection.php` | Add connection registry, fix `__destruct` | Bug 1 |
| `src/Driver/Firebird/Connection.php` | Replace `Batch::fromQuery()` with procedural | Bug 2 |
| `tests/debug/trace-handle-lifecycle.php` | New diagnostic script | Bug 1 |
| `tests/debug/compare-batch-paths.php` | New diagnostic script | Bug 2 |
| `tests/Test/Integration/AbstractIntegrationTestCase.php` | Possible lifecycle fix | Bug 1 |
| `NEXT_STEPS.md` | Update after fixes | All |

## References

- Root cause analysis: `fbird_connection.c:176` (`fbc_connection = NULL` on close)
- Transaction error source: `fbird_transaction.c:307,803,896`
- Extension verification: `tests/debug/verify-v10.3.9-fixes.php`
- C parameter spec: `fbird_trans_start()` uses `|ra` (resource + optional array)
