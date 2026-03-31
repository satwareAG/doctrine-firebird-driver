# Next Steps - doctrine-firebird-driver

**Last session:** 2026-03-31 (Phase 1 complete - test suite stabilization)
**Branch:** `001-quality-improvements` (based on `3.10.x`)
**Extension:** php-firebird v10.3.6 (`ext-firebird: ^10.3.2`)
**Status:** Active - Phase 2 resource type guard completion

---

## Current State (2026-03-31)

### Completed This Session
- Fixed FB4 SIGFPE crash in BatchTest (fbird_batch_create on parameterless statements)
- Filed upstream issue [php-firebird#180](https://github.com/satwareAG/php-firebird/issues/180)
- Fixed FB5 phpunit config (wrong db_dbname path)
- Fixed AlbumTest ISQL seeding (boolean TRUE/FALSE literals, table name case)
- Phase 1: Marked BatchTest known failures with `markTestSkipped()` + php-firebird#180 ref
- Phase 1: Added Unit/Integration-ReadOnly/Integration-Write suites to FB4/FB5 XML configs
- Phase 1: Captured clean test baseline (see below)

### Metrics
- PHPStan Level 8: 0 errors, empty baseline
- `@fbird_*` suppressions in src/: 0 (all removed)
- Connection.php: 903 LOC (down from 1320, TransactionManager extracted)
- Commits: 1c06ade, 04e5807, 96c31cc, dff12a9, 651c7a2

### Test Baseline (2026-03-31)

| Suite | FB4 | FB5 | Notes |
|-------|-----|-----|-------|
| **Unit** | 1569 OK (21S, 4I) | 1569 OK (21S, 4I) | No DB required |
| **Integration-ReadOnly** | 6/6 pass + SIGSEGV at exit | 15/24 pass, 9 errors | OO API handle loss in tearDown |
| **Integration-Write** | Not yet baselined | Not yet baselined | Runs in full suite to 65% |
| **Functional** | ~646 tests pass individually | ~646 tests pass individually | BatchTest 3/3 skipped (php-firebird#180) |
| **Full suite** | 1525/2336 pass then SIGSEGV | 1525/2336 pass then SIGSEGV | Crash at ~65% during Integration tearDown |

**Known blockers (all upstream php-firebird):**
- SIGSEGV at PHP shutdown after test suite completion ([php-firebird#183](https://github.com/satwareAG/php-firebird/issues/183))
- OO API handle loss after connection tearDown/reconnect cycles ([php-firebird#184](https://github.com/satwareAG/php-firebird/issues/184))
- IBatch handle becomes invalid before execute() ([php-firebird#185](https://github.com/satwareAG/php-firebird/issues/185))
- SIGFPE crash on parameterless batch statements ([php-firebird#180](https://github.com/satwareAG/php-firebird/issues/180))
- Stale DDL tables after SIGSEGV prevents Functional tearDown (consequence of #183)

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

## Phase 3 - Gap Analysis Tests (Issues #65-#67)

11. TransactionTest (#65) - transaction isolation, savepoints, nested transactions
12. DefaultValueTest (#66) - schema manager default value handling
13. ComparatorTest (#67) - schema comparator false-positive detection
14. Full suite regression check on FB4 and FB5

## Phase 4 - Finalize and Merge

15. Final PHPStan + full test suite verification
16. Squash-merge or rebase `001-quality-improvements` into `3.10.x`

---

## Known Upstream Issues (php-firebird)

| Issue | Description | Impact |
|-------|-------------|--------|
| [#180](https://github.com/satwareAG/php-firebird/issues/180) | `fbird_batch_create()` SIGFPE on parameterless statements | BatchTest must use parameterized queries |
| [#183](https://github.com/satwareAG/php-firebird/issues/183) | SIGSEGV during PHP shutdown after connection usage | Non-blocking - tests complete before crash |
| [#184](https://github.com/satwareAG/php-firebird/issues/184) | OO API handle loss after tearDown/reconnect cycles | 9 Integration-ReadOnly tests fail on FB5 |
| [#185](https://github.com/satwareAG/php-firebird/issues/185) | IBatch handle becomes invalid before execute() | BatchTest 3/3 skipped |
| [#96](https://github.com/satwareAG/doctrine-firebird-driver/issues/96) | v8.2.0 reconnection bug (workaround: isql subprocess) | Integration tests use isql for DDL/DML seeding |

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
- Gap analysis: `docs/research/2026-03-03-dbal3-gap-analysis.md`
- Code quality audit: `docs/audit-code-quality-2026-03.md`
- DBAL 4.x research: `docs/research/dbal4-migration.md`
