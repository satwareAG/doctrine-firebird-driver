# Next Steps - doctrine-firebird-driver

**Last session:** 2026-03-31 (3 test failures fixed, implementation plan updated)
**Branch:** `001-quality-improvements` (based on `3.10.x`)
**Extension:** php-firebird v10.3.6 (`ext-firebird: ^10.3.2`)
**Status:** Active - test suite stabilization and quality improvements

---

## Current State (2026-03-31)

### Completed This Session
- Fixed FB4 SIGFPE crash in BatchTest (fbird_batch_create on parameterless statements)
- Filed upstream issue [php-firebird#180](https://github.com/satwareAG/php-firebird/issues/180)
- Fixed FB5 phpunit config (wrong db_dbname path)
- Fixed AlbumTest ISQL seeding (boolean TRUE/FALSE literals, table name case)
- Updated implementation plan for quality improvements Phase 2

### Metrics
- PHPStan Level 8: 0 errors, empty baseline
- `@fbird_*` suppressions in src/: 0 (all removed)
- Connection.php: 903 LOC (down from 1320, TransactionManager extracted)
- FB4 Functional: ~646 tests (BatchTest has known upstream failures - php-firebird#180)
- Integration-ReadOnly: 15/16 pass, 105 assertions
- Commits: 1c06ade, 04e5807, 96c31cc

---

## Phase 1 - Test Suite Stabilization (Next Up)

1. Mark BatchTest known failures with `markTestSkipped()` and php-firebird#180 reference
2. Add Integration suites to `phpunit-firebird4.xml` and `phpunit-firebird5.xml`
3. Run full FB4 suite - capture clean baseline (all pass or skip, no crashes)
4. Run full FB5 suite - verify clean baseline

## Phase 2 - Resource Type Guard Completion

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
| [#96](https://github.com/satwareAG/doctrine-firebird-driver/issues/96) | v8.2.0 reconnection bug (workaround: isql subprocess) | Integration tests use isql for DDL/DML seeding |
| OO API handle loss | Connection loses OO API handle after many operations | 1 Integration-ReadOnly test fails |
| SIGSEGV at exit | Segfault during PHP shutdown after test suite | Non-blocking - tests complete before crash |

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
