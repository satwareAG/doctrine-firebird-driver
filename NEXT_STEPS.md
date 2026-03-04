# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-04 (Sprint 1 complete, CI fixes pushed)
**Branch:** `3.0.x` | **Tag:** `v3.10.0-RC.1` | **Commit:** `b392eca`

---

## ✅ Sprint 1 — COMPLETE (2026-03-04)

All 6 Sprint 1 issues (#59–#64) implemented and closed:

| Issue | Test File | Status |
|-------|-----------|--------|
| #59 | `ForeignKeyConstraintViolationsTest.php` | ✅ Closed |
| #60 | `UniqueConstraintViolationsTest.php` | ✅ Closed |
| #61 | `BooleanBindingTest.php` | ✅ Closed |
| #62 | `Platform/NewPrimaryKeyWithNewAutoIncrementColumnTest.php` | ✅ Closed |
| #63 | `Platform/LockMode/NoneTest.php` | ✅ Closed |
| #64 | `Types/DateImmutableTypeTest.php` + `DateTimeImmutableTypeTest.php` + `TimeImmutableTypeTest.php` | ✅ Closed |

CI fixes also pushed (b31381a):
- Dynamic PHP ini path for `shivammathur/setup-php`
- `--ignore-platform-req=php` for psalm install (PHP 8.4 compat)

---

## Priority 1 — Coverage ≥90% on PHP 8.4 + FB3

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

## Priority 2 — Sprint 2 DBAL Gap Issues

Check open Sprint 2 issues:

```bash
gh issue list --repo satwareAG/doctrine-firebird-driver --label "sprint-2" --state open
gh milestone list --repo satwareAG/doctrine-firebird-driver
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
- [ ] Sprint 2 DBAL gap issues implemented
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
gh issue list --repo satwareAG/doctrine-firebird-driver --label "sprint-2" --state open
```

---

## Session History

### 2026-03-04 (commits `b31381a`, `b392eca`)

1. **CI fix — dynamic PHP ini path** — `shivammathur/setup-php` uses a different ini
   location than the system PHP package path. Fixed by deriving the path dynamically
   from `php_ini_loaded_file()` instead of hardcoding `/etc/php/X.Y/cli/conf.d/`.

2. **CI fix — psalm PHP 8.4 compat** — `vimeo/psalm ^5.0` caps at ~8.3.0 and is
   incompatible with PHP 8.4 platform (`config.platform.php=8.4` in composer.json).
   Fixed by adding `--ignore-platform-req=php` to the static-analysis Composer install.

3. **Sprint 1 complete** — All 6 Sprint 1 issues (#59–#64) implemented as TDD test
   classes. 8 new test files, 755 lines, PHPStan Level 8 clean. Issues auto-closed
   via `Closes #N` keywords in commit b392eca.

### 2026-03-03 (commit `c197f3d`)

1. **GitHub token `workflow` scope** — Re-authenticated with `gh auth login --scopes workflow`
   to allow pushing `.github/workflows/ci.yml` changes.

2. **PHPStan CI fix** — `phpstan.neon.dist` was using `stubFiles` for vendor fbird stubs.
   PHPStan's `stubFiles` only overrides symbols from an *installed* extension.
   When `ext-firebird` is absent (CI), `stubFiles` has no effect.
   Fixed by switching to `scanFiles` which discovers symbols unconditionally.

3. **Statement.php cleanup** — Removed redundant `is_array()` check after `!== false`
   guard (PHPStan now knows `fbird_fetch_assoc()` returns `array|false`).
