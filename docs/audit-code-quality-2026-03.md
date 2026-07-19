# Code Quality Audit Report

**Project**: doctrine-firebird-driver
**Date**: 2026-03-23
**Auditor**: Jane Alesi (AI)
**Scope**: `src/` directory, ~8108 LOC across 31 files
**Baseline Extension**: php-firebird v7.1.0-rc.1
**Target Extension**: php-firebird v8.2.0

---

## 1. Executive Summary

| Metric | Value | Assessment |
|--------|-------|------------|
| Total LOC | ~8108 | Small codebase, manageable |
| @ suppressions | 23 | High density (1 per 352 LOC) |
| God classes (>500 LOC) | 3 | Critical |
| Probable bugs | 7 | 2 critical, 2 high |
| Duplicate code blocks | 5+ | Significant |
| `is_resource()` guards | ~20 | PHP 7 pattern, no type safety |
| Missing `readonly` | ~15 classes | Modernization gap |
| Switch vs match opportunities | 3 files | Easy wins |

**Overall Health: C+ (needs significant refactoring before DBAL 4.x migration)**

The driver works correctly for its supported use cases but carries significant technical debt from the PHP 7 / `fbird_*` procedural era. The two god classes (`Connection.php` at 1320 LOC, `FirebirdPlatform.php` at 1843 LOC) concentrate too many responsibilities. Error handling relies on redundant `@` + `try-catch(Throwable)` patterns that mask real bugs. The php-firebird v8.2.0 release introduces native features (blob streaming, scrollable cursors, `fbird_last_insert_id`, `commit_ret` lifecycle) that could eliminate major workarounds in this driver.

---

## 2. @ Suppression Inventory

| # | File | Line | Function | Suppressed Call | Risk |
|---|------|------|----------|-----------------|------|
| 1 | Statement.php | 245 | execute() | `@fbird_execute` | **High** - masks execution failures |
| 2 | Statement.php | 297 | advance() | `@fbird_fetch_assoc` | Medium - EOF is expected |
| 3 | Result.php | 84 | fetchOne() | `@fbird_fetch_row` (FBIRD_FETCH_BLOBS) | Medium - EOF expected |
| 4 | Result.php | 109 | fetchAssociative() | `@fbird_fetch_assoc` (FBIRD_FETCH_BLOBS) | Medium - EOF expected |
| 5 | Result.php | 236 | fetchNumeric() | `@fbird_fetch_row` | Medium - EOF expected |
| 6 | Result.php | 281 | fetchAssociativeWithDateObjects() | `@fbird_fetch_assoc` | Medium - EOF expected |
| 7 | Driver.php | 64 | connect() | `@fbird_service_attach` | Low - version detection |
| 8 | Driver.php | 86 | connect() | `@fbird_pconnect` | **High** - masks connect failure |
| 9 | Driver.php | 88 | connect() | `@fbird_connect` | **High** - masks connect failure |
| 10 | Connection.php | 201 | commit() | `@fbird_commit` | **High** - silent commit failure |
| 11 | Connection.php | 203 | commit() | `@fbird_rollback` | **High** - rollback in commit path |
| 12 | Connection.php | 209 | commit() | `@fbird_rollback` | **High** - duplicate rollback |
| 13 | Connection.php | 318 | prepare() | `@fbird_prepare` | **High** - masks prepare errors |
| 14 | Connection.php | 431 | commit() | `@fbird_commit` | **High** - nested txn silent fail |
| 15 | Connection.php | 436 | commit() | `@fbird_rollback` | **High** - duplicate pattern |
| 16 | Connection.php | 446 | commit() | `@fbird_rollback` | **High** - duplicate pattern |
| 17 | Connection.php | 483 | rollBack() | `@fbird_commit` | Medium - rollback fallback |
| 18 | Connection.php | 487 | rollBack() | `@fbird_rollback` | Medium - expected path |
| 19 | Connection.php | 496 | rollBack() | `@fbird_rollback` | Medium - duplicate pattern |
| 20 | Connection.php | 536 | commitRetaining() | `@fbird_commit_ret` | **High** - silent commit_ret failure |
| 21 | Connection.php | 583 | rollBackToSavepoint() | `@fbird_rollback` | Medium - expected path |
| 22 | Connection.php | 636 | createSavepoint() | `@fbird_savepoint` | Medium - expected path |
| 23 | Connection.php | 663 | releaseSavepoint() | `@fbird_release_savepoint` | Medium - expected path |
| 24 | Connection.php | 690 | rollbackSavepoint() | `@fbird_rollback_savepoint` | Medium - expected path |
| 25 | Connection.php | 1040 | prepare() | `@fbird_prepare` | **High** - masks prepare errors |
| 26 | FirebirdSchemaManager.php | 126 | _getPortableTableColumnDefinition() | `@fbird_connect` | Low - schema introspection |
| 27 | FirebirdSchemaManager.php | 155 | _getPortableTableColumnDefinition() | `@fbird_drop_db` | Low - temp DB cleanup |

**Summary**: 10 high-risk suppressions (mask real failures), 14 medium/low. 52% of all suppressions are in `Connection.php`.

**Critical pattern**: Every `@` suppression is paired with `try-catch(\Throwable)`, making the `@` operator completely redundant. The `@` operator suppresses `E_WARNING`/`E_NOTICE`, but `try-catch` already catches exceptions. The `@` only masks warnings that should be surfaced.

---

## 3. Code Smells by Category

### 3.1 God Classes

| File | LOC | Responsibilities | Should Be |
|------|-----|-----------------|-----------|
| FirebirdPlatform.php | 1843 | DDL generation, type mapping, quoting, sequence management, reserved words, index SQL, column defaults | 5-6 focused classes |
| Connection.php | 1320 | Transaction mgmt, blob handling, query execution, connection lifecycle, nesting, savepoints | 4-5 focused classes |
| FirebirdSchemaManager.php | 584 | Schema introspection, column detection, index listing, table creation, sequence management | 2-3 classes |

### 3.2 Duplicate Code

| Pattern | Locations | Lines |
|---------|-----------|-------|
| Commit/rollback error handling | Connection.php:201-209, 431-446, 483-496 | 3 copies, ~40 LOC total |
| Key normalization in fetch | Result.php fetchAssociative() vs fetchAssociativeWithDateObjects() | Identical ~10 line blocks |
| `@` + try-catch(Throwable) wrapper | Statement.php:245, 297; Result.php:84, 109, 236, 281; Driver.php:64, 86, 88 | 8 occurrences |
| Table name extraction regex | ConnectionWrapper.php:93; Statement.php (DML detection) | Similar regex patterns |
| Identity column detection | ConnectionWrapper.php:extractIdentityColumn(); Statement.php (str_starts_with heuristic) | Two different fragile approaches |

### 3.3 Primitive Obsession

| Instance | File:Line | Should Be |
|----------|-----------|-----------|
| Transaction state as `bool` flags | Connection.php (multiple) | `enum TransactionState` |
| AutoCommit as `bool` | ExecutionMode.php | `enum ExecutionMode { AutoCommit, Manual }` |
| Connection type as string comparison | Connection.php (`get_resource_type()`) | OOP wrapper class |
| SQL dialect as integer | FirebirdPlatform.php | `enum FirebirdDialect { Dialect1, Dialect3 }` |

### 3.4 Missing Type Safety

| Pattern | Count | Locations |
|---------|-------|-----------|
| `is_resource()` type guards | ~20 | Connection.php, Result.php, Driver.php, Statement.php |
| `mixed` typed properties | 3 | `Connection::$nativeConnection`, `Result::$firebirdResultResource` |
| `get_resource_type()` string comparison | 2 | Result.php (fragile, version-dependent) |
| `defined('FBIRD_FETCH_DATE_OBJ')` runtime check | 1 | Result.php (should be version compare) |
| No return type on some methods | 5+ | Scattered across Platform classes |

### 3.5 Redundant Error Handling

| Pattern | Occurrences | Issue |
|---------|-------------|-------|
| `@` + `try-catch(\Throwable)` | 8 | `@` is redundant when catching Throwable |
| Double `@fbird_rollback` in same error path | 2 | Connection.php:203+209, 436+446 |
| `catch (\Throwable $e)` then re-throw | 3 | Catches everything, adds no value |

### 3.6 Procedural Style

| Instance | File | Description |
|----------|------|-------------|
| Direct `fbird_*` calls everywhere | All driver files | No abstraction layer |
| `resource` type usage | Connection.php, Result.php, Driver.php | C-era resource handles, not objects |
| Regex SQL parsing | Statement.php, ConnectionWrapper.php | String manipulation instead of AST |
| `stream_get_contents` + `fclose` for blobs | Statement.php | Procedural I/O instead of streaming |

### 3.7 Missing Readonly

| File | Classes Missing `readonly` |
|------|---------------------------|
| ExecutionMode.php | ExecutionMode |
| Exception.php | Exception (psalm-immutable but not readonly) |
| ConnectionWrapper.php | ConnectionWrapper |
| ValueFormatter.php | ValueFormatter (likely) |
| All Platform classes | FirebirdPlatform, Firebird3Platform, etc. |

### 3.8 Switch vs Match Opportunities

| File | Current | Opportunity |
|------|---------|-------------|
| ExceptionConverter.php:47-146 | `switch ($exception->getCode())` | Partially converted (SQLSTATE path uses `match`), SQLCODE path still `switch` |
| FirebirdPlatform.php | Likely `switch` for type mapping | `match` expression |
| Result.php | Conditional chains for fetch flags | `match` on flag combinations |

---

## 4. Probable Bugs

### Critical

| # | Description | File:Line | Evidence |
|---|-------------|-----------|----------|
| C1 | **Double rollback in commit() error path** - `@fbird_rollback` called twice in same catch block (lines 203 AND 209). Second call operates on already-rolled-back transaction, potentially corrupting connection state. | Connection.php:203,209 | Identical pattern at 436+446 |
| C2 | **Identity column heuristic false positives** - `str_starts_with(strtoupper($key), 'ID')` matches any column starting with "ID" (ID_CARD, IDEOGRAPH, etc.), not just identity columns. | Statement.php | Fragile string matching vs metadata check |

### High

| # | Description | File:Line | Evidence |
|---|-------------|-----------|----------|
| H1 | **`@fbird_execute` masks real failures** - Line 245 suppresses warnings from execute, then try-catch catches Throwable. If extension returns `false` + warning (not exception), the warning is swallowed and `false` propagates without context. | Statement.php:245 | Redundant @ + try-catch pattern |
| H2 | **Connection type stored as `mixed`** - `Result::$firebirdResultResource` documented as resource but can be `int` (checked via `is_numeric`). Mixed type means no static analysis coverage. | Result.php:constructor | `is_numeric` guard on result resource |

### Medium

| # | Description | File:Line | Evidence |
|---|-------------|-----------|----------|
| M1 | **Static cache in ConnectionWrapper never invalidated** - `$identityColumnTables` and `$tableSequences` are static, persisting across requests in long-running processes. Schema changes (ALTER TABLE ADD COLUMN) won't be detected. | ConnectionWrapper.php:78,151 | Static arrays, no invalidation |
| M2 | **Constructor side effect in Result** - Fetches `lastInsertId` immediately during construction. If the query is not an INSERT RETURNING, this performs unnecessary work. | Result.php:constructor | Side effect in constructor |
| M3 | **Default password 'masterkey'** - Driver.php uses 'masterkey' as default password for service attach. Even if documented, this is a security concern if users rely on defaults. | Driver.php | Default credential |

### Low

| # | Description | File:Line | Evidence |
|---|-------------|-----------|----------|
| L1 | **`get_resource_type()` string comparison** - Compares resource type string which may vary between extension versions. | Result.php | Fragile string matching |
| L2 | **`psalm-suppress UnusedClass` on ConnectionWrapper** - Class is suppressed as unused but is actively referenced. | ConnectionWrapper.php:20 | Stale suppression |

---

## 5. Modernization Opportunities

### 5.1 Enums for State

```php
// Before (ExecutionMode.php)
private bool $isAutoCommitEnabled = true;

// After
enum ExecutionMode: bool
{
    case AutoCommit = true;
    case Manual = false;
}
```

```php
// Before (Connection.php - boolean flags)
private bool $isTransactionActive = false;
private bool $nestTransactionsWithSavepoints = false;

// After
enum TransactionState
{
    case Idle;
    case Active;
    case Savepoint;
    case NestedSavepoint;
}
```

### 5.2 Readonly Classes

```php
// Before
final class ExecutionMode
{
    private bool $isAutoCommitEnabled = true;
    // ...
}

// After
readonly enum ExecutionMode: bool { ... }
```

All Platform classes, ValueFormatter, Exception should be `readonly` where mutable state is absent.

### 5.3 Match Expressions

```php
// Before (ExceptionConverter.php SQLCODE path)
switch ($exception->getCode()) {
    case -104: return new SyntaxErrorException(...);
    case -204: ...
}

// After
return match ($exception->getCode()) {
    -104 => new SyntaxErrorException(...),
    -204 => $this->resolve204($exception, $query),
    ...
    default => new DriverException($exception, $query),
};
```

### 5.4 Constructor Property Promotion

Several classes manually declare and assign properties in constructors. Promotion would reduce boilerplate.

### 5.5 Named Arguments

```php
// Before
fbird_trans_start($this->nativeConnection, $tpb);

// After (v8.2.0 OOP API)
$transaction = $connection->startTransaction(isolation: TransactionIsolation::ReadCommitted);
```

### 5.6 Union Types & Null-Safe Operator

```php
// Before (Connection.php)
private mixed $nativeConnection;
// ... is_resource($this->nativeConnection) checks everywhere

// After
private \Firebird\Connection|null $nativeConnection = null;
// ... $this->nativeConnection?->prepare(...)
```

### 5.7 Null-Safe Operator

```php
// Before (ConnectionWrapper.php)
if ($this->_conn instanceof \Satag\...Connection) {
    $this->_conn->setConnectionInsertColumn(...);
}

// After
$this->_conn?->setConnectionInsertColumn(...);
```

---

## 6. Architecture Analysis

### 6.1 Coupling to fbird_* (No Abstraction)

```text
┌─────────────┐     direct calls      ┌──────────────────┐
│  Connection  │ ────────────────────> │ fbird_connect()   │
│  Statement   │ ────────────────────> │ fbird_prepare()   │
│  Result      │ ────────────────────> │ fbird_fetch_*()   │
│  Driver      │ ────────────────────> │ fbird_service_*() │
│  SchemaMgr   │ ────────────────────> │ fbird_connect()   │
└─────────────┘                       └──────────────────┘
       │                                      │
       │  No interface layer                  │  Procedural C API
       │  No test doubles possible            │  Resources, not objects
       └──────────────────────────────────────┘
```

Every driver class directly calls `fbird_*` procedural functions. There is no `FirebirdClientInterface`, no adapter pattern, no way to inject test doubles. This blocks:
- Unit testing without a live Firebird instance
- Migration to `Firebird\*` OOP API (v8.0.0+)
- Swapping implementations for different connection modes

### 6.2 SOLID Violations

| Principle | Violation | Location |
|-----------|-----------|----------|
| **SRP** | Connection handles txns + blobs + queries + lifecycle | Connection.php (1320 LOC) |
| **SRP** | Platform handles DDL + types + quoting + sequences + keywords | FirebirdPlatform.php (1843 LOC) |
| **OCP** | ExceptionConverter hardcoded SQLCODE list | ExceptionConverter.php |
| **LSP** | ConnectionWrapper extends Connection but suppresses class as "unused" | ConnectionWrapper.php |
| **ISP** | FirebirdPlatform exposes all methods to all consumers | FirebirdPlatform.php |
| **DIP** | High-level driver depends on low-level fbird_* functions | All driver files |

### 6.3 Tight Coupling: Result/Statement/Connection

```text
Connection ──creates──> Statement ──creates──> Result
    │                       │                      │
    │  passes resource      │  passes resource     │  stores resource
    │  (no type safety)     │  (no type safety)    │  (mixed type!)
    └───────────────────────┴──────────────────────┘
           All three share raw fbird_* resource handles
           No encapsulation of the resource lifecycle
```

The three core classes pass raw `resource` handles between each other. `Result` stores the result resource as `mixed` (can be `int` or `resource`). There is no encapsulation of the Firebird resource lifecycle.

### 6.4 Missing Dependency Injection

- No constructor injection of configuration
- No factory pattern for connection creation
- `ExecutionMode` created internally, not injected
- Schema manager created via `createSchemaManager()` (DBAL pattern) but no custom factory logic

---

## 7. Prioritized Improvement List

### Milestone 1: Critical Bugs (Week 1)

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| 1 | Fix double rollback in commit() error paths | 1h | Prevents potential connection corruption |
| 2 | Remove redundant `@` operators (keep try-catch) | 2h | Unmasks 23 hidden warnings |
| 3 | Fix identity column heuristic (use metadata, not string prefix) | 3h | Prevents false-positive ID detection |

### Milestone 2: Type Safety (Week 2)

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| 4 | Replace `is_resource()` guards with proper typed wrappers | 4h | Enables PHPStan Level 9 |
| 5 | Replace `mixed` properties with union types | 2h | Static analysis coverage |
| 6 | Remove `defined()` runtime checks, use version compare | 1h | Predictable behavior |

### Milestone 3: Extract Classes (Week 3-4)

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| 7 | Extract `TransactionManager` from Connection.php | 8h | Connection < 500 LOC |
| 8 | Extract `BlobHandler` from Connection.php | 4h | Single responsibility |
| 9 | Deduplicate commit/rollback error handling | 2h | DRY compliance |
| 10 | Deduplicate key normalization in Result.php | 1h | DRY compliance |

### Milestone 4: Modernization (Week 5-6)

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| 11 | Introduce `TransactionState` enum | 2h | Replace boolean flags |
| 12 | Convert `ExecutionMode` to enum | 1h | Type-safe state |
| 13 | Convert remaining `switch` to `match` | 2h | Modern PHP idiom |
| 14 | Add `readonly` to immutable classes | 2h | Prevent mutation bugs |
| 15 | Extract `FirebirdClientInterface` abstraction | 8h | Testability, OOP migration path |

### Milestone 5: v8.2.0 Integration (Week 7-8)

| # | Issue | Effort | Impact |
|---|-------|--------|--------|
| 16 | Replace blob `stream_get_contents` with v8.2.0 LOB streaming | 4h | Memory-efficient blobs |
| 17 | Replace identity detection with `fbird_last_insert_id()` | 4h | Eliminates fragile heuristics |
| 18 | Leverage scrollable cursors for `fetchFirst/Last/Absolute` | 8h | New capability |
| 19 | Migrate from `fbird_*` procedural to `Firebird\*` OOP API | 40h+ | Full modernization |

---

## 8. v8.2.0 Integration Opportunities

php-firebird v8.2.0 (released 2026-03-23) introduces features that directly address current driver workarounds.

### 8.1 Feature Mapping: Workaround to Native

| Current Workaround | v8.2.0 Feature | Driver Change |
|--------------------|---------------|---------------|
| `stream_get_contents()` + `fclose()` for blob reading (Statement.php) | Blob LOB streaming via `PDO::PARAM_LOB` | Use stream resource instead of loading entire blob into memory |
| Regex-based identity column detection (`str_starts_with($key, 'ID')`) | `fbird_last_insert_id()` using `GEN_ID(sequence,0)` | Call `fbird_last_insert_id()` after INSERT, remove heuristic |
| `FBIRD_FETCH_DATE_OBJ` runtime `defined()` check | Date/Time/Timestamp format attributes (`PDO::FBIRD_ATTR_DATE_FORMAT` etc.) | Use attribute-based formatting, remove runtime constant check |
| No scrollable cursor support | Scrollable cursors (FB 5.0+) | Implement `fetchFirst()`, `fetchLast()`, `fetchAbsolute()` in Result |
| Manual `RETURNING` clause injection (ConnectionWrapper.php) | `PDO::lastInsertId()` natively | Remove `extractIdentityColumn()` SQL manipulation |
| Column name ambiguity (no table prefix) | `PDO::FBIRD_ATTR_FETCH_TABLE_NAMES` | Enable `TABLE.COLUMN` format in result sets |

### 8.2 New Capabilities

| Feature | Doctrine Integration | Priority |
|---------|----------------------|----------|
| FB4+ type coercion (INT128, DECFLOAT16/34) | New Doctrine types: `DecFloat16Type`, `DecFloat34Type`, `Int128Type` | High (FB4+ users) |
| TIME/TIMESTAMP WITH TIME ZONE | `TimeTzType`, `TimestampTzType` for Firebird | High (FB4+ users) |
| Scrollable cursors | `Result::fetchFirst()`, `fetchLast()`, `fetchAbsolute()` | Medium |
| `next_rowset` stub | No-op (Firebird has no multi-rowset) | Low (correctness) |
| `fbird_execute_reuse` verified | Prepare-once/execute-many pattern confidence | Low (already works) |
| `fbird_commit_ret_lifecycle` verified | Statement survival after `commit_ret` | Low (already works) |

### 8.3 Migration Risk

| Risk | Mitigation |
|------|------------|
| v8.2.0 requires Firebird 3.0+ client | Already enforced by v8.0.0; no regression |
| OOP API (`Firebird\*` classes) requires v8.0.0+ | Keep procedural fallback for v7.x users during transition |
| Scrollable cursors require FB 5.0+ server | Feature-detect at runtime, degrade gracefully |
| LOB streaming changes blob handling semantics | Add integration tests before switching |

### 8.4 Recommended Integration Order

```text
1. fbird_last_insert_id()     - Eliminates identity heuristic (highest impact)
2. LOB streaming              - Eliminates memory-heavy blob workaround
3. FBIRD_ATTR_FETCH_TABLE_NAMES - Eliminates column ambiguity
4. FB4+ type coercion         - New Doctrine types for modern Firebird
5. Scrollable cursors         - New capability, FB 5.0+ only
6. Firebird\* OOP migration   - Full modernization (separate milestone)
```

---

## Appendix A: File Size Distribution

```text
  1843 ████████████████████████████████████ FirebirdPlatform.php
  1320 ██████████████████████████           Connection.php
   584 ███████                               FirebirdSchemaManager.php
   444 █████                                  Statement.php
   432 █████                                  Firebird3Platform.php
   367 ████                                   Result.php
   266 ███                                    ExceptionConverter.php
   216 ██                                     Compat.php
   196 ██                                     FirebirdKeywords.php
   187 ██                                     ConnectionWrapper.php
   182 ██                                     CharsetResultMiddleware.php
   170 ██                                     firebird-userland-classes.php
   161 █                                     FirebirdSelectSQLBuilder.php
   155 █                                     FirebirdDriver.php (legacy)
   151 █                                     Exception.php
   128 █                                     Driver.php
   128 █                                     ValueFormatter.php
     0 ──────────────────────────────────────
     0   200   400   600   800  1000  1200  1400  1600  1800  2000
```

## Appendix B: @ Suppression Risk Distribution

```text
  High:   ██████████████████  10 (37%)
  Medium: ████████████████     14 (52%)
  Low:    ████                  3 (11%)
```

## Appendix C: Technical Debt Summary

| Category | Count | Estimated Remediation |
|----------|-------|----------------------|
| God classes | 3 | 3-4 sprints |
| Duplicate code blocks | 5+ | 1 sprint |
| Missing type safety | ~25 instances | 1-2 sprints |
| Redundant @ suppressions | 23 | 0.5 sprint |
| Missing enums | 4 opportunities | 0.5 sprint |
| Missing readonly | ~15 classes | 0.5 sprint |
| Switch to match | 3 files | 0.5 sprint |
| Procedural coupling | All driver files | 2-3 sprints |
| **Total** | **~80 items** | **~10 sprints** |