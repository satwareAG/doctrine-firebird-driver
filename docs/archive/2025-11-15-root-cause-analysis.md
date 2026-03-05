# Root Cause Analysis: FetchTest and FetchEmptyTest Failures

**Date:** 2025-11-15  
**Issue:** 22 test failures (20 errors + 2 failures) all in Connection namespace

## Critical Discovery

**ONLY 2 test files in entire functional suite have setUp() methods:**
- `tests/Test/Functional/Connection/FetchEmptyTest.php`
- `tests/Test/Functional/Connection/FetchTest.php`

**Result:** `grep -r "public function setUp" tests/Test/Functional/` shows ONLY these 2 files.

## Root Cause

### PHPUnit Lifecycle Order
1. `@before` annotations execute
2. `setUp()` method executes
3. Test method executes
4. `tearDown()` method executes
5. `@after` annotations execute

### FunctionalTestCase Design
- Uses `@before` annotation for `connect()` method (initializes `$this->connection`)
- Uses `@after` annotation for `disconnect()` method
- Does NOT have `setUp()` or `tearDown()` methods defined
- Methods marked `final` to prevent override

### Problem in FetchTest/FetchEmptyTest

Both tests override `setUp()` and immediately use `$this->connection`:

```php
// FetchEmptyTest.php
protected function setUp(): void
{
    $this->connection->executeStatement('CREATE TABLE fetch_table (test_int INT)');
}

// FetchTest.php  
protected function setUp(): void
{
    $this->connection->executeStatement('CREATE TABLE fetch_table (test_int INT)');
    $this->connection->insert('fetch_table', ['test_int' => 1]);
}
```

### The Issue

While `@before connect()` SHOULD run before `setUp()`, there appears to be a PHPUnit lifecycle interaction issue when:
1. Child class overrides `setUp()`
2. Parent class uses `@before` annotation
3. No explicit parent initialization in child's `setUp()`

**Hypothesis:** The `$this->connection` resource gets established by `@before connect()` but becomes invalid or uninitialized by the time `setUp()` executes. This could be due to:
- Object cloning/serialization in PHPUnit test isolation
- Connection resource not being properly maintained across lifecycle hooks
- Timing issue between `@before` and `setUp()` execution

## Error Details

**Error Location:** `src/Driver/Firebird/Connection.php:223`

```php
return new Statement(
    $this,
    @fbird_prepare($this->connection, $this->firebirdActiveTransaction, $sql),
    //            ^^^^^^^^^^^^^^^^^^^ - Invalid resource
    $visitor->getParameterMap(),
);
```

**Error Message:** `TypeError: fbird_prepare(): supplied resource is not a valid Firebird/InterBase link resource`

This indicates `$this->connection` property in the DBAL Connection object is not a valid Firebird resource when `executeStatement()` is called from test's `setUp()`.

## Solution

**Option 1: Remove setUp() override (RECOMMENDED)**

Move table creation to test methods or use a data provider. This avoids the lifecycle conflict entirely.

**Option 2: Add explicit connection verification**

Add connection check at start of `setUp()`:

```php
protected function setUp(): void
{
    if (!$this->connection->isConnected()) {
        $this->connection->connect();
    }
    // ... rest of setup
}
```

**Option 3: Use @before annotation instead**

Convert `setUp()` to a method with `@before` annotation to match parent's pattern.

## Testing the Fix

After applying fix:
```bash
cd tests && ./phpunit.sh --filter="Fetch" --testdox
```

Expected: All 22 tests should pass.

## Affected Tests

### FetchEmptyTest (6 tests)
1. testFetchAssociative
2. testFetchNumeric
3. testFetchOne
4. testFetchAllAssociative
5. testFetchAllNumeric
6. testFetchFirstColumn

### FetchTest (16 tests)
1. testFetchNumeric
2. testFetchOne
3. testFetchAssociative
4. testFetchAllNumeric
5. testFetchAllAssociative
6. testFetchAllKeyValue
7. testFetchAllKeyValueWithLimit
8. testFetchAllAssociativeIndexed
9. testFetchFirstColumn
10. testIterateNumeric
11. testIterateAssociative
12. testIterateKeyValue
13. testIterateAssociativeIndexed
14. testIterateColumn
15. testFetchAllKeyValueOneColumn
16. testIterateKeyValueOneColumn
