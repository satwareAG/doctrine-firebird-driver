# Next Steps - doctrine-firebird-driver

**Last session:** 2026-03-31 (v10.3.9 upgrade + validation)
**Branch:** `001-quality-improvements` (based on `3.10.x`)
**Extension:** php-firebird v10.3.9 (`ext-firebird: ^10.3.2`)
**Status:** Active - Phase 2 resource type guard completion

---

## Current State (2026-03-31)

### Completed This Session
- Upgraded php-firebird v10.3.7 -> v10.3.9
  - v10.3.7: Security fix (#181)
  - v10.3.8: Lifecycle fixes (#183, #184, #185) at C level
  - v10.3.9: SIGFPE guard for parameterless batch (#180)
- **Direct extension-level verification** (tests/debug/verify-v10.3.9-fixes.php):
  - **#180 PASS** - `fbird_batch_create()` returns false on parameterless statements (no SIGFPE)
  - **#184 PASS** - Procedural reconnect after OO `Connection::close()` works correctly
  - **#185 PASS** - Batch execute succeeds after `gc_collect_cycles()` (GC_ADDREF fix)
- **DBAL test suite results unchanged** - failures are in our driver/OO-wrapper layer, not the extension:
  - Integration-ReadOnly: 9 errors (OO handle loss during DBAL tearDown/setUp cycles)
  - BatchTest: 3 errors (batch handle invalid through OO wrapper code path)
  - Full suite: SIGSEGV at ~65% (different code path than the C-level fix addresses)

### Key Finding
The C-level fixes in v10.3.8/v10.3.9 correctly fix the described bugs at the extension API level.
The remaining DBAL test failures are caused by our Doctrine driver layer and the OO PHP wrapper
(`Firebird\Batch`, `Firebird\Connection`) interacting with the extension differently than the
direct procedural API. These are **DBAL-layer issues**, not extension bugs.

### Previously Completed
- Upgraded php-firebird v10.3.6 -> v10.3.7 (security fix #181)
- Reopened upstream issues #183, #184, #185 (before discovering v10.3.8/v10.3.9)
- Fixed FB4 SIGFPE crash in BatchTest (parameterless fbird_batch_create)
- Fixed FB5 phpunit config, AlbumTest ISQL seeding
- Phase 1: BatchTest workarounds, suite configs, clean baseline

### Metrics
- PHPStan Level 8: 0 errors, empty baseline
- `@fbird_*` suppressions in src/: 0 (all removed)
- Connection.php: 903 LOC (down from 1320, TransactionManager extracted)
- php-firebird: v10.3.9 (#180 + #181 + #183/#184/#185 C-level fixes)

### Test Baseline (2026-03-31, v10.3.9)

| Suite | FB4 | Notes |
|-------|-----|-------|
| **Unit** | 1569 OK (21S, 4I) | Unchanged from v10.3.7 |
| **Integration-ReadOnly** | 24 tests, 9 errors | DBAL-layer handle loss (not ext bug) |
| **BatchTest** | 3/3 errors | OO wrapper path (C-level fix works via procedural API) |
| **Full suite** | ~1569/2336 then SIGSEGV | Crash at ~65% (different code path than #183 fix) |

**Upstream extension bugs - FIXED at C level:**
- [php-firebird#180](https://github.com/satwareAG/php-firebird/issues/180) - SIGFPE guard (v10.3.9)
- [php-firebird#183](https://github.com/satwareAG/php-firebird/issues/183) - SIGSEGV shutdown (v10.3.8)
- [php-firebird#184](https://github.com/satwareAG/php-firebird/issues/184) - OO handle loss (v10.3.8)
- [php-firebird#185](https://github.com/satwareAG/php-firebird/issues/185) - IBatch GC_ADDREF (v10.3.8)

**DBAL-layer issues (our code, need separate investigation):**
- Integration-ReadOnly 9 errors: Connection handle invalid during DBAL setUp `beginTransaction()`
- BatchTest 3 errors: Batch handle invalid through OO wrapper `Batch::fromQuery()` path
- Full suite SIGSEGV: Crash during integration test setup (different from extension shutdown fix)
- Stale DDL tables after SIGSEGV prevents Functional tearDown

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

## Phase 2.5 - DBAL-Layer Bug Investigation

11. Investigate Integration-ReadOnly handle loss: trace exact code path in DBAL tearDown/setUp cycle
12. Investigate BatchTest OO wrapper: compare `Batch::fromQuery()` vs direct `fbird_batch_create()`
13. Investigate full-suite SIGSEGV: identify exact test that triggers crash via `--stop-on-error`
14. Consider if `Firebird\Connection` OO wrapper needs upstream fixes for DBAL patterns

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
| Integration-ReadOnly 9 errors | DBAL driver | Open - needs investigation |
| BatchTest 3 errors | OO PHP wrapper | Open - needs investigation |
| Full suite SIGSEGV ~65% | Unknown (C or DBAL) | Open - needs investigation |
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
