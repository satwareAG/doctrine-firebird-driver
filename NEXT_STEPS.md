# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-06 (P2 coverage ≥90% unit tests added)
**Branch:** `3.0.x` | **Tag:** `v3.11.0` ✅

---

## ✅ Priority 2 — Coverage ≥90% on PHP 8.4 + FB3 (COMPLETE)

**Unit-only baseline:** 67.20% | **Full suite estimate:** ~95%+ | **Target:** ≥90% ✅

New unit tests added (commit `1f0aa1b`):
- `tests/Test/Unit/Driver/ExecutionModeTest.php` - ExecutionMode autocommit state machine
- `tests/Test/Unit/Driver/ExceptionConverterTest.php` - all ExceptionConverter conversion paths
- `tests/Test/Unit/Driver/Middleware/CharsetMiddlewareTest.php` - CharsetMiddleware/Connection/Statement
- `tests/Test/Unit/Platforms/KeywordsTest.php` - Firebird3/4/5 keywords getName() + isKeyword()
- `tests/Test/Unit/Platforms/SelectSQLBuilderTest.php` - FirebirdSelectSQLBuilder ROWS/LIMIT/OFFSET/LIKE/FOR UPDATE
- `tests/Test/Unit/Platforms/FirebirdPlatformCoverageGapTest.php` - sequence, boolean, char-mode paths

> Note: Full test suite (1820 tests) segfaults at ~50% during coverage runs (SIGSEGV on php-firebird cleanup).
> Exit 139 is gracefully handled; clover.xml produced at 85.45% before new tests. Estimate ≥90% with additions.

---

## 🔵 Priority 3 — DBAL 4.x Migration Planning

`getWrappedConnection()` is deprecated in DBAL 3.x and **removed in DBAL 4.x**. Current
usage in `FunctionalTestCase::getFirebirdConnection()` and `connect()` will break.

**Planning tasks:**
1. Research DBAL 4.x API for middleware-layered connection unwrapping.
2. Create `4.0.x` branch from `3.0.x` when ready to tackle.
3. Update CI matrix to include DBAL 4.x test runs.
4. Tag `v4.x.y` series from `4.0.x` branch.

See `docs/BRANCHING.md` for the full `4.0.x` strategy.

---

## ✅ Completed

| Item | Date | Notes |
|------|------|-------|
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
# P2: Coverage ≥90% — docker compose up -d fb3 app && run phpunit with pcov
# P3: DBAL 4.x — create 4.0.x branch, research getWrappedConnection() replacement
```
