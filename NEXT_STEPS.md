# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-03-31
**Branch:** `3.10.x` (HEAD: 797421e)
**Extension:** php-firebird v10.3.9 (`ext-firebird: ^10.3.2`)
**Status:** All phases complete. Zero open issues.

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

### Metrics (2026-03-31)

| Metric | Value |
|--------|-------|
| Full suite | 2336 tests, ALL PASSED (FB4) |
| PHPStan Level 8 | 0 errors |
| `@fbird_*` suppressions | 0 (all removed) |
| Open issues | 0 |
| Gap analysis (#59-#79) | All 21 issues CLOSED |

### Charset Transparency Middleware (COMPLETE)

Spec: `specs/001-charset-transparency-middleware/spec.md` (Status: Implemented)
Implementation: `src/Driver/Firebird/Middleware/Charset*.php` (4 classes)

### Additional Completed Items

- php-firebird v10.3.9 upgrade (#98 - closed)
- isql reconnection workaround (#96 - closed, fixed by v10.3.9)
- Pagination OFFSET/FETCH for Firebird 3.0+ (#93 - closed)

---

## Future: DBAL 4.x Migration (4.4.x branch)

Branch `4.4.x` exists but is not yet started. All 3.10.x stabilization is complete.

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
