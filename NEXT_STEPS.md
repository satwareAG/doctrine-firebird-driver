# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-04-07
**Branch:** `3.10.x` (HEAD: 91ea882)
**Extension:** php-firebird v10.6.2 (`ext-firebird: ^10.6`)
**Status:** All 3.10.x phases complete. Zero open issues. DBAL 3 + ORM 3 compatibility fully verified.

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

### Metrics (2026-04-07)

| Metric | Value |
|--------|-------|
| Unit test suite | 1561 tests, OK (18 skipped, 4 incomplete) |
| PHPStan Level 8 | 0 errors |
| `@fbird_*` suppressions | 0 (all removed) |
| Open issues | 0 |
| Gap analysis (#59-#79) | All 21 issues CLOSED |

### Charset Transparency Middleware (COMPLETE)

Spec: `specs/001-charset-transparency-middleware/spec.md` (Status: Implemented)
Implementation: `src/Driver/Firebird/Middleware/Charset*.php` (4 classes)

### php-firebird v10.6.2 Upgrade (COMPLETE - 2026-04-03)

Five commits on `3.10.x`:

1. `chore(deps)`: remove redundant `symfony/polyfill-php82`, bump `ext-firebird: ^10.6`
2. `feat(ci)`: upgrade php-firebird to v10.6.2 in all CI workflows
3. `chore(ci)`: pin GitHub Actions to immutable SHA digests
4. `fix(tests)`: TransactionTest SERIALIZABLE race fixed via `markConnectionNotReusable()`
5. GitHub housekeeping: milestone #14 created, issues #99-#106 triaged, PR #104 closed

### DBAL 3 + ORM 3 Compatibility Audit (COMPLETE - 2026-04-07)

Verified all requirements for PHP 8.2, 8.3, 8.4, and 8.5:

| Area | Status |
|------|--------|
| All DBAL 3 driver interfaces | ✅ Fully implemented |
| ORM 3 integration (QuoteStrategy, BooleanType, Connection, SchemaManager) | ✅ Present |
| PHP 8.4 implicit nullable deprecation | ✅ No issues (DBAL interfaces are untyped) |
| `Compat\Override` polyfill for PHP 8.2 | ✅ Correct |
| CI matrix PHP 8.2/8.3/8.4/8.5 | ✅ All versions covered |
| PHPStan Level 8 | ✅ 0 errors |
| `doctrine/orm` | ✅ 3.6.3 (latest 3.x) |
| `doctrine/dbal` | ✅ 3.10.5 (latest 3.x) |

### Additional Completed Items

- php-firebird v10.3.9 upgrade (#98 - closed)
- isql reconnection workaround (#96 - closed, fixed by v10.3.9)
- Pagination OFFSET/FETCH for Firebird 3.0+ (#93 - closed)

---

## Future: DBAL 4.x Migration (4.4.x branch)

Branch `4.4.x` exists but is not yet started. All 3.10.x stabilization is complete.
Milestone: `4.4.0 - DBAL 4 Migration` (#14) - issues #99-#102 assigned.

### Documented Blockers

From `docs/learnings/2026-03-18-dbal4-initial-migration-blockers.md`:

1. **`getWrappedConnection()` removed** - replaced by `getNativeConnection()` in DBAL 4
2. **`ServerInfoAwareConnection` interface changes** - new method signatures
3. **`AbstractSchemaManager` constructor changes** - different parameter list
4. **`Type::getName()` removal** - must use class-based type identification

### Roadmap

1. **Branch Setup**: Rebase `4.4.x` from current `3.10.x`
2. **Dependency Update**: Require `doctrine/dbal: ^4.1`
3. **API Refactoring**: Address 4 blockers above
4. **CI Matrix**: PHP 8.4/8.5 + Firebird 4/5

See also: `docs/research/dbal4-migration.md`

---

## Reference

- Gap analysis plan: `docs/plans/2026-03-03-dbal3-gap-implementation.md`
- Gap analysis research: `docs/research/2026-03-03-dbal3-gap-analysis.md`
- Code quality audit: `docs/audit-code-quality-2026-03.md`
- DBAL 4.x research: `docs/research/dbal4-migration.md`
- DBAL 4.x blockers: `docs/learnings/2026-03-18-dbal4-initial-migration-blockers.md`
- Charset spec: `specs/001-charset-transparency-middleware/spec.md`
