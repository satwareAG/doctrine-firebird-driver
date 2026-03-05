# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-05 (v3.11.0 released — CharsetMiddleware; PR #85 fixed)
**Branch:** `3.0.x` | **Tag:** `v3.11.0` ✅

---

## 🟡 Priority 1 — Merge PR #85 after CI passes (Issue #47 test suite simplification)

| PR | Title | State | CI |
|----|-------|-------|----|
| **#85** | fix(test): Issue #47 test suite simplification - fix connection cascade failures | Ready | ⏳ CI running |

**Root cause** (identified + fixed 2026-03-05): `getNativeConnection()` in DBAL 3.x returns the raw
Firebird PHP resource, NOT the `FirebirdConnection` object, when middleware layers are present. The
`instanceof FirebirdConnection` check always returned `false`, causing `getFirebirdConnection()` to
return `null`, silently disabling all connection validity checks. Dead connections were reused → 213
cascade failures.

**Fix applied** (commit `c4b4715`): Restored `getWrappedConnection()` traversal loop in
`getFirebirdConnection()` and `connect()`. Branch rebased onto `3.0.x` (includes CharsetMiddleware).
PHPStan Level 8 passes. PR converted from DRAFT to Ready for Review.

**Next action**: Wait for CI to pass (8 jobs: PHP 8.3/8.4 × FB3/FB4/FB5 + Static Analysis + Summary),
then squash-merge to `3.0.x`.

---

## 🟡 Priority 2 — GitLab MRs (unblocked by v3.10.1)

Both were waiting for doctrine-firebird-driver v3.10.1 - now released. Can be progressed.

| Repo | MR | Title | Status |
|------|----|-------|--------|
| `satware/satag-amicron-entity-bundle` | !25 | feat(deps): php-firebird v7.2.0 compatibility | ✅ Unblocked |
| `satware/amicron-platform` | !116 | feat(deps): php-firebird v7.2.0 compatibility | ✅ Unblocked after !25 |

**Dependency chain**: !25 → !116

### Open GitLab Issues

| Repo | Issue | Title |
|------|-------|-------|
| amicron-platform | #75 | chore(ci): replace composer -q with proper error handling |
| amicron-platform | #76 | chore(deps): upgrade satag/doctrine-firebird-driver to ^3.10 when entity bundle allows it |
| amicron-platform | #77 | chore(ci): document CI image upgrade from PHP 8.1 to 8.2 |
| satag-amicron-entity-bundle | #51 | chore(deps): relax satag/doctrine-firebird-driver constraint to allow ^3.10 |

---

## 🟢 Priority 3 — Coverage ≥90% on PHP 8.4 + FB3

Current baseline: **~82.84%** on PHP 8.4 + Firebird 3.0

**Known gaps** (no Docker needed locally for unit coverage):
- `FirebirdSchemaManager` introspection (procedures, triggers, generators)
- `Firebird3Platform` / `Firebird4Platform` / `Firebird5Platform` edge cases

```bash
cd tests && docker compose up -d fb3 app
docker compose exec app php -d pcov.enabled=1 \
  vendor/bin/phpunit --configuration tests/phpunit.xml \
  --coverage-text --coverage-xml=coverage-xml/ 2>&1 | grep -E "Lines|Methods|Classes" | head -10
```

---

## ✅ Completed

| Item | Date | Notes |
|------|------|-------|
| PR #86 merged + v3.11.0 released | 2026-03-05 | CharsetMiddleware (WIN1252/UTF-8 transparent conversion) |
| v3.10.1 released | 2026-03-05 | Patch: CI matrix version parsing + php-firebird v7.2.0 |
| PR #83 merged | 2026-03-05 | fix(driver): accept plain numeric version strings |
| PR #81 merged | 2026-03-05 | feat(deps): php-firebird v7.2.0 compatibility |
| v3.10.0 stable released | 2026-03-04 | Sprints 1-5 complete (#59-#79) |
| Sprint 1 (#59-#64) | 2026-03-04 | All closed |
| Sprint 2 (#65-#68) | 2026-03-04 | All closed |
| Sprint 3 (#69-#71) | 2026-03-04 | All closed |
| Sprint 4 (#72-#74) | 2026-03-04 | All closed |
| Sprint 5 (#75-#79) | 2026-03-04 | All closed |

---

## Resume

```bash
cd /home/mw/external/doctrine-firebird-driver
git log --oneline -5
gh pr checks 85 --repo satwareAG/doctrine-firebird-driver
# If CI passes: squash-merge PR #85, close #47
gh pr merge 85 --repo satwareAG/doctrine-firebird-driver --squash --delete-branch
```
