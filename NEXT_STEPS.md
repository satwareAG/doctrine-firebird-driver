# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-16 (v3.12.0 stable: Psalm 6.15.1, baseline reduction, changelog consolidated)
**Branch:** `3.10.x-dev` | **Tag:** `v3.12.0` (pending stable)

---

## ✅ Branching Transition (3.10.x Strategy)

The repository has transitioned to a new branching scheme aligned with Doctrine DBAL 3.10.x.

- **Stable Branch:** `3.10.x` (protected, source of truth for stable releases)
- **Development Branch:** `3.10.x-dev` (default branch, target for all PRs)
- **Legacy Maintenance:** `3.0.x`

---

## ✅ Priority 2 — Coverage ≥90% on PHP 8.4 + FB3 (COMPLETE - VERIFIED)

**Baseline:** 86.52% | **Final verified:** 90.03% (2023/2247 statements) | **Target:** ≥90% ✅

New unit tests added (commits `1f0aa1b` + current):
- `tests/Test/Unit/Driver/ExecutionModeTest.php` - ExecutionMode autocommit state machine
- `tests/Test/Unit/Driver/ExceptionConverterTest.php` - all ExceptionConverter conversion paths
- `tests/Test/Unit/Driver/Middleware/CharsetMiddlewareTest.php` - CharsetMiddleware/Connection/Statement
- `tests/Test/Unit/Platforms/KeywordsTest.php` - Firebird3/4/5 keywords getName() + isKeyword()
- `tests/Test/Unit/Platforms/SelectSQLBuilderTest.php` - FirebirdSelectSQLBuilder ROWS/LIMIT/OFFSET/LIKE/FOR UPDATE
- `tests/Test/Unit/Platforms/FirebirdPlatformCoverageGapTest.php` - sequence, boolean, char-mode paths + 9 gap tests (event hooks, AbstractAsset branch, sequence/check SQL, identifier quoting)
- `tests/Test/Unit/Platforms/Firebird3PlatformCoverageGapTest.php` - drop/modify column paths + 4 event hook gap tests

> Coverage verified with PCOV + full test suite (Unit + Integration + Functional) in Docker.
> 1134+ unit tests pass; functional suite runs against Firebird 3 container.

---

## ✅ v3.12.0 Stable Release (RC→Stable Promotion)

Steps 1–6 of the release plan completed:
1. ✅ Psalm upgraded to 6.15.1 (PHP 8.4 compatible, 0 errors)
2. ✅ Psalm baseline reduced 51% (420→204 lines, 151 errors fixed, `#[Override]` on 146 methods)
3. ✅ Full test suite passing (1988 tests, 4199 assertions, 0 failures)
4. ✅ CHANGELOG.md consolidated (RC.2 + RC.3 → single [3.12.0] entry)
5. ✅ Untracked scratch files cleaned up
6. ✅ All changes committed on `3.10.x-dev`

**Remaining:** Merge `3.10.x-dev` → `3.10.x` via PR, tag `v3.12.0` stable.

---

## 🔵 Priority 3 — DBAL 4.x Migration (Issue #90) [ACTIVE]

`getWrappedConnection()` is deprecated in DBAL 3.x and **removed in DBAL 4.x**. Current
usage in `FunctionalTestCase::getFirebirdConnection()` and `connect()` will break.

**Next steps:**
1. Research DBAL 4.x API for middleware-layered connection unwrapping — document in `docs/research/dbal4-migration.md`.
2. Create `4.0.x` branch from `3.10.x`, update `composer.json` to require `doctrine/dbal ^4.0`.
3. Fix all removed-API compilation errors.
4. Add DBAL 4.x and Firebird 4/5 container jobs to CI matrix.
5. Tag `v4.x.y` series from `4.0.x` branch.

See Issue #90 for detailed tracking and the full `4.0.x` strategy in `docs/BRANCHING.md`.

---

## ✅ Completed

| Item | Date | Notes |
|------|------|-------|
| v3.12.0 stable prep (steps 1–6) | 2026-03-16 | Psalm 6.15.1, baseline -51%, 1988 tests green, changelog consolidated |
| !116 amicron-platform merged | 2026-03-05 | feat(deps): php-firebird v7.2.0 compat; fix(ci): rm stale composer.lock before install |
| !25 satag-amicron-entity-bundle merged | 2026-03-05 | feat(deps): php-firebird v7.2.0 compat (PHP ^8.2, ext-firebird ^7.2.0) |
| AppVeyor CI removed | 2026-03-05 | No Windows DLLs for ext-firebird v7.x; follows Doctrine pattern; research: `docs/research/2026-03-05-windows-ci-removal-decision.md` |
| PR #85 merged + issue #47 closed | 2026-03-05 | Test suite simplified; Firebird cursor-lock fix |
| Project cleanup: archive docs/issues/ + plans/ | 2026-03-05 | 17 resolved issue docs archived |
| Delete stale feat/charset-middleware branch | 2026-03-05 | Merged in v3.11.0 |
| Branching strategy documented | 2026-03-05 | `docs/BRANCHING.md` created |
| Learning: cursor-lock teardown | 2026-03-05 | `docs/learnings/2026-03-05-firebird-cursor-lock-teardown.md` |
| PR #86 merged + v3.11.0 released | 2026-03-05 | CharsetMiddleware (WIN1252/UTF-8 transparent conversion) |
| v3.10.1 released | 2026-03-05 | Patch: CI matrix version parsing + php-firebird v7.2.0 |
| PR #83 merged | 2026-03-05 | fix(driver): accept plain numeric version strings |
| PR #81 merged | 2026-03-05 | feat(deps): php-firebird v7.2.0 compatibility |
| v3.10.0 stable released | 2026-03-04 | Sprints 1-5 complete (#59-#79) |

---

## Resume

```bash
cd /home/mw/external/doctrine-firebird-driver
git fetch --all
# Working on 3.10.x-dev branch
# Next: Merge 3.10.x-dev → 3.10.x, tag v3.12.0 stable
# Then: DBAL 4.x migration — research getWrappedConnection() replacement
```
