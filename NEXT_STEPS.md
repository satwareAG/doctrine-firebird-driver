# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-07-06
**Branch:** `integration/php-firebird-12.0.0` (from `3.10.x`)
**Extension:** php-firebird v12.0.0-rc.11 + commit `d4d3851` (issue #310 fix)
**Status:** v3.14.0 integration complete. Full matrix green. Ready for php-firebird v12.0.0 stable.

---

## Current State

### Test Results (full matrix: PHP 8.2-8.5 × Firebird 3.0/4.0/5.0)

| PHP | FB 3.0 | FB 4.0 | FB 5.0 |
|-----|--------|--------|--------|
| 8.2 | 2320 (0 err, 138 skip) | 2320 (0 err, 134 skip) | 2320 (0 err, 134 skip) |
| 8.3 | 2320 (0 err, 138 skip) | 2320 (0 err, 134 skip) | 2320 (0 err, 134 skip) |
| 8.4 | 2320 (0 err, 138 skip) | 2320 (0 err, 134 skip) | 2320 (0 err, 134 skip) |
| 8.5 | 2320 (0 err, 138 skip) | 2320 (0 err, 134 skip) | 2320 (0 err, 134 skip) |

**Total:** 12 combos × 2320 tests = 27,840 test runs, 0 errors, 0 failures.

### Quality Gates

| Gate | Result |
|------|--------|
| PHPStan | 0 errors |
| Psalm | 0 errors (68-line baseline) |

---

## Completed Work

### php-firebird v12.0.0-rc.11 Integration (2026-07-06)

- Upgraded `ext-firebird` constraint from `^11.1` to `^12.0`
- Upgraded `satwareag/php-firebird-stubs` from `^11.1` to `^12.0.0-rc.11@rc`
- Pinned Docker test image to commit `d4d3851` (post-rc.11, includes #310 fix)
- Updated CI workflows, Dockerfile, Windows DLL download patterns for v12.0.0-rc.11
- Updated docblock return types for v12 stubs accuracy (`resource` -> `Firebird\*` objects)
- Typed `ProceduralBatch::$batchHandle` as `Firebird\BatchHandle` (was `mixed`)
- Added `FirebirdSchemaManager::getCurrentSequenceValue()` for Firebird-specific sequence value reading
- Removed `@phpstan-ignore argument.type` on `fbird_trans_start` (rc.11 fixed arginfo)

### FB4/FB5 Fixes (2026-07-06)

- **`Connection::queryInTransaction()`**: Added `Firebird\TransactionManager` handling
  (from `TBuilder::start()`). Extracts `Firebird\Transaction` via `getResource()`.
  Fixes "must be a Firebird transaction resource" TypeError on FB4/FB5.
- **`Connection::createBatch()` / `executeBatch()`**: Widened `$transaction` parameter
  type to `TransactionManager|FirebirdTransactionManager|null`.
- **`BatchTest::setUp()`**: Removed redundant `createBatch('SELECT 1...')` probe
  that failed because IBatch requires parameterized statements. `function_exists`
  is sufficient (C function registered only when `FB_API_VER >= 40`).
- **php-firebird #310**: `TransactionManager::__destruct` THROW mode fix
  (replaced `@fbird_rollback()` with try/catch in php-firebird commit `d4d3851`).

### Test Quality Improvements (2026-07-06)

- Fixed test fixture pollution in `ExceptionConverterTest` (setUp/tearDown cleanup)
- Fixed `installFirebirdDatabase()` seeding: `UPDATE OR INSERT` with explicit IDs
  + `ALTER TABLE ... RESTART WITH` for identity generators
- Added `dropTableIfExists()` cleanup to all Integration-Write tests that create tables
- Added `dropSequenceIfExists()` helper and cleanup to functional schema tests
- Changed `stopOnDefect` to `false` in `phpunit.xml`
- Removed stale `@todo` from `FirebirdSchemaManager`
- Deleted deprecated `ReadOnlyIntegrationTestCase`, `ConfigurableLikeCastLengthTest`
- Replaced `bindParam()` with `bindValue()` in functional tests (kept 2 by-ref tests)
- Implemented 4 previously incomplete `testQuotesAlterTableChangeColumnLength` tests
- Reduced Psalm baseline from 167 to 68 lines

### php-firebird v11.1.0 Integration (v3.13.0, 2026-07-02)

- Upgraded from `^10.6` to `^11.1`
- Migrated from `is_resource()` checks to `instanceof` assertions
- Fixed SIGSEGV exit-code handling in CI

---

## Pending: php-firebird v12.0.0 Stable Release

6 issues opened on `satwareAG/php-firebird` (#305-#310) — ALL FIXED in rc.11 + `d4d3851`.

Once php-firebird v12.0.0 stable is released:
- Update `composer.json` constraint from `^12.0.0-rc.11@rc` to `^12.0`
- Update Dockerfile from commit `d4d3851` to `v12.0.0` tag
- Update CI workflows to `v12.0.0`
- Re-verify full test suite
- Merge `integration/php-firebird-12.0.0` into `3.10.x`
- Tag `v3.14.0`, create GitHub release
- Update downstream consumers:
  - `satag-amicron-entity-bundle`: bump to `^3.14.0`
  - `amicron-platform`: bump to `^3.14.0`

---

## Reference

- php-firebird CHANGELOG: `~/external/php-firebird/CHANGELOG.md`
- Gap analysis plan: `docs/plans/2026-03-03-dbal3-gap-implementation.md`
- Charset spec: `specs/001-charset-transparency-middleware/spec.md` (Status: Implemented)
- DBAL 4.x research: `docs/research/dbal4-migration.md`
- DBAL 4.x work: `4.4.x` branch (dormant, needs v12 upgrade)
