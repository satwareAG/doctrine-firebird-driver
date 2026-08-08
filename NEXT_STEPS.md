# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-08-08
**Branch:** `4.4.x` | **Release:** `v4.6.0` (2026-08-08) | **PHP:** 8.2-8.5 | **Firebird:** 3.0/4.0/5.0
**Extension:** php-firebird v13.0.3 | **DBAL:** 4.4.4

---

## Current State

### v4.6.0 — Diagnostics & Observability

**Status:** Released 2026-08-08. All CI green.

The `4.4.x` branch is the active release line, shipping on DBAL 4.4.x with
php-firebird v13.0.3 (ext-firebird ^13.0.1). The `3.10.x` branch is in
maintenance mode (DBAL 3.x).

### Test Results

| Suite | Tests | Errors | Failures | Skipped |
|-------|-------|--------|----------|---------|
| Unit | 1624 | 0 | 0 | 48 |
| Functional FB3 | 576 | 0 | 0 | 58 |
| Functional FB4 | 576 | 0 | 0 | 54 |
| Functional FB5 | 576 | 0 | 0 | 54 |
| Integration-ReadOnly | 24 | 0 | 0 | 0 |
| Integration-Write | 96 | 0 | 0 | 3 |

### Quality Gates

| Gate | Result |
|------|--------|
| PHPStan Level 8 | 0 errors (90 baselined DBAL 4.4->5.0 deprecation warnings) |
| Psalm | 0 errors (114 baselined pre-existing issues) |
| PHP_CodeSniffer | 0 errors |
| ext-firebird | v13.0.2 loaded (constraint: ^13.0.1) |
| DBAL | 4.4.4 installed |
| Stubs | v13.0.3 |
| CI (PHP x Firebird Matrix) | 14/14 jobs green |
| Windows CI | green (v13.0.0 DLL) |
| CodeQL | green |

### What was done (v4.5.0 - 2026-07-19)

1. **Merge 3.10.x into 4.4.x** - brought all 181 modern commits (v3.13.0-v3.19.0)
   including v13 support, #127 BLOB fix, #114/#115 forceNewConnection, #124 SQL
   Parser decoupling, #117/#119 charset asymmetry docs + tests.
2. **DBAL4 API adaptations** - re-applied Connection/Statement/Platform/Schema
   signature changes for DBAL4 (quote, lastInsertId, beginTransaction/commit/
   rollBack void, getNativeConnection, TransactionIsolationLevel enum,
   ColumnDiff API, getCreateTableSQL, getLocateExpression, doModifyLimitQuery).
3. **CI promotion** - FB4/FB5 promoted from experimental to required.
4. **v13 optimizations** - R3 (fbird_ping), R4 (fbird_server_version replaces
   service-attach), R9 (fbird_escape_literal).
5. **Code review fixes** - dead code in ConnectionWrapper::lastInsertId(),
   setLastInsertTable moved after failure check, misnamed test renamed.
6. **SchemaTest investigation** - root cause identified (Firebird metadata locks
   at transaction level), issue filed to php-firebird (#540).

### What was done (v4.5.1 - 2026-07-20)

1. **Test coverage expansion** - 17 missing platform tests from DBAL 4.x
   AbstractPlatformTestCase (104 instances across 4 platform variants),
   43 comparator tests from DBAL 4.x AbstractComparatorTestCase, plus
   adapted functional test classes (ForeignKeyConstraintTest, AlterTableTest,
   BigIntTypeTest, SequenceTest, ResultMetadataTest).
2. **FirebirdComparator accepts ComparatorConfig** - was silently dropped,
   breaking withDetectRenamedColumns(false) and similar config.
3. **FirebirdPlatform::columnsEqual() override** - autoincrement changes were
   invisible because Firebird's SQL declaration is identical with/without
   autoincrement (identity emulated via sequences).
4. **dropDatabase() is_resource bug fix** - ext-firebird 13.0 returns objects,
   not resources; error path was always entered.
5. **createDatabase() deprecation fix** - replaced fbird_query(FBIRD_CREATE)
   with fbird_create_database() (security improvement: built-in escaping +
   charset validation).
6. **Driver::connect() warning suppression** - @ on all 3 connect branches.
7. **float/real type mappings corrected** to SMALLFLOAT (was silently widening
   to DOUBLE PRECISION).
8. **ReflectionProperty::setAccessible() removed** - deprecated in PHP 8.5.
9. **80 phpcs errors fixed** - Code Quality gate now passing.
10. **Psalm baseline regenerated** - 112 pre-existing errors captured (114 as of v4.6.0: +2 `PossiblyUnusedMethod` for `getFbirdErrCode`/`getFbirdErrMsg`).
11. **Windows CI updated** to php-firebird v13.0.0 DLL (was v12.0.0-rc.11).
12. **4 Dependabot PRs merged** (actions/checkout, codeql-action, codecov).

### What was done (v4.5.2 - 2026-07-20)

1. **R1: BLOB sub_type detection** - replaced NULL-byte heuristic in
   `CharsetResultMiddleware` with `fbird_field_info()` sub_type lookup. Binary
   BLOBs (sub_type 0) passed through without transcoding; text BLOBs (sub_type 1)
   transcoded. Fixes issue #130 (binary BLOBs without NULL bytes corrupted).
2. **`Firebird\Result::getBlobSubTypes()`** - lazy-cached column-index to
   BLOB sub_type map. Graceful degradation to heuristic when inner Result is
   not native Firebird Result.

### What was done (2026-08-08 housekeeping)

1. **3 Dependabot PRs merged** - actions/checkout 6.0->7.0 (#138),
   codeql-action 4.37.1->4.37.4 (#143, #144). CodeQL Analyze (actions) now
   passing (was failing since 2026-07-20).
2. **ext-firebird constraint bumped** `^13.0` -> `^13.0.1` - excludes v13.0.0
   with 15 critical bugs (BLOB ID truncation #516, IStatus memory leaks
   #512-526, FB3 transaction failures #518, fetch_object ctor_args #522).
3. **php-firebird-stubs bumped** `^13.0.0` -> `^13.0.1` (resolved to v13.0.3).
4. **squizlabs/php_codesniffer bumped** `^4.0.1` -> `^4.0.2` (CVE-2026-67434,
   OS command injection, high severity; resolved to v4.0.4).

### v4.6 — Diagnostics & Observability (released 2026-08-08)

| Issue | Title | Status |
|-------|-------|--------|
| #145 | ATTR_TRACE_ENABLED connection diagnostics | **Won't-fix** - belongs in consumer middleware (see entity-bundle #168, #173, #174) |
| #146 | ATTR_SLOW_QUERY_MS slow-query detection | **Won't-fix** - belongs in consumer middleware (see entity-bundle #175) |
| #147 | Exception enrichment with raw fbird_errcode()/fbird_errmsg() | **Done** - `getFbirdErrCode()`/`getFbirdErrMsg()` named accessors (commit `c0ccf75`) |

**#145/#146 rationale**: DBAL ships `Doctrine\DBAL\Logging\Middleware` (PSR-3)
for SQL query logging. The driver's `ConnectionWrapper` docblock explicitly
anticipates logging middleware being added downstream. The entity-bundle already
has a `doctrine.middleware` convention with 4 middlewares. Downstream issues
created: entity-bundle #168, #173, #174, #175; amicron-platform #218, #219.

### DBAL 3 series (3.10.x branch) - MAINTENANCE

The `3.10.x` branch is in maintenance mode. Last release: `v3.19.2` (2026-08-08)
(#147 backport: `getFbirdErrCode()`/`getFbirdErrMsg()` named accessors). All
critical fixes are in v3.19.2. No further 3.10.x releases planned unless
critical bugs are found.

### Known issues

1. **Metadata lock hang** (php-firebird #540) - `fbird_commit_ret()` holds
   metadata locks from SELECT cursors, causing DDL to hang after schema
   introspection. Workaround: `gc_collect_cycles()` before DDL. Filed:
   https://github.com/satwareAG/php-firebird/issues/540

2. **DBAL 4.4 -> 5.0 deprecations** - 90 PHPStan baseline entries for
   deprecated methods (`getQuotedName`, `getName`, etc.). Forward-looking;
   will be addressed when DBAL 5.0 is released.

3. **~~DBAL 4.4.4 unreleased~~** - DBAL 4.4.4 released and installed. The
   `detectRenamedIndexes()` test skip in `AbstractComparatorTestCase.php`
   auto-lifted via `version_compare` check (unit tests: 1620 -> 1624, skipped:
   49 -> 48).

### Deferred for future releases

- ~~R1 (BLOB sub_type via fbird_field_info)~~ - **DONE** in commit `25455f4` (v4.5.2)
- ~~#145 (ATTR_TRACE_ENABLED)~~ - **Won't-fix** - belongs in consumer middleware (entity-bundle #168, #173, #174)
- ~~#146 (ATTR_SLOW_QUERY_MS)~~ - **Won't-fix** - belongs in consumer middleware (entity-bundle #175)
- ~~#147 (Exception enrichment)~~ - **Done** in v4.6.0 (commit `c0ccf75`, backported to v3.19.2)
- R2 (per-connection error context) - marginal benefit
- R5/R12 (statement timeout) - `fbird_set_statement_timeout()` available, but DBAL has no per-query timeout concept
- R6 (schema introspection via fbird_list_tables) - breaks DBAL's SQL-string abstraction
- R7 (DecFloat native type) - `fbird_decfloat()` not yet available in ext-firebird
- R8 (fbird_fetch_all) - incompatible with per-row BLOB/charset processing
- R10 (fbird_error_field) - available, no current demand
- R11 (fbird_blob_export) - available, no downstream demand
- Merge 4.4.x -> main - separate decision
- Firebird 6.0 features - deferred to v14

### Spec

See `specs/003-php-firebird-v13-on-dbal4/spec.md` for full design document.
