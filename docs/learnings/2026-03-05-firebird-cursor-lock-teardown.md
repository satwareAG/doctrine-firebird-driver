# Firebird Cursor Lock in PHPUnit tearDown

**Date:** 2026-03-05
**Context:** PR #85 - test suite simplification / FunctionalTestCase refactor

---

## Problem

After restoring `getWrappedConnection()` traversal in `FunctionalTestCase::getFirebirdConnection()`,
CI started failing with 6 errors:

```text
Doctrine\DBAL\Exception\DriverException: TABLE "DEFAULT_VALUE_TEST" is in use
Doctrine\DBAL\Exception\DriverException: TABLE "GH22_REGRESSION" is in use
Doctrine\DBAL\Exception\DriverException: TABLE "GH23_REGRESSION" is in use
Doctrine\DBAL\Exception\DriverException: TABLE "GH50_TABLE_A" is in use
```

These errors appeared in PHPUnit's error list - not as test failures - meaning they
came from uncaught exceptions in test lifecycle methods.

---

## Root Cause

PHPUnit execution order for a single test:

```text
1. setUp() / @before hooks
2. Test method body
3. tearDown()         <- test's own cleanup
4. @after hooks       <- FunctionalTestCase::disconnect()
```

The `FunctionalTestCase::disconnect()` method (`@after`) calls `gc_collect_cycles()` in step 4.
But individual tests (GH22Test, GH23Test, GH50Test, DefaultValueTest) call `dropTableIfExists()`
directly in their own `tearDown()` in step 3.

At step 3:
- Result set / `Statement` objects from the test body are still in PHP memory (not GCed)
- These objects hold Firebird cursor locks on the shared connection
- Firebird's DDL engine sees the same connection still holding a cursor on the table
- `DROP TABLE` fails with "object TABLE X is in use"

The retry loop in `dropTableIfExists()` attempted `fbird_drop_table_force()` first, but that
function is designed to kill OTHER connections' locks - it cannot release locks held by the
CURRENT connection's own un-freed PHP objects.

---

## Fix

Added `gc_collect_cycles()` at the top of `dropTableIfExists()`:

```php
public function dropTableIfExists(string $name): void
{
    // Force GC before any drop attempt: test statements/result-sets hold Firebird cursor locks
    // on the same connection. Without GC, tearDown() calls arrive before disconnect() @after
    // runs, leaving PHP objects that keep the table "in use" in Firebird.
    gc_collect_cycles();

    // ... rest of the method
}
```

This ensures any PHP `Statement`/`Result` objects from the test body are destroyed (and their
Firebird cursor resources freed) before the DDL drop is attempted.

---

## Why This Pattern Matters

Firebird DDL is fully transactional AND cursor-aware. A table with:
- An active cursor (open result set)
- An uncommitted DDL transaction

...cannot be modified by another DDL statement, even in the same session.

PHP's garbage collector is non-deterministic. Objects containing Firebird cursor resources
are only freed when:
1. They go out of scope AND PHP's cyclic GC collects them
2. OR `gc_collect_cycles()` is called explicitly

In PHPUnit, test method local variables go out of scope after step 2 (test method body),
but PHP does not guarantee immediate GC. Calling `gc_collect_cycles()` at step 3 (tearDown)
is the safe pattern.

---

## Prevention Rule

**Always call `gc_collect_cycles()` before any Firebird DDL (DROP TABLE, CREATE TABLE)
when operating on the same shared connection as the test body.**

For `FunctionalTestCase` specifically: calling it inside `dropTableIfExists()` covers all
callers - both `disconnect()` (`@after`) and individual `tearDown()` methods.

---

## Related

- `docs/learnings/2026-03-05-dbal-connection-wrapper.md` - DBAL 3.x middleware traversal
- `tests/Test/FunctionalTestCase.php` - the fix location
- PR #85 commit `537e298`
