# Implementation Plan

[Overview]
Upgrade php-firebird to v10.6.2, pin GitHub Actions to immutable SHAs, and remove the redundant polyfill-php82 dependency.

This plan closes issues #105 (php-firebird v10.6.2 upgrade) and #106 (Windows CI modernisation / SHA pinning)
on the `3.10.x` branch, and implements the spirit of PR #104 (remove redundant `symfony/polyfill-php82`).
It also creates a `4.4.0 - DBAL 4 Migration` milestone and assigns the dormant DBAL-4 forward-compat
issues (#99-#102) to it, and closes the now-superseded issue #103.

All changes target `3.10.x` exclusively. The dormant `4.4.x` branch is untouched.

[Types]
No new PHP types are introduced; changes are purely to configuration and CI files.

No type changes. Only configuration mutations in `composer.json` and YAML workflow files.

[Files]
Three composer/CI files are modified in three atomic commits.

- **composer.json** (modified)
  - Remove `symfony/polyfill-php82` from `require`
  - Bump `ext-firebird` constraint from `^10.3.2` to `^10.6`
  - Bump `satwareag/php-firebird-stubs` from `^10.3.2` to `^10.6`
- **.github/workflows/ci.yml** (modified)
  - Replace all 6 occurrences of `v10.3.9` with `v10.6.2`
  - Bump quality-checks cache key suffix `quality-v2` → `quality-v3`
  - Pin all action tags to immutable SHA digests
- **.github/workflows/codeql.yml** (modified)
  - Pin `actions/checkout` and `github/codeql-action` SHAs
- **.github/workflows/windows.yml** (modified)
  - Pin all action SHAs
  - Add Chocolatey retry loop
  - Pin Firebird server version to `3.0.13`
  - Update php-firebird DLL version to `v10.6.2`

[Functions]
No PHP source functions are changed.

No function modifications. All changes are in YAML/JSON configuration files.

[Classes]
No PHP classes are changed.

No class modifications required.

[Dependencies]
Update php-firebird pinning from v10.3.9 to v10.6.2 and clean up one redundant polyfill.

- `symfony/polyfill-php82` REMOVED from `require` (redundant with `php: ^8.2` minimum)
- `ext-firebird` constraint: `^10.3.2` → `^10.6`
- `satwareag/php-firebird-stubs` (dev): `^10.3.2` → `^10.6`
- GitHub Actions SHA pins (see implementation order for exact values)

[Testing]
No new tests required; existing test matrix validates the upgrade.

- Trigger `workflow_dispatch` on `ci.yml` after push to confirm matrix green
- Windows matrix validates DLL download approach
- Quality-checks job validates PHPStan still passes with v10.6.2

[Implementation Order]
Three sequential commits, then GitHub issue/milestone housekeeping.

1. **Commit 1** `chore(deps): remove redundant symfony/polyfill-php82`
   - Edit `composer.json`: remove `"symfony/polyfill-php82": "^1.33"` line
   - Run `composer update symfony/polyfill-php82 --no-interaction` to clean lockfile
   - Git add `composer.json composer.lock`, commit, close PR #104

2. **Commit 2** `feat(ci): upgrade php-firebird to v10.6.2, bump ext-firebird constraint`
   - Edit `composer.json`: `ext-firebird ^10.6`, stubs `^10.6`
   - Edit `ci.yml`: replace 6× `v10.3.9` → `v10.6.2`; cache key `quality-v3`
   - Edit `windows.yml`: update DLL version to `v10.6.2`
   - Git add, commit, reference closes #105

3. **Commit 3** `chore(ci): pin GitHub Actions to immutable SHA digests`
   - Edit `ci.yml`, `codeql.yml`, `windows.yml`: replace mutable tags with SHAs
     - `actions/checkout@v5` → `@93cb6efe18208431cddfb8368fd83d5badbf9bfd`
     - `shivammathur/setup-php@v2` → `@728c6c6b8cf02c2e48117716a91ee48313958a19`
     - `actions/cache@v4` → `@0057852bfaa89a56745cba8c7296529d2fc39830`
     - `actions/upload-artifact@v4` → `@ea165f8d65b6e75b540449e92b4886f43607fa02`
     - `codecov/codecov-action@v5` → `@75cd11691c0faa626561e295848008c8a7dddffe`
     - `github/codeql-action/{init,analyze}@v4` → `@d4b3ca9fa7f69d38bfcd667bdc45bc373d16277e`
   - Add Chocolatey retry loop + Firebird version pin (`3.0.13`) in `windows.yml`
   - Git add, commit, reference closes #106

4. **GitHub housekeeping** (gh CLI, no code changes)
   - Create milestone `4.4.0 - DBAL 4 Migration` on doctrine-firebird-driver
   - Assign issues #99, #100, #101, #102 to milestone
   - Close #103 (superseded by #105 upgrade)
   - Comment on PR #104 and close it (implemented on 3.10.x directly)
   - File issue on `satwareAG/php-firebird`: publish v10.6.2 release (currently draft)
