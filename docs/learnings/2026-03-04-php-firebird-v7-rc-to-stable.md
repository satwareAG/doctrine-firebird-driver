---
description: >-
  Lessons from migrating doctrine-firebird-driver to php-firebird v7.0.0 stable
  and preparing v3.10.0-RC.1 for PHP 8.4 + Firebird 3.0 primary target.
tags:
  - php
  - firebird
  - phpstan
  - ci-cd
  - doctrine
last_updated: '2026-03-04'
---

# 2026-03-04: php-firebird v7.0.0 GA Migration + v3.10.0-RC.1

## Problem

`doctrine-firebird-driver` was on `php-firebird v7.0.0-rc.52`. The stable GA `v7.0.0`
was released 2026-03-04 with 85 `fbird_*` functions and confirmed stable OO classes
(`Firebird\Database`, `Firebird\Transaction`, `Firebird\Batch`, `Firebird\DbInfo`, etc.).

The `composer.json` still declared `"ext-firebird": "^7.0.0-rc.52"` which would block
installation once stable dropped the pre-release namespace.

## Solution

1. Update `composer.json`: `ext-firebird "^7.0.0"`, `satwareag/php-firebird-stubs "^7.0.0"`
2. Update Dockerfile: `--branch v7.0.0` with build-time version assertion:
   ```dockerfile
   php -r "if (phpversion('interbase') !== '7.0.0') { exit(1); }"
   ```
3. Remove stale `phpstan.neon.dist` `ignoreErrors` — they were for rc.52 stubs that no
   longer match the v7.0.0 stub signatures.
4. Fix `Connection::executeAuto()` return type: `int|false` → `mixed` (v7.0.0 stub
   now correctly declares `resource|int|false`).

## Lesson

**PHPStan stub evolution**: When upgrading a C extension's stubs package, always diff
the stub signatures — v7.0.0 GA often tightens/widens return types vs RC stubs.
Old `ignoreErrors` become stale and must be removed; otherwise PHPStan reports
"error in ignored error" warnings at level 8.

---

## Problem

Amicron ERP bundle (`satag-amicron-entity-bundle`) requires `php: ^8.4` but the
driver's `composer.json` `config.platform.php` was still `"8.1"`. This caused
`composer update` in the bundle to see stubs optimized for PHP 8.1.

## Solution

Raise `config.platform.php` to `"8.4"` — safe because:
- PHP 8.1 is still the **require** minimum (installable on 8.1–8.4+)
- Platform PHP controls which PHP 8.4 features Composer resolves for dev tools only
- All `#[Override]` attributes were already present (PHP 8.3+)

## Lesson

`config.platform.php` and `require.php` serve different purposes:
- `require.php: ^8.1` = minimum supported for end users
- `config.platform.php: 8.4` = version dev tools (PHPStan, Rector) optimize for

Align `platform.php` with the primary consumer's actual PHP version.

---

## Problem

Gitleaks `detect --no-git` reported 10 "generic-api-key" leaks — all were PHP
sodium extension function names (`sodium_crypto_aead_chacha20poly1305_decrypt` etc.)
inside `vendor/phpstan/phpstan/phpstan.phar`.

## Solution

Add `.gitleaks.toml` allowlisting `vendor/` and `vendor-bin/` paths:
```toml
[[allowlists]]
paths = ['''vendor/.*\.phar$''', '''vendor-bin/''']
```

## Lesson

Always scan with `--config .gitleaks.toml` that excludes `vendor/`. The `--no-git`
flag scans ALL files including the full vendor tree. Pre-compiled `.phar` files
contain large binary+text blobs that trigger many generic-api-key false positives.
The baseline approach (`--baseline-path`) is alternative but path-based allowlists
are cleaner for permanent vendor exclusions.

---

## API Version Boundary: Firebird 3.0 vs 4.0+

Critical knowledge confirmed during this session:

| Feature | FB3.0 | FB4.0+ |
|---------|-------|--------|
| `fbird_execute_auto()` | ✅ | ✅ |
| `fbird_connection_info()` | ✅ | ✅ |
| Exception Mode (`FBIRD_EXCEPTION_MODE_THROW`) | ✅ | ✅ |
| `fbird_batch_*` (IBatch API) | ❌ | ✅ |
| Multi-row `RETURNING` | ❌ | ✅ |
| `fbird_execute_statement()` / `fbird_execute_query()` | ❌ | ✅ |

**Pattern**: Guard ALL IBatch and new v7.0.0 DML/SELECT-split functions with
`version_compare($serverVersion, '4.0', '>=')`. Throw `DriverException` on FB3.
Document the boundary clearly in EXAMPLES.md and PERFORMANCE.md.
