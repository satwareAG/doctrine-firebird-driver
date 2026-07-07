# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-07-07
**Branch:** `integration/php-firebird-12.0.0` (from `3.10.x`)
**Extension:** php-firebird v12.0.0 stable (commit `ae40ef1`)
**Status:** v3.14.0 release ready. All tests green. Merging to `3.10.x`.

---

## Current State

### Test Results (PHP 8.4 x Firebird 3.0/4.0/5.0)

| FB | Tests | Skipped | Errors | Failures | Incomplete |
|----|-------|---------|--------|----------|------------|
| 3.0 | 2319 | 139 | 0 | 0 | 0 |
| 4.0 | 2319 | 135 | 0 | 0 | 0 |
| 5.0 | 2319 | 135 | 0 | 0 | 0 |

Full matrix (PHP 8.2-8.5 x FB 3.0/4.0/5.0 = 12 combos) verified at commit `566cda4`.
PHP 8.4 x FB 3/4/5 re-verified after v12.0.0 stable upgrade and cleanup fixes.

### Quality Gates

| Gate | Result |
|------|--------|
| PHPCS | 0 errors |
| PHPStan Level 8 | 0 errors |
| Psalm | 0 errors (68-line baseline) |

---

## Completed Work

### php-firebird v12.0.0 Stable Integration (2026-07-07)

- Upgraded `ext-firebird` constraint from `^11.1` to `^12.0`
- Upgraded `satwareag/php-firebird-stubs` from `^11.1` to `^12.0.0` (stable, no `@rc`)
- Pinned Docker test image to commit `ae40ef1` (v12.0.0 stable)
- Updated CI workflows to clone `v12.0.0` tag, cache key v9
- Removed SIGSEGV exit 139/134 workarounds (root cause fixed in v12.0.0 via #311)
- Added test script auto-cleanup (`cleanup_all()`, `cleanup_fb_version()`)
- Eliminated all incomplete tests (was 2, now 0)

### php-firebird v12.0.0-rc.11 Initial Integration (2026-07-06)

- Updated docblock return types for v12 stubs accuracy (`resource` -> `Firebird\*` objects)
- Typed `ProceduralBatch::$batchHandle` as `Firebird\BatchHandle` (was `mixed`)
- Added `FirebirdSchemaManager::getCurrentSequenceValue()` for Firebird-specific sequence value reading
- Removed `@phpstan-ignore argument.type` on `fbird_trans_start` (v12 fixed arginfo)
- 7 issues opened on `satwareAG/php-firebird` (#305-#311) - ALL FIXED in v12.0.0

### FB4/FB5 Fixes (2026-07-06)

- **`Connection::queryInTransaction()`**: Added `Firebird\TransactionManager` handling
  (from `TBuilder::start()`). Extracts `Firebird\Transaction` via `getResource()`.
  Fixes "must be a Firebird transaction resource" TypeError on FB4/FB5.
- **`Connection::createBatch()` / `executeBatch()`**: Widened `$transaction` parameter
  type to `TransactionManager|FirebirdTransactionManager|null`.
- **`BatchTest::setUp()`**: Removed redundant `createBatch('SELECT 1...')` probe
  that failed because IBatch requires parameterized statements.
- **php-firebird #310**: `TransactionManager::__destruct` THROW mode fix.

### Test Quality Improvements (2026-07-06)

- Fixed test fixture pollution in `ExceptionConverterTest` (setUp/tearDown cleanup)
- Fixed `installFirebirdDatabase()` seeding: `UPDATE OR INSERT` with explicit IDs
  + `ALTER TABLE ... RESTART WITH` for identity generators
- Added `dropTableIfExists()` cleanup to all Integration-Write tests that create tables
- Added `dropSequenceIfExists()` helper and cleanup to functional schema tests
- Deleted deprecated `ReadOnlyIntegrationTestCase`, `ConfigurableLikeCastLengthTest`
- Replaced `bindParam()` with `bindValue()` in functional tests (kept 2 by-ref tests)
- Implemented 4 previously incomplete `testQuotesAlterTableChangeColumnLength` tests
- Reduced Psalm baseline from 167 to 68 lines

### Test Script Consolidation (2026-07-06)

- Rewrote `tests/run-matrix.sh` as primary test runner
- Created `tests/lib/common.sh` (shared helpers: colors, print, cleanup)
- Deduplicated `docker-cqc.sh` and `cqc.sh` to source `common.sh`
- Deleted `phpunit.sh`, `phpunit-lowest-versions.sh`, per-version phpunit XMLs
- Added `cleanup_all()` and `cleanup_fb_version()` for automatic volume cleanup
- Healthcheck-based container readiness polling (replaces fixed 5s sleep)
- Volume removal retry loop (Docker may briefly hold references)

### php-firebird v11.1.0 Integration (v3.13.0, 2026-07-02)

- Upgraded from `^10.6` to `^11.1`
- Migrated from `is_resource()` checks to `instanceof` assertions
- Fixed SIGSEGV exit-code handling in CI

---

## Release Plan

php-firebird v12.0.0 stable released on 2026-07-07. All integration work is complete.

- [x] Update `composer.json` constraint to `^12.0.0` (stable)
- [x] Update Dockerfile to v12.0.0 commit `ae40ef1`
- [x] Update CI workflows to `v12.0.0` tag
- [x] Re-verify full test suite (0 errors, 0 failures, 0 incomplete)
- [x] Remove SIGSEGV workarounds
- [x] Add test script auto-cleanup
- [ ] Merge `integration/php-firebird-12.0.0` into `3.10.x` via PR
- [ ] Tag `v3.14.0`, create GitHub release
- [ ] Update downstream consumers:
  - `satag-amicron-entity-bundle`: bump to `^3.14.0`, update `php-firebird` to `^12.0`
  - `amicron-platform`: bump to `^3.14.0`, update `php-firebird` to `^12.0`

---

## Reference

- php-firebird v12.0.0 release: https://github.com/satwareAG/php-firebird/releases/tag/v12.0.0
- Gap analysis plan: `docs/plans/2026-03-03-dbal3-gap-implementation.md`
- Charset spec: `specs/001-charset-transparency-middleware/spec.md` (Status: Implemented)
- DBAL 4.x research: `docs/research/dbal4-migration.md`
- DBAL 4.x work: `4.4.x` branch (dormant, needs v12 upgrade)
