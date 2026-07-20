# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-07-20
**Branch:** `4.4.x` | **Release:** `v4.5.1` (2026-07-20) | **PHP:** 8.2-8.5 | **Firebird:** 3.0/4.0/5.0
**Extension:** php-firebird v13.0.0 | **DBAL:** 4.4.3

---

## Current State

### v4.5.1 — Test coverage, bug fixes, and CI fixes

**Status:** Released 2026-07-20. All CI green.

The `4.4.x` branch is the active release line, shipping on DBAL 4.4.x with
php-firebird v13.0.0. The `3.10.x` branch is in maintenance mode (DBAL 3.x).

### Test Results

| Suite | Tests | Errors | Failures | Skipped |
|-------|-------|--------|----------|---------|
| Unit | 1620 | 0 | 0 | 49 |
| Functional FB3 | 575 | 0 | 0 | 59 |
| Functional FB4 | 575 | 0 | 0 | 55 |
| Functional FB5 | 575 | 0 | 0 | 55 |
| Integration-ReadOnly | 24 | 0 | 0 | 0 |
| Integration-Write | 96 | 0 | 0 | 3 |

### Quality Gates

| Gate | Result |
|------|--------|
| PHPStan Level 8 | 0 errors (90 baselined DBAL 4.4->5.0 deprecation warnings) |
| Psalm | 0 errors (112 baselined pre-existing issues) |
| PHP_CodeSniffer | 0 errors |
| ext-firebird | v13.0.0 loaded |
| DBAL | 4.4.3 installed |
| Stubs | v13.0.0 |
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
10. **Psalm baseline regenerated** - 112 pre-existing errors captured.
11. **Windows CI updated** to php-firebird v13.0.0 DLL (was v12.0.0-rc.11).
12. **4 Dependabot PRs merged** (actions/checkout, codeql-action, codecov).

### DBAL 3 series (3.10.x branch) - MAINTENANCE

The `3.10.x` branch is in maintenance mode. Last release: `v3.19.0` (2026-07-19).
All critical fixes are in v3.19.0. No further 3.10.x releases planned unless
critical bugs are found.

### Known issues

1. **Metadata lock hang** (php-firebird #540) - `fbird_commit_ret()` holds
   metadata locks from SELECT cursors, causing DDL to hang after schema
   introspection. Workaround: `gc_collect_cycles()` before DDL. Filed:
   https://github.com/satwareAG/php-firebird/issues/540

2. **DBAL 4.4 -> 5.0 deprecations** - 90 PHPStan baseline entries for
   deprecated methods (`getQuotedName`, `getName`, etc.). Forward-looking;
   will be addressed when DBAL 5.0 is released.

3. **DBAL 4.4.4 unreleased** - contains `dfcc457cb` fix for
   `detectRenamedIndexes()` undefined array key bug. Our test skip in
   `AbstractComparatorTestCase.php` auto-lifts when 4.4.4 lands
   (version_compare check).

### Deferred for future releases

- R1 (BLOB sub_type via fbird_field_info) - needs middleware refactor
- R5-R7 (statement timeout, schema introspection, DecFloat) - medium effort
- R10-R12 (error_field, blob_export, per-stmt timeout) - low ROI
- Merge 4.4.x -> main - separate decision
- Firebird 6.0 features - deferred to v14

### Spec

See `specs/003-php-firebird-v13-on-dbal4/spec.md` for full design document.
