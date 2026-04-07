# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-04-07
**Branch:** `3.10.x` (HEAD: 06cb4a2)
**Extension:** php-firebird v10.6.2 (`ext-firebird: ^10.6`)
**Status:** All 3.10.x phases complete. Zero open issues on 3.10.x/3.12.x. Active work: `4.4.x` DBAL 4 migration.

---

## Release Inventory (as of 2026-04-07)

| Tag | GitHub Release | Notes |
|-----|---------------|-------|
| v3.12.3 | ✅ Created 2026-04-07 | **GitHub Latest** - Standard SQL Pagination + Docker stability |
| v3.12.2 | ✅ Exists | "Final DBAL 3 Maintenance Release" (superseded by v3.12.3) |
| v3.12.0 | ✅ Exists | Stable release |
| v3.11.0 | ✅ Exists | CharsetMiddleware: transparent Firebird charset conversion |
| v3.10.5 | ✅ Created 2026-04-07 | Maintenance: php-firebird v10.6.2 + CI hardening (non-latest) |
| v3.10.0-v3.10.4 | ✅ Exist | Earlier 3.10.x series |

---

## Completed Work

### Phases 1-4: Quality Improvements (COMPLETE - merged 2026-03-31)

| Phase | Scope | Result |
|-------|-------|--------|
| **1** | Test suite stabilization | BatchTest workarounds, suite configs, clean baseline |
| **2** | Resource type guards | All `@phpstan-assert-if-true` guards verified |
| **2.5** | DBAL-layer bug fixes | Resource registry, ProceduralBatch, SIGSEGV resolution |
| **3** | Gap analysis tests | #65 TransactionTest, #66 DefaultValueTest, #67 ComparatorTest |
| **4** | Merge to 3.10.x | Commit 797421e, issues #65/#66/#67 auto-closed |

### Metrics (2026-04-07, branch 3.10.x)

| Metric | Value |
|--------|-------|
| Unit test suite | 2324-2336 tests PASSED (PHP 8.4, php-firebird v10.6.2) |
| Firebird 3 | ✅ 2324 tests PASSED |
| Firebird 4 | ✅ 2336 tests PASSED |
| Firebird 5 | ✅ 2336 tests PASSED |
| DBAL version | 3.10.5 (latest 3.x) |
| ORM version | 3.6.3 (latest 3.x) |
| php-firebird | v10.6.2 (Docker test image updated) |
| PHPStan Level 8 | 0 errors |
| `@fbird_*` suppressions | 0 (all removed) |
| Open issues (3.10.x/3.12.x) | 0 |
| Gap analysis (#59-#79) | All 21 issues CLOSED |

### Charset Transparency Middleware (COMPLETE)

Spec: `specs/001-charset-transparency-middleware/spec.md` (Status: Implemented)
Implementation: `src/Driver/Firebird/Middleware/Charset*.php` (4 classes)
Released: v3.11.0

### Docker Test Image v10.6.2 (COMPLETE - 2026-04-07)

Commit `0f9f4c9`: Updated `tests/app/Dockerfile` to use php-firebird v10.6.2 (previously pinned to v10.3.9).
Full test suite verified: Firebird 3/4/5 all pass with PHP 8.4 + DBAL 3.10.5 + ORM 3.6.3.

### php-firebird v10.6.2 Upgrade (COMPLETE - 2026-04-03)

Five commits on `3.10.x`:

1. `chore(deps)`: remove redundant `symfony/polyfill-php82`, bump `ext-firebird: ^10.6`
2. `feat(ci)`: upgrade php-firebird to v10.6.2 in all CI workflows
3. `chore(ci)`: pin GitHub Actions to immutable SHA digests
4. `fix(tests)`: TransactionTest SERIALIZABLE race fixed via `markConnectionNotReusable()`
5. GitHub housekeeping: milestone #14 created, issues #99-#106 triaged, PR #104 closed

### DBAL 3 + ORM 3 Compatibility Audit (COMPLETE - 2026-04-07)

All requirements verified for PHP 8.2, 8.3, 8.4, and 8.5.

### Standard SQL Pagination (COMPLETE - released in v3.12.3)

Replaced legacy `ROWS` syntax with standard SQL `OFFSET/FETCH` for Firebird 3/4/5 platforms.
Released 2026-03-19 in v3.12.3.

---

## Active: DBAL 4.x Migration (4.4.x branch)

**Branch:** `4.4.x` (remote: `origin/4.4.x`)
**Milestone:** `4.4.0 - DBAL 4 Migration` (#14)
**Open Issues:** 4 (#99, #100, #101, #102)

### Already Done on 4.4.x (commits 7545c1e, 0ff3f80, f7de7ac)

| Change | Status |
|--------|--------|
| `doctrine/dbal: ^4.4` in composer.json | ✅ Done |
| `bindValue()` return type `void` | ✅ Done |
| `bindParam()` return type `void` | ✅ Done |
| `quote(string $value): string` (removed `$type` arg) | ✅ Done |
| `lastInsertId(): string|int` (removed `false`) | ✅ Done |
| `ParameterType` as enum (not constants) | ✅ Done |
| `getNativeConnection()` added to `Connection.php` | ✅ Done |

### Remaining Work (4 Open Issues)

**#100 - FATAL BLOCKER** - `ServerInfoAwareConnection` / `getDatabasePlatform()` signature:
- DBAL 4 removed `ServerInfoAwareConnection` interface entirely
- `Driver::getDatabasePlatform()` now requires `ServerVersionProvider $versionProvider` parameter
- `FirebirdDriver::getDatabasePlatform()` on 4.4.x still has old no-argument signature
- Fix: Add `ServerVersionProvider $versionProvider` param; use it to detect version instead of service attach

**#99** - `getWrappedConnection()` in FunctionalTestCase:
- `tests/Test/FunctionalTestCase.php` still has `while (method_exists($conn, 'getWrappedConnection'))` loops
- DBAL 4 removes `getWrappedConnection()` entirely - the fallback chain breaks
- Fix: Replace with clean `getNativeConnection()` + type assertion pattern

**#101** - `AbstractSchemaManager` constructor changes:
- `FirebirdSchemaManager` has no custom `__construct`, relies on parent
- DBAL 4's `AbstractSchemaManager` constructor signature changed
- Fix: Verify parent signature; add explicit constructor if needed

**#102** - `Type::getName()` removal:
- `FirebirdSchemaManager` imports `use Doctrine\DBAL\Types\Type;`
- DBAL 4 removes `Type::getName()` - must use class-based identification (`instanceof` / `::class`)
- Fix: Audit all `->getName()` calls in SchemaManager; replace with `instanceof` checks

### Recommended Execution Order

```bash
# 1. Switch to 4.4.x
git fetch && git checkout 4.4.x

# 2. Install DBAL 4 dependencies
composer install

# 3. Get exact error list
vendor/bin/phpstan analyse --error-format=table 2>&1 | head -80

# 4. Fix in order: #100 (fatal) -> #99 (tests) -> #101 (schema) -> #102 (types)

# 5. Run tests (Firebird 4 + 5; DBAL 4 drops Firebird 3 support)
bash tests/phpunit.sh firebird4
bash tests/phpunit.sh firebird5

# 6. Release v4.4.0
```

---

## Reference

- Gap analysis plan: `docs/plans/2026-03-03-dbal3-gap-implementation.md`
- Gap analysis research: `docs/research/2026-03-03-dbal3-gap-analysis.md`
- Code quality audit: `docs/audit-code-quality-2026-03.md`
- DBAL 4.x research: `docs/research/dbal4-migration.md`
- DBAL 4.x blockers: `docs/learnings/2026-03-18-dbal4-initial-migration-blockers.md`
- Charset spec: `specs/001-charset-transparency-middleware/spec.md`
