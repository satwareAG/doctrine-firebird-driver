# Code Quality Audit Report

**Date**: 2026-03-23
**Scope**: `src/` - doctrine-firebird-driver
**Purpose**: Inform design of new `~/internal/php-firebird` driver

---

## 1. `@` Error Suppression Inventory (24 occurrences)

Every `@` call in the codebase. These silence errors instead of handling them.

| # | File | Line | Function | Risk |
|---|------|------|----------|------|
| 1 | `Driver/Firebird/Connection.php` | 169 | `fbird_set_exception_mode(THROW)` | **GLOBAL side effect** - affects ALL connections in the process |
| 2 | `Driver/Firebird/Connection.php` | 201 | `fbird_commit()` | Silent failure on commit |
| 3 | `Driver/Firebird/Connection.php` | 203 | `fbird_rollback()` | Silent failure on rollback after failed commit |
| 4 | `Driver/Firebird/Connection.php` | 209 | `fbird_rollback()` | Silent failure on rollback (second path) |
| 5 | `Driver/Firebird/Connection.php` | 318 | `fbird_prepare()` | Silences prepare errors |
| 6 | `Driver/Firebird/Connection.php` | 431 | `fbird_commit()` | Silent failure in autoCommit |
| 7 | `Driver/Firebird/Connection.php` | 436 | `fbird_rollback()` | Silent rollback in autoCommit |
| 8 | `Driver/Firebird/Connection.php` | 446 | `fbird_rollback()` | Silent rollback (second path) |
| 9 | `Driver/Firebird/Connection.php` | 483 | `fbird_commit()` | Silent failure in nested commit |
| 10 | `Driver/Firebird/Connection.php` | 487 | `fbird_rollback()` | Silent rollback in nested commit |
| 11 | `Driver/Firebird/Connection.php` | 496 | `fbird_rollback()` | Silent rollback (third path) |
| 12 | `Driver/Firebird/Connection.php` | 536 | `fbird_commit_ret()` | Silent failure on retain commit |
| 13 | `Driver/Firebird/Connection.php` | 583 | `fbird_rollback()` | Silent failure on rollback |
| 14 | `Driver/Firebird/Connection.php` | 636 | `fbird_savepoint()` | Silent failure creating savepoint |
| 15 | `Driver/Firebird/Connection.php` | 663 | `fbird_release_savepoint()` | Silent failure releasing savepoint |
| 16 | `Driver/Firebird/Connection.php` | 690 | `fbird_rollback_savepoint()` | Silent failure rolling back savepoint |
| 17 | `Driver/Firebird/Connection.php` | 1040 | `fbird_prepare()` | Silences prepare in query() |
| 18 | `Driver/Firebird/Statement.php` | 245 | `fbird_execute()` | Silences execution errors |
| 19 | `Driver/Firebird/Statement.php` | 297 | `fbird_fetch_assoc()` | Silences fetch errors on RETURNING |
| 20 | `Driver/Firebird/Result.php` | 84 | `fbird_fetch_row()` | Silences end-of-cursor warning |
| 21 | `Driver/Firebird/Result.php` | 109 | `fbird_fetch_assoc()` | Silences end-of-cursor warning |
| 22 | `Driver/Firebird/Result.php` | 236 | `fbird_fetch_row()` | Silences fetch with date objects |
| 23 | `Driver/Firebird/Result.php` | 281 | `fbird_fetch_assoc()` | Silences fetch with date objects |
| 24 | `Driver/Firebird/Driver.php` | 64 | `fbird_service_attach()` | Silences service attach |
| 25 | `Driver/Firebird/Driver.php` | 86 | `fbird_pconnect()` | Silences persistent connect |
| 26 | `Driver/Firebird/Driver.php` | 88 | `fbird_connect()` | Silences regular connect |
| 27 | `Schema/FirebirdSchemaManager.php` | 126 | `fbird_connect()` | Silences connect for drop DB |
| 28 | `Schema/FirebirdSchemaManager.php` | 155 | `fbird_drop_db()` | Silences drop database |

**Critical Contradiction**: `fbird_set_exception_mode(FBIRD_EXCEPTION_MODE_THROW)` at Connection.php:169 enables exceptions globally, but 27 `@` calls suppress them per-call. This means exceptions are thrown but immediately silenced, losing error context. The `@` operator does NOT catch exceptions - it only suppresses warnings. If exception mode is THROW, the `@` on commit/rollback is useless for exception cases.

---

## 2. God Classes

| File | LOC | Methods | Verdict |
|------|-----|---------|---------|
| `Platforms/FirebirdPlatform.php` | 1843 | 137 | **GOD CLASS** - 18x the recommended size |
| `Driver/Firebird/Connection.php` | ~1070 | ~25 | **GOD CLASS** - handles connect, trans, query, blobs, metadata, savepoints |
| `Schema/FirebirdSchemaManager.php` | 584 | ~20 | Borderline - mixes DDL with raw queries |
| `Driver/Firebird/Statement.php` | ~300 | 8 | `execute()` alone is ~100 LOC |

### Connection.php Responsibilities (SRP Violation)
- Connection lifecycle (connect/disconnect)
- Transaction management (commit/rollback/savepoint/retain)
- Query execution (prepare + execute)
- Blob handling (create/read/write/close)
- Metadata queries (table list, column list, index list, FK list)
- Auto-commit simulation
- DSN parsing
- Last insert ID tracking

### FirebirdPlatform.php Responsibilities (SRP Violation)
- SQL dialect generation (137 methods)
- Type mapping
- Identifier quoting
- Boolean representation configuration
- Regex expression support
- Date arithmetic
- Like cast length configuration
- Constraint identifier length
- Uses `func_get_arg()` / `func_num_args()` (deprecated pattern)

---

## 3. Long Methods (>50 LOC)

| File | Method | Est. LOC | Issue |
|------|--------|----------|-------|
| `Statement.php` | `execute()` | ~100 | DML detection, RETURNING handling, affected rows, auto-commit all in one method |
| `Connection.php` | `autoCommit()` | ~50 | Multiple code paths for commit/rollback |
| `Connection.php` | `query()` | ~80 | Metadata query with type parsing |
| `Connection.php` | `_getPortableTableColumnDefinition` equivalent | ~100 | Column type mapping in SchemaManager |
| `SchemaManager.php` | `dropDatabase()` | ~70 | Connect, drop, cleanup |
| `Result.php` | `fetchAssociativeWithDateObjects()` | ~50 | Date conversion logic |

---

## 4. Probable Bugs

### BUG-1: Global `fbird_set_exception_mode()` Side Effect (CRITICAL)
**File**: `Connection.php:169`
```php
fbird_set_exception_mode(FBIRD_EXCEPTION_MODE_THROW);
```
This is a **process-global** setting. If multiple connections exist (common in tests or connection pooling), enabling exception mode for one connection affects ALL. Combined with `@` suppression, this creates unpredictable behavior.

### BUG-2: Commit-Then-Rollback Race Condition
**File**: `Connection.php:201-209`
```php
if (! @fbird_commit($this->firebirdActiveTransaction)) {
    @fbird_rollback($this->firebirdActiveTransaction);  // rollback a failed commit?
}
```
Rolling back after a failed commit is undefined behavior in Firebird. The transaction may already be in a limbo state. Should check error code and report, not silently rollback.

### BUG-3: `@` Does Not Suppress Exceptions
**File**: Multiple locations
When `fbird_set_exception_mode(THROW)` is active, `@fbird_commit()` will NOT suppress the exception. The `@` operator only suppresses warnings/errors, not thrown exceptions. This means the `@` on lines 201, 431, 483 etc. are ineffective dead code when exception mode is THROW.

### BUG-4: Resource Leak in Statement on Exception Path
**File**: `Statement.php:225-250`
If `fbird_execute` throws, the `$fbirdResultRc` resource (when returned) is never freed. The `currentResult` is set to null before execution, so the destructor has nothing to clean up.

### BUG-5: Mutable Shared State via Connection Reference
**File**: `Statement.php:274`
```php
$this->connection->setLastInsertId((int) $value);
```
Statement mutates Connection state. If multiple statements share a connection (normal), lastInsertId can be overwritten by a concurrent operation within the same transaction.

### BUG-6: `stream_get_contents` + `fclose` in bindValueInternal
**File**: `Statement.php:179-183`
```php
$content = stream_get_contents($variable);
fclose($variable);
```
Closes a stream the caller may still hold a reference to. The caller's resource becomes invalid. Should document this side effect or not close the stream.

### BUG-7: Duplicate Blob Stream Consumption
**File**: `Statement.php:179-183` AND `Statement.php:226-230`
The same blob stream workaround appears in BOTH `bindValueInternal()` and `execute()`. If `bindValueInternal()` already consumed the stream, `execute()` will operate on a string (not a resource), making the `execute()` check dead code. If `bindValueInternal()` was bypassed (e.g., direct `execute($params)` call), only the `execute()` path runs. This duplication is confusing and error-prone.

### BUG-8: `is_resource()` Checks on php-firebird v8.1.0 Objects
**File**: Multiple (Result.php:76, Statement.php:65, Connection.php:200+)
php-firebird v8.1.0 may return objects instead of resources for some handle types. `is_resource()` will return false for objects, causing incorrect code paths.

### BUG-9: Static Caches in ConnectionWrapper (CRITICAL for Long-Running Processes)
**File**: `ConnectionWrapper.php`
```php
static $identityColumnTables = null;
static $tableSequences = null;
static $database = null;
```
These `static` variables survive across requests in persistent PHP processes (FPM worker reuse, Swoole, RoadRunner). After schema changes (DDL), stale metadata is served indefinitely. This is a data-corruption risk in production.

### BUG-10: Resource Leak in prepare()/createBatch() Exception Path
**File**: `Connection.php`
If `fbird_prepare()` succeeds but `new Statement(...)` constructor throws, the C-level prepared statement resource is never freed. Same pattern in `createBatch()` - `fbird_prepare` result leaks if `Batch::fromQuery()` throws.

### BUG-11: Dual Auto-Commit State Tracking
**File**: `Connection.php`
`$attrAutoCommit` boolean AND `$executionMode->isAutoCommitEnabled()` track the same concept independently. These can drift apart on error paths, causing inconsistent behavior.

---

## 5. Dead Code

| File | What | Reason |
|------|------|--------|
| `Compat/Compat.php` (entire file, 216 LOC) | All 8 methods | PHP 8.4 has `json_validate`, `array_find`, `str_contains`, etc. natively |
| `Statement.php:179-183` | Blob stream workaround in `bindValueInternal()` | Duplicated in `execute()` and may never run |
| `Driver/FirebirdDriver.php` | Entire class | `@psalm-suppress UnusedClass` - dead legacy entry point |
| `Platforms/FirebirdPlatform.php:393` | `func_get_arg(0)` | Deprecated in PHP 8.x; should use named args or nullable param |

---

## 6. Outdated PHP Patterns

| Current | Modern (PHP 8.4+) | Locations |
|---------|-------------------|-----------|
| `switch` in `ExceptionConverter::convert()` | `match` expression | ExceptionConverter.php:59 |
| `func_get_arg()` / `func_num_args()` | Nullable parameter with default | FirebirdPlatform.php:393 |
| Mutable properties without `readonly` | `readonly` properties | Most classes |
| Constructor property promotion (partial) | Full promotion | Connection, Statement, Result |
| `is_resource()` type checks | Object type checks (php-firebird v8.x) | Everywhere |
| `array_flip` + lookup pattern | Direct map or `match` | Statement.php:136 |
| Manual Compat layer | Native functions | Compat/Compat.php (entire) |
| No enums for transaction states | `enum TransactionState` | Connection.php |
| No enums for resource types | `enum FirebirdResourceType` | Statement.php:72, Result.php:67 |
| String concatenation for SQL | Builder pattern | SchemaManager, Connection |

---

## 7. Architecture Issues

### A. No Abstraction Over php-firebird Functions
Every file calls `fbird_*` functions directly. No wrapper/facade. This means:
- Cannot swap implementations for testing
- Cannot add logging/metrics at a single point
- Cannot handle the resource-to-object migration in one place

### B. Transaction State Machine Not Explicit
Connection.php manages transaction state via boolean flags (`transactionNestingLevel`, `inTransaction`). Should be an explicit state machine enum with validated transitions.

### C. SchemaManager Uses Raw `fbird_query()` Instead of Connection
`FirebirdSchemaManager.php:34` imports `fbird_query` directly and `FirebirdSchemaManager.php:126` creates its own connection via `fbird_connect()`. Bypasses the driver's Connection entirely, duplicating connection logic.

### D. No Connection Pool Support
`fbird_pconnect` is available but only used as a flag in Driver.php. No pooling, no health checks, no max connections.

### E. Missing Interface Segmentation
- No `TransactionInterface` - transaction logic buried in Connection
- No `BlobHandleInterface` - blob operations inline in Connection
- No `MetadataProviderInterface` - metadata queries mixed with connection logic

### F. Version-Specific Platforms Use Inheritance Instead of Composition
`Firebird3Platform` -> `Firebird4Platform` -> `Firebird5Platform` extends `FirebirdPlatform`. Changes to base can break all versions. Should use composition with version-specific strategy objects.

---

## 8. Prioritized Improvement List for `~/internal/php-firebird`

### P0 - Critical (Must Have)

| # | Improvement | GH Milestone |
|---|-------------|-------------|
| 1 | **Facade over php-firebird functions** - Single `FirebirdClient` class wrapping all `fbird_*` calls. Enables testing, logging, and resource-to-object migration. | `v1.0-alpha` |
| 2 | **No `@` error suppression** - Use exception mode exclusively. Catch specific exceptions. For end-of-cursor, check return value (false) instead of suppressing warnings. | `v1.0-alpha` |
| 3 | **Remove global `fbird_set_exception_mode()`** - If php-firebird v8.1.0 defaults to exception mode, don't call it. If not, call it once at client init and document the implication. | `v1.0-alpha` |
| 4 | **Resource type abstraction** - Create `FirebirdHandle` value object that wraps both resources (old) and objects (v8.x). Replace all `is_resource()` checks. | `v1.0-alpha` |

### P1 - High (Should Have)

| # | Improvement | GH Milestone |
|---|-------------|-------------|
| 5 | **Transaction state machine** - `enum TransactionState { Active, Committed, RolledBack, Limbo }` with validated transitions. Replace boolean flags. | `v1.0-alpha` |
| 6 | **Extract BlobHandler** - Separate class for blob create/read/write/close. Eliminates the stream-close side effect bug. | `v1.0-alpha` |
| 7 | **Extract MetadataProvider** - Separate class for table/column/index/FK introspection queries. | `v1.0-beta` |
| 8 | **Remove Compat.php** - Target PHP 8.4+ exclusively. Use native `str_contains`, `json_validate`, `array_find`, etc. | `v1.0-alpha` |
| 9 | **Proper resource cleanup** - Use `WeakMap` or explicit tracking for statement/result resources. No destructor-dependent cleanup. | `v1.0-beta` |

### P2 - Medium (Nice to Have)

| # | Improvement | GH Milestone |
|---|-------------|-------------|
| 10 | **Connection pool** - Configurable pool with health checks, max connections, idle timeout. | `v1.1` |
| 11 | **Async/Fiber support** - Non-blocking queries for PHP Fibers. | `v1.2` |
| 12 | **PHP 8.4 enums everywhere** - `TransactionIsolation`, `ExecutionMode`, `FirebirdResourceType`, `TransactionState`. | `v1.0-alpha` |
| 13 | **Named arguments in public API** - All public methods use named params. | `v1.0-alpha` |
| 14 | **SchemaManager uses Connection** - No raw `fbird_query()` calls outside the facade. | `v1.0-beta` |
| 15 | **Version strategy composition** - Replace platform inheritance with version-specific strategy objects. | `v1.1` |

### P3 - Low (Future)

| # | Improvement | GH Milestone |
|---|-------------|-------------|
| 16 | **Event system** - Dispatch events on connect/disconnect/query/commit/rollback for observability. | `v1.2` |
| 17 | **SQL builder** - Type-safe SQL generation for common Firebird patterns (RETURNING, WITH, CTE). | `v1.2` |
| 18 | **Wire protocol docs** - Document the Firebird wire protocol layer for debugging. | `v2.0` |
| 19 | **Connection health check API** - `ping()`, `isAlive()`, `getServerVersion()` without query. | `v1.1` |

---

## 9. Recommended GH Issues for New Driver

### Milestone: `v1.0-alpha` (Foundation)
- [ ] Design `FirebirdClient` facade over php-firebird v8.1.0 functions
- [ ] Implement `FirebirdHandle` value object (resource + object abstraction)
- [ ] Implement transaction state machine with validated transitions
- [ ] Extract `BlobHandler` from connection logic
- [ ] Establish exception handling strategy (no `@`, no global mode)
- [ ] Set PHP 8.4 minimum, remove Compat layer
- [ ] Define public API with named arguments and strict types

### Milestone: `v1.0-beta` (Doctrine Integration)
- [ ] Build Doctrine DBAL 4.x driver adapter on top of `FirebirdClient`
- [ ] Extract `MetadataProvider` for schema introspection
- [ ] Proper resource lifecycle management (no destructor dependency)
- [ ] SchemaManager refactor (uses Connection, not raw fbird_*)
- [ ] Full test suite with Firebird 4.0 and 5.0 CI

### Milestone: `v1.1` (Production Hardening)
- [ ] Connection pool implementation
- [ ] Version strategy composition (replace platform inheritance)
- [ ] Connection health check API
- [ ] Performance benchmarks vs current driver
- [ ] Stress test: 10k transactions, blob throughput, concurrent connections

### Milestone: `v1.2` (Advanced Features)
- [ ] Fiber/async support for non-blocking queries
- [ ] Event system for observability
- [ ] Type-safe SQL builder for Firebird-specific patterns

---

## 10. Summary Statistics

| Metric | Value |
|--------|-------|
| Total `src/` LOC | ~4,133 |
| `@` error suppression calls | 28 |
| God classes (>500 LOC) | 3 (Connection, Platform, SchemaManager) |
| Long methods (>50 LOC) | 6 |
| Probable bugs | 11 |
| Dead code files | 2 (Compat.php, FirebirdDriver.php) |
| Outdated PHP patterns | 10 categories |
| SOLID violations | 6 (SRP x3, OCP x1, DIP x2) |
| Recommended GH issues | 17 |
| Recommended milestones | 4 |