# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-04 (v3.10.0-RC.1 prepared and published)
**Branch:** `3.0.x` | **Tag:** `v3.10.0-RC.1` | **Commit:** `d64f0b2`

---

## Priority 1 — Coverage ≥90% on PHP 8.4 + FB3 (aspirational RC target)

Current baseline: **~82.84%** on PHP 8.4 + Firebird 3.0

```bash
# start Docker stack and measure
cd tests && docker compose up -d fb3 app
docker compose exec app php -d pcov.enabled=1 \
  vendor/bin/phpunit --configuration tests/phpunit.xml \
  --coverage-text --coverage-xml=coverage-xml/ 2>&1 | grep -E "Lines|Methods|Classes" | head -10
```

### Known coverage gaps (FB3, no Docker needed locally):
- `Connection::queryInTransaction()` — CQRS pattern, needs FB3 functional test
- `Connection::getConnectionInfo()` — `DbInfo` OO branch, needs FB3 functional test
- `Connection::getLimboTransactions()` + `reconnectLimboTransaction()` — rare path
- `FirebirdSchemaManager` introspection (procedures, triggers, generators)
- `Firebird3Platform` / `Firebird4Platform` / `Firebird5Platform` edge cases

### New test classes to create:
- `tests/Test/Functional/Connection/ExecuteAutoTest.php`
- `tests/Test/Functional/Connection/QueryInTransactionTest.php`
- `tests/Test/Functional/Connection/ConnectionInfoTest.php`

---

## Priority 2 — DBAL 3.10.x Gap Issues (#59–#79)

21 issues created in `docs/plans/2026-03-03-dbal3-gap-implementation.md`
Primary targets (FB3-safe):

| Issue | Title | Effort |
|-------|-------|--------|
| #59 | `getListTablesSQL()` optimized query | S |
| #60 | `getListViewsSQL()` — missing | S |
| #61 | `getListSequencesSQL()` — missing | S |
| #62 | `modifyLimitQuery()` with OFFSET only | M |
| #63 | `getDefaultValueDeclarationSQL()` improvements | M |
| #64 | Transaction isolation SQL builder | M |

```bash
gh issue list --repo satwareAG/doctrine-firebird-driver --state open --limit 30
```

---

## Priority 3 — Satag Amicron Entity Bundle Integration Test

Bundle: `/home/mw/internal/satag-amicron-entity-bundle`
Requirements confirmed: `php: ^8.4`, `satag/doctrine-firebird-driver: ^3.10.0-rc.1`

```bash
cd /home/mw/internal/satag-amicron-entity-bundle
composer update satag/doctrine-firebird-driver
vendor/bin/phpunit --no-coverage 2>&1 | tail -20
```

Verify WIN1252 charset works correctly with Amicron ERP tables (ADRESSEN, AUFTRAG, ATRPOS).

---

## Priority 4 — Promote RC.1 → Full Release

Checklist before `v3.10.0` stable:
- [ ] Coverage ≥80% confirmed in GitHub Actions CI pipeline (green badge)
- [ ] Functional tests on FB3 passing in CI matrix
- [ ] Amicron Entity Bundle integration test passing
- [ ] DBAL gap issues #1-5 implemented (Priority 2 above)
- [ ] Codecov badge showing real coverage number
- [ ] Packagist updated (auto via GitHub tag)

```bash
# When ready to promote:
git tag -a v3.10.0 -m "Stable release — PHP 8.4 + Firebird 3.0 primary"
git push origin v3.10.0
gh release create v3.10.0 --target 3.0.x --title "v3.10.0 — Stable"
```

---

## Resume

```bash
cd /home/mw/external/doctrine-firebird-driver
git log --oneline -5
vendor/bin/phpstan analyse src/ --level=8 --no-progress
```
