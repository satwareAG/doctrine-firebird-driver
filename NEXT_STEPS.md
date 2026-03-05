# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-05 (AppVeyor removed; Windows CI research completed)
**Branch:** `3.0.x` | **Tag:** `v3.11.0` ✅

---

## 🟡 Priority 1 — GitLab MRs (unblocked by v3.10.1)

Both were waiting for doctrine-firebird-driver v3.10.1 - now released.

| Repo | MR | Title | Status |
|------|----|-------|--------|
| `satware/satag-amicron-entity-bundle` | !25 | feat(deps): php-firebird v7.2.0 compatibility | Unblocked |
| `satware/amicron-platform` | !116 | feat(deps): php-firebird v7.2.0 compatibility | Unblocked after !25 |

**Dependency chain**: !25 must merge first, then !116.

### Related GitLab Issues

| Repo | Issue | Title |
|------|-------|-------|
| amicron-platform | #75 | chore(ci): replace composer -q with proper error handling |
| amicron-platform | #76 | chore(deps): upgrade satag/doctrine-firebird-driver to ^3.10 |
| amicron-platform | #77 | chore(ci): document CI image upgrade from PHP 8.1 to 8.2 |
| satag-amicron-entity-bundle | #51 | chore(deps): relax satag/doctrine-firebird-driver constraint to allow ^3.10 |

---

## 🟢 Priority 2 — Coverage ≥90% on PHP 8.4 + FB3

Current baseline: **~82.84%** | Target: **≥90%**

**Known gaps:**
- `FirebirdSchemaManager` introspection (procedures, triggers, generators)
- `Firebird3Platform` / `Firebird4Platform` / `Firebird5Platform` edge cases
- Schema comparison / migration scenarios

```bash
# Run locally with coverage
cd tests && docker compose up -d fb3 app
docker compose exec app php -d pcov.enabled=1 \
  vendor/bin/phpunit --configuration tests/phpunit.xml \
  --coverage-text --coverage-xml=coverage-xml/ 2>&1 | grep -E "Lines|Methods|Classes" | head -10
```

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
# GitLab MRs: !25 (satag-amicron-entity-bundle) → !116 (amicron-platform)
# Coverage: docker compose up -d fb3 app && run phpunit with pcov
```
