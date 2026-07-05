# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-07-05
**Branch:** `integration/php-firebird-12.0.0` (from `3.10.x`)
**Extension:** php-firebird v12.0.0-rc.10 (`ext-firebird: ^12.0`)
**Status:** v3.14.0 integration complete. All tests green. Ready for php-firebird v12.0.0 stable.

---

## Current State

### Test Results (php-firebird v12.0.0-rc.10, PHP 8.5, Firebird 5.0)

| Suite | Tests | Result |
|-------|-------|--------|
| Unit | 1561 | OK (18 skipped, 4 incomplete - pre-existing) |
| Integration-ReadOnly | 24 | OK |
| Integration-Write | 97 | OK |
| Functional | 644 | OK (117 skipped, 2 incomplete - pre-existing) |
| **Total** | **2326** | **0 errors, 0 failures** |

### Quality Gates

| Gate | Result |
|------|--------|
| PHPStan | 0 errors |
| Psalm | 0 errors |
| PHPCS | 0 errors |

---

## Completed Work

### php-firebird v12.0.0-rc.10 Integration (2026-07-05)

- Upgraded `ext-firebird` constraint from `^11.1` to `^12.0`
- Upgraded `satwareag/php-firebird-stubs` from `^11.1` to `^12.0.0-rc.10@rc`
- Updated CI workflows, Dockerfile, Windows DLL download patterns for v12.0.0-rc.10
- Updated docblock return types for v12 stubs accuracy (`resource` -> `Firebird\*` objects)
- Typed `ProceduralBatch::$batchHandle` as `Firebird\BatchHandle` (was `mixed`)
- Added `FirebirdSchemaManager::getCurrentSequenceValue()` for Firebird-specific sequence value reading
- Fixed test fixture pollution in `ExceptionConverterTest` (setUp/tearDown cleanup)
- Fixed `installFirebirdDatabase()` seeding to use `UPDATE OR INSERT` with explicit IDs
- Fixed identity generator reset using `ALTER TABLE ... RESTART WITH` (not `SET GENERATOR`)
- Added `dropTableIfExists()` cleanup to all Integration-Write tests that create tables
- Changed `stopOnDefect` to `false` in phpunit.xml for better error visibility
- Marked Phase 2.5 plan as complete
- Removed stale `@todo` from `FirebirdSchemaManager`

### php-firebird v11.1.0 Integration (v3.13.0, 2026-07-02)

- Upgraded from `^10.6` to `^11.1`
- Migrated from `is_resource()` checks to `instanceof` assertions
- Fixed SIGSEGV exit-code handling in CI

---

## Pending: php-firebird v12.0.0 Stable Release

5 issues opened on `satwareAG/php-firebird` with findings from deep code inspection:

1. **THROW mode bypass**: `_php_fbird_prepare` and `fbird_batch_*` use `php_error_docref` instead of `_php_fbird_module_error`
2. **C arginfo return types**: `MAY_BE_RESOURCE` but runtime returns `Firebird\*` objects
3. **C arginfo parameter types**: `IS_STRING` but functions accept resource/object
4. **`fbird_get_client_version`**: returns `float` but arginfo+stubs say `string`
5. **`Firebird\BatchHandle`**: opaque marker with no methods

Once php-firebird v12.0.0 stable is released:
- Update `composer.json` constraint from `^12.0.0-rc.10@rc` to `^12.0`
- Update Dockerfile/CI to `v12.0.0` tag
- Re-verify full test suite
- Merge `integration/php-firebird-12.0.0` into `3.10.x`
- Tag `v3.14.0`
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
