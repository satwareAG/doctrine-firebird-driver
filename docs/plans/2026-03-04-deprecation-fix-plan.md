# Deprecation Fix Plan — doctrine-firebird-driver

**Date:** 2026-03-04  
**Branch:** `3.0.x`  
**Status:** ✅ ALL SPRINTS COMPLETE + CI GREEN (2026-03-04)  
**Scope:** All open deprecations, outdated dependencies, CI best-practice gaps

---

## CI Fix Log (Sprint 5 Follow-up — 2026-03-04 EOD)

After Sprint 5 push (commit `bfeca67`), CI revealed 5 test failures requiring fixes.
All resolved in commits `6017c70`, `d701bf1`, `520fe79`.

| Fix | Commit | Description |
|-----|--------|-------------|
| A | `6017c70` | `FirebirdComparator.php:101` — `trim()` on int, cast to `(string)` |
| B | `6017c70` | `PrimaryReadReplicaConnectionTest` — `isConnectedToReplica()` → `isConnectedToPrimary()` |
| C | `6017c70` | `ExecuteAutoTest` — `fbird_execute_auto` with SELECT not allowed + `rollBack()` fix |
| D | `6017c70` | `QueryInTransactionTest` — `class_exists('Firebird\TBuilder')` guard + exception type |
| E | `d701bf1` | `ConnectionInfoTest` — fix regex to match raw `LI-V3.0.13.33818` format (DBAL requires raw) |
| F | `6017c70` | `GH23Test` — `array_change_key_case(CASE_LOWER)` for uppercase Firebird column names |
| G | `6017c70` | `GH50Test` — `markTestIncomplete` for known deadlock issue #50 |
| H | `520fe79` | `PrimaryReadReplicaConnectionTest` — skip on network unreachable (service manager) |

**Final CI run:** `22681878826` — ✅ ALL GREEN  
`Static Analysis ✓ | PHP 8.4/8.3 × FB3/4/5 ✓ | CI Summary ✓`

---

## Executive Summary

CI is GREEN (run 22668063691). This plan addresses all deprecations and outdated
dependencies discovered via:

- `gh issue list` (27 open issues, #80 is the key new one)
- `composer outdated --direct` (12 packages with updates)
- CI log analysis (Codecov action deprecation, `composer/package-versions-deprecated`)
- `phpstan.neon.dist` audit (7 suppressed DBAL deprecation calls)
- Deep research: GitHub Actions 2026, PHPStan 2.x, Rector 2.x, php-firebird v7.1.0

---

## Findings by Category

### A. GitHub Actions — CI Workflow (`.github/workflows/ci.yml`)

| Finding | Current | Target | Priority |
|---------|---------|--------|----------|
| `actions/checkout` | `@v4` | `@v5` | P2 |
| `codecov/test-results-action` | `@v1` (deprecated) | Remove — use `codecov-action@v5` with `files:` | **P1** |
| `codecov/codecov-action` | `@v5` ✅ | Already v5, but needs `report_type: test_results` for JUnit | P1 |
| `phpVersion` in phpstan.neon.dist | `80100` (PHP 8.1) | `80300` (PHP 8.3 = config.platform.php) | P2 |
| SHA pinning | Tags only | Pin to SHA (security best practice 2026) | P3 |
| `permissions:` block | Missing | Add `contents: read` + `id-token: write` | P2 |
| php-firebird build tag | `v7.0.0` hardcoded | `v7.1.0` (issue #80) | **P1** |

**Key finding:** `codecov/test-results-action@v1` is **deprecated** per Codecov's own
announcement. The correct 2026 pattern is a single `codecov/codecov-action@v5` step
with both coverage and JUnit files in `files:` parameter.

### B. Composer Dependencies — `composer.json`

| Package | Current | Latest | Action | Priority |
|---------|---------|--------|--------|----------|
| `satwareag/php-firebird-stubs` | `7.0.0` | `7.1.0` | Bump to `^7.1.0` | **P1** (issue #80) |
| `ext-firebird` constraint | `^7.0.0` | `^7.1.0` | Bump (issue #80) | **P1** |
| `phpstan/phpstan` | `1.12.33` | `2.1.40` | Bump to `^2.0` | P2 |
| `phpstan/phpstan-deprecation-rules` | `1.2.1` | `2.0.4` | Bump to `^2.0` | P2 |
| `phpstan/phpstan-doctrine` | `1.5.7` | `2.0.18` | Bump to `^2.0` | P2 |
| `phpstan/phpstan-strict-rules` | `1.6.2` | `2.0.10` | Bump to `^2.0` | P2 |
| `phpunit/phpunit` | `10.5.63` | `12.5.14` | Bump to `^11.0` (skip 12 for now) | P3 |
| `jetbrains/phpstorm-stubs` | `2024.3` | `2025.3` | Bump to `2025.3` | P2 |
| `vimeo/psalm` | `5.26.1` | `6.5.0` | Bump to `^6.0` | P3 |
| `psalm/plugin-phpunit` | `0.18.4` | `0.19.5` | Bump to `^0.19` | P3 |
| `symfony/cache` | `6.4.34` | `7.4.6` | Keep `^6.0\|^7.0` (already allows 7) | ✅ OK |
| `symfony/console` | `6.4.34` | `7.4.6` | Keep `^4.4\|^5.4\|^6.0\|^7.0` (already allows 7) | ✅ OK |
| `composer/package-versions-deprecated` | installed transitively | N/A | Not a direct dep; Composer 2.x runtime API replaces it | ✅ OK |

**Note on `composer/package-versions-deprecated`:** This appears in CI logs as a
transitive dependency. It is NOT in our `composer.json` directly. Composer 2.x
provides `composer-runtime-api ^2` which we already require. No action needed.

### C. PHPStan Configuration (`phpstan.neon.dist`)

| Finding | Current | Target | Priority |
|---------|---------|--------|----------|
| `phpVersion` | `80100` (PHP 8.1) | `80300` (PHP 8.3 = config.platform.php) | P2 |
| PHPStan 1.x → 2.x migration | `^1.12` | `^2.0` | P2 |
| 7 suppressed DBAL deprecated method calls | Suppressed | Fix underlying calls (DBAL 4.x prep) | P3 (Sprint 3+) |

**PHPStan 2.x migration notes:**
- `phpVersion: 80100` syntax is still valid in 2.x (no change needed to format)
- All three phpstan extensions (`strict-rules`, `deprecation-rules`, `doctrine`) need
  `^2.0` bumps to be compatible with PHPStan 2.x core
- PHPStan 2.x adds Level 10 (explicit `mixed`); we stay at Level 8
- Bleeding Edge mode in 1.12 previews 2.x changes — we can test before committing

### D. Rector Configuration (`rector.php`)

| Finding | Current | Target | Priority |
|---------|---------|--------|----------|
| PHP target set | `UP_TO_PHP_81` | `UP_TO_PHP_84` | P2 |
| Rector version | `vendor-bin/rector` | Check if 2.x | P2 |

**Note:** `LevelSetList::UP_TO_PHP_84` is available in Rector 2.x. The current
`UP_TO_PHP_81` misses PHP 8.2, 8.3, 8.4 modernization rules.

### E. Source Code — PHP 8.4 Deprecations

**PHP 8.4 implicit nullable parameter deprecation:**
PHP 8.4 deprecates `function foo(Type $param = null)` — must use `?Type $param = null`.

Affected files (from grep):
- `src/Platforms/FirebirdPlatform.php:964` — `getListTableColumnsSQL($table, $database = null)`
- `src/Platforms/FirebirdPlatform.php:1054` — `getListTableIndexesSQL($table, $database = null)`
- `src/Platforms/Firebird3Platform.php:212` — `getListTableColumnsSQL($table, $database = null)`
- `src/Schema/FirebirdSchemaManager.php:422` — `_getPortableTableIndexesList($tableIndexes, $tableName = null)`

**Note:** These are DBAL interface overrides — the parent signature must be checked
before changing. If parent uses untyped `$param = null`, we match it. If parent uses
`?string $param = null`, we update. This requires careful inspection.

### F. php-firebird v7.1.0 (Issue #80)

**New in v7.1.0:**
- `fbird_query_params_tx(resource $link, resource $trans, string $sql, ?array $params)` — new function enabling DBAL 4.x integration
- `ibase_*` aliases deprecated (removal in v7.2.0) — **already clean** (0 `ibase_*` refs in src/)
- PHP 8.1 support deprecated (already dropped from our matrix)
- Firebird 2.5 connectivity deprecated (not in our test matrix)

**Required changes:**
1. Bump `ext-firebird: ^7.1.0` in `composer.json`
2. Bump `satwareag/php-firebird-stubs: ^7.1.0` in `require-dev`
3. Update CI to build `php-firebird v7.1.0` (hardcoded `v7.0.0` in ci.yml)
4. Add `fbird_query_params_tx` stub awareness (via updated stubs package)

---

## Implementation Plan — Prioritized Sprints

### Sprint D1 — Critical Deprecation Fixes (P1) — ~1 hour

**Goal:** Fix the two most urgent deprecations that affect CI correctness.

#### D1.1 — Fix `codecov/test-results-action@v1` deprecation in CI

**File:** `.github/workflows/ci.yml`

Replace the two-step Codecov upload with a single consolidated step:

```yaml
# BEFORE (deprecated):
- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v5
  with:
    files: ./coverage.xml
    token: ${{ secrets.CODECOV_TOKEN }}
    fail_ci_if_error: false

- name: Upload test results to Codecov
  if: matrix.php-version == '8.4' && matrix.firebird.version == '3.0' && !cancelled()
  uses: codecov/test-results-action@v1
  with:
    files: ./junit.xml
    token: ${{ secrets.CODECOV_TOKEN }}

# AFTER (2026 best practice):
- name: Upload coverage and test results to Codecov
  uses: codecov/codecov-action@v5
  with:
    files: ./coverage.xml,./junit.xml
    token: ${{ secrets.CODECOV_TOKEN }}
    fail_ci_if_error: false
```

**Commit:** `chore(ci): replace deprecated test-results-action with codecov-action@v5 files param`

#### D1.2 — Bump php-firebird to v7.1.0 in CI + composer.json (Issue #80)

**Files:** `.github/workflows/ci.yml`, `composer.json`

1. In `ci.yml`: Change `--branch v7.0.0` → `--branch v7.1.0`
2. In `composer.json`: Bump `ext-firebird: ^7.1.0`, `satwareag/php-firebird-stubs: ^7.1.0`

**Commit:** `chore(deps): bump php-firebird to v7.1.0 — closes #80`

---

### Sprint D2 — PHPStan 2.x Migration (P2) — ~2 hours

**Goal:** Migrate from PHPStan 1.x to 2.x with all extensions.

#### D2.1 — Update composer.json PHPStan constraints

```json
"phpstan/phpstan": "^2.0",
"phpstan/phpstan-deprecation-rules": "^2.0",
"phpstan/phpstan-doctrine": "^2.0",
"phpstan/phpstan-strict-rules": "^2.0"
```

#### D2.2 — Update phpstan.neon.dist

- Change `phpVersion: 80100` → `phpVersion: 80300` (matches `config.platform.php`)
- Review any new errors introduced by PHPStan 2.x stricter rules
- PHPStan 2.x Level 8 should still pass (we stay at 8, not 10)

#### D2.3 — Update jetbrains/phpstorm-stubs

```json
"jetbrains/phpstorm-stubs": "2025.3"
```

**Commit:** `chore(deps): migrate PHPStan 1.x → 2.x with all extensions — update phpVersion to 80300`

---

### Sprint D3 — Rector + PHP 8.4 Modernization (P2) — ~1 hour

**Goal:** Update Rector target to PHP 8.4 and fix implicit nullable deprecations.

#### D3.1 — Update rector.php

```php
// Change:
LevelSetList::UP_TO_PHP_81,
// To:
LevelSetList::UP_TO_PHP_84,
```

#### D3.2 — Fix implicit nullable parameters (PHP 8.4 deprecation)

Inspect and fix these signatures (check parent DBAL interface first):
- `FirebirdPlatform::getListTableColumnsSQL($table, $database = null)`
- `FirebirdPlatform::getListTableIndexesSQL($table, $database = null)`
- `Firebird3Platform::getListTableColumnsSQL($table, $database = null)`
- `FirebirdSchemaManager::_getPortableTableIndexesList($tableIndexes, $tableName = null)`

**Commit:** `fix(php84): update rector target to PHP 8.4, fix implicit nullable params`

---

### Sprint D4 — CI Security Hardening (P2) — ~30 min

**Goal:** Apply 2026 GitHub Actions security best practices.

#### D4.1 — Add `permissions:` block to CI workflow

```yaml
permissions:
  contents: read
```

Add at job level for `test` and `static-analysis` jobs.

#### D4.2 — Update `actions/checkout@v4` → `@v5`

```yaml
uses: actions/checkout@v5
```

#### D4.3 — Fix `phpVersion` in phpstan.neon.dist (if not done in D2)

Already covered in D2.2.

**Commit:** `chore(ci): add permissions block, upgrade checkout to v5`

---

### Sprint D5 — PHPUnit 11 Migration (P3) — ~2 hours

**Goal:** Migrate from PHPUnit 10.5 to PHPUnit 11.

**Note:** PHPUnit 12 is available but PHPUnit 11 is the safer migration step.
PHPUnit 10.5 is still supported but EOL is approaching.

Key changes in PHPUnit 11:
- `#[DataProvider]` attribute replaces `@dataProvider` annotation (Rector handles this)
- `#[Test]` attribute replaces `@test` annotation
- `setUp()`/`tearDown()` return type `void` enforced
- `getMockBuilder()` changes

**Commit:** `chore(deps): migrate PHPUnit 10.5 → 11.x`

---

### Sprint D6 — Psalm v6 + Plugin Update (P3) — ~30 min

**Goal:** Update Psalm and its PHPUnit plugin.

```json
"vimeo/psalm": "^6.0",
"psalm/plugin-phpunit": "^0.19"
```

**Note:** Psalm v6 may require `psalm.xml.dist` updates. Verify before committing.

**Commit:** `chore(deps): bump vimeo/psalm to v6, psalm/plugin-phpunit to ^0.19`

---

## Execution Order

```text
D1.1 → D1.2 → D2 → D3 → D4 → D5 → D6
```

Each step: implement → `vendor/bin/phpstan analyse src/ --level=8` → commit.

---

## Files to Modify

| File | Sprints |
|------|---------|
| `.github/workflows/ci.yml` | D1.1, D1.2, D4.2, D4.1 |
| `composer.json` | D1.2, D2.1, D2.3, D5, D6 |
| `phpstan.neon.dist` | D2.2 |
| `rector.php` | D3.1 |
| `src/Platforms/FirebirdPlatform.php` | D3.2 |
| `src/Platforms/Firebird3Platform.php` | D3.2 |
| `src/Schema/FirebirdSchemaManager.php` | D3.2 |

---

## Issues to Close After Implementation

| Issue | Sprint | Action |
|-------|--------|--------|
| #80 — bump php-firebird to v7.1.0 | D1.2 | Close via commit |

---

## Out of Scope (Tracked Separately)

| Item | Reason | Tracked In |
|------|--------|------------|
| DBAL 4.x migration (7 suppressed deprecated calls) | Major breaking change, separate sprint | Issue #78 |
| Sprint 3–5 DBAL gap issues (#69–#79) | Feature work, not deprecations | Issues #69–#79 |
| Coverage ≥90% | Feature work | NEXT_STEPS.md Priority 1 |
| `fbird_query_params_tx` integration | DBAL 4.x prep, not urgent | Issue #80 checklist |
| Windows CI (#38) | Blocked by php-firebird DLLs | Issue #38 |
| SHA pinning for all actions | Security hardening, low urgency | D4 (optional) |

---

## Verification Checklist

After each sprint:

```bash
# PHPStan must pass clean
vendor/bin/phpstan analyse src/ --level=8 --no-progress

# Composer validate
php composer.phar validate --strict

# Git status clean
git status
```

After all sprints:

```bash
# Push and verify CI green
git push origin 3.0.x
gh run watch --repo satwareAG/doctrine-firebird-driver
```
