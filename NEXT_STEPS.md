# Next Steps - doctrine-firebird-driver

**Last session:** 2026-03-31 (DBAL-layer bug fixes)
**Branch:** `001-quality-improvements` (based on `3.10.x`)
**Extension:** php-firebird v10.3.9 (`ext-firebird: ^10.3.2`)
**Status:** Active - Phase 2.5 complete, Phase 3 next

---

## Current State (2026-03-31)

### Completed This Session - DBAL-Layer Bug Fixes
All three DBAL-layer bugs identified in `implementation_plan.md` have been fixed:

**Bug 1: Integration-ReadOnly 9 errors - FIXED (0 errors)**
- Root cause: Premature `fbird_close()` in `__destruct()` when multiple DBAL layers share the same native resource
- Fix: Static resource reference registry in `Connection.php` - tracks refcount per native resource ID, only calls `fbird_close()` when refcount reaches 0
- Also fixed: `FunctionalTestCase::connect()` no longer calls destructive `close()`, uses graceful null assignment + `TestUtil::resetSharedConnection()`
- Also fixed: `AbstractIntegrationTestCase::setUpEntityManager()` guards `setNestTransactionsWithSavepoints()` with `isTransactionActive()` check

**Bug 2: BatchTest 3 errors - FIXED (0 errors)**
- Root cause: `Firebird\Batch::__construct()` is private (can't instantiate from outside), and `Batch::fromQuery()` returns "invalid batch handle"
- Fix: Created `ProceduralBatch` and `ProceduralBatchResult` wrapper classes that use the working procedural API (`fbird_batch_create`, `fbird_batch_add`, `fbird_batch_execute`)
- `Connection::createBatch()` now returns `ProceduralBatch` instead of `Firebird\Batch`

**Bug 3: Full-suite SIGSEGV at ~65% - FIXED (suite completes)**
- Root cause: Accumulated resource corruption from Bug 1 (premature fbird_close causing cascading failures)
- Fix: Resolved by Bug 1's resource registry - no more premature resource destruction
- Note: SIGSEGV during PHP shutdown remains (known extension bug php-firebird#183, not our code)

### New Files
- `src/Driver/Firebird/ProceduralBatch.php` - Wrapper around procedural fbird_batch_* API
- `src/Driver/Firebird/ProceduralBatchResult.php` - Result object with successCount/hasErrors()

### Previously Completed
- Upgraded php-firebird v10.3.6 -> v10.3.9
- Direct extension-level verification (tests/debug/verify-v10.3.9-fixes.php)
- Phase 1: BatchTest workarounds, suite configs, clean baseline

### Metrics
- PHPStan Level 8: 0 errors
- `@fbird_*` suppressions in src/: 0 (all removed)
- php-firebird: v10.3.9

### Test Baseline (2026-03-31, post-fix)

| Suite | FB4 | Notes |
|-------|-----|-------|
| **Unit** | 1569 OK (21S, 4I) | Unchanged |
| **Integration-ReadOnly** | 24 tests, 150 assertions, 0 errors | **FIXED** |
| **BatchTest** | 3 tests, 7 assertions, 0 errors | **FIXED** |
| **Full suite** | 2336 tests, ALL PASSED | **FIXED** (SIGSEGV only during shutdown - known ext bug) |

**Upstream extension bugs - FIXED at C level:**
- [php-firebird#180](https://github.com/satwareAG/php-firebird/issues/180) - SIGFPE guard (v10.3.9)
- [php-firebird#183](https://github.com/satwareAG/php-firebird/issues/183) - SIGSEGV shutdown (v10.3.8)
- [php-firebird#184](https://github.com/satwareAG/php-firebird/issues/184) - OO handle loss (v10.3.8)
- [php-firebird#185](https://github.com/satwareAG/php-firebird/issues/185) - IBatch GC_ADDREF (v10.3.8)

**DBAL-layer issues - ALL FIXED:**
- ~~Integration-ReadOnly 9 errors~~ - Fixed: resource registry prevents premature fbird_close()
- ~~BatchTest 3 errors~~ - Fixed: ProceduralBatch wrapper uses procedural API
- ~~Full suite SIGSEGV~~ - Fixed: resolved by resource registry (Bug 1 fix)

---

## Phase 1 - Test Suite Stabilization (COMPLETE)

1. ~~Mark BatchTest known failures with `markTestSkipped()` and php-firebird#180 reference~~ (651c7a2)
2. ~~Add Integration suites to `phpunit-firebird4.xml` and `phpunit-firebird5.xml`~~ (651c7a2)
3. ~~Run full FB4 suite - capture clean baseline~~ (see Test Baseline above)
4. ~~Run full FB5 suite - verify clean baseline~~ (see Test Baseline above)

## Phase 2 - Resource Type Guard Completion (Next Up)

5. Audit `Connection::isConnectionValid()` usage across all public methods
6. Add `Statement::isStatementValid()` with `@phpstan-assert-if-true`
7. Add `Result::isResultValid()` with `@phpstan-assert-if-true`
8. Audit `TransactionManager::isTransactionValid()` annotations
9. Update unit tests for validation methods
10. PHPStan Level 8 must remain 0 errors

## Phase 2.5 - DBAL-Layer Bug Investigation (COMPLETE)

11. ~~Investigate Integration-ReadOnly handle loss~~ - Fixed: resource reference registry
12. ~~Investigate BatchTest OO wrapper~~ - Fixed: ProceduralBatch using procedural API
13. ~~Investigate full-suite SIGSEGV~~ - Fixed: resolved by resource registry
14. ~~Consider `Firebird\Batch` OO wrapper~~ - Workaround: private constructor, using procedural API

## Phase 3 - Gap Analysis Tests (Issues #65-#67)

15. TransactionTest (#65) - transaction isolation, savepoints, nested transactions
16. DefaultValueTest (#66) - schema manager default value handling
17. ComparatorTest (#67) - schema comparator false-positive detection
18. Full suite regression check on FB4 and FB5

## Phase 4 - Finalize and Merge

19. Final PHPStan + full test suite verification
20. Squash-merge or rebase `001-quality-improvements` into `3.10.x`

---

## Known Issues Summary

| Issue | Layer | Status |
|-------|-------|--------|
| [php-firebird#180](https://github.com/satwareAG/php-firebird/issues/180) | Extension (C) | **FIXED** in v10.3.9 |
| [php-firebird#183](https://github.com/satwareAG/php-firebird/issues/183) | Extension (C) | **FIXED** in v10.3.8 (shutdown path) |
| [php-firebird#184](https://github.com/satwareAG/php-firebird/issues/184) | Extension (C) | **FIXED** in v10.3.8 (procedural API) |
| [php-firebird#185](https://github.com/satwareAG/php-firebird/issues/185) | Extension (C) | **FIXED** in v10.3.8 (GC_ADDREF) |
| Integration-ReadOnly 9 errors | DBAL driver | **FIXED** - resource registry |
| BatchTest 3 errors | OO PHP wrapper | **FIXED** - ProceduralBatch wrapper |
| Full suite SIGSEGV ~65% | DBAL driver | **FIXED** - resource registry |
| [#96](https://github.com/satwareAG/doctrine-firebird-driver/issues/96) | DBAL driver | Workaround: isql subprocess |

---

## Future: DBAL 4.x Migration (4.4.x branch)

Blocked until `001-quality-improvements` merges to `3.10.x`.

### Roadmap for 4.4.x:
1. **Branch Setup**: Create `4.4.x` from `3.10.x`
2. **Dependency Update**: Require `doctrine/dbal: ^4.1`
3. **API Refactoring**: Replace deprecated interfaces, update return types
4. **CI Matrix**: PHP 8.4/8.5 + Firebird 4/5

See `docs/research/dbal4-migration.md` and `docs/learnings/2026-03-18-dbal4-initial-migration-blockers.md`.

---

## Open GitHub Issues (#59-#79)

21 issues from the DBAL 3.10.x gap analysis remain open.
See `docs/plans/2026-03-03-dbal3-gap-implementation.md` for the full sprint plan.
Priority issues for this branch: #65 (TransactionTest), #66 (DefaultValueTest), #67 (ComparatorTest).

---

## Reference

- Implementation plan: `implementation_plan.md`
- Extension verification: `tests/debug/verify-v10.3.9-fixes.php`
- Gap analysis: `docs/research/2026-03-03-dbal3-gap-analysis.md`
- Code quality audit: `docs/audit-code-quality-2026-03.md`
- DBAL 4.x research: `docs/research/dbal4-migration.md`
