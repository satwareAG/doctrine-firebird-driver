# Implementation Plan

[Overview]
Fix the failing CI pipeline on `4.4.x` and then resolve all four open DBAL4 migration issues to close the `4.4.0 - DBAL 4 Migration` milestone.

## Context

The `4.4.x` branch carries our DBAL4 migration commit (`05bc513`) but CI is not green for two reasons:

1. **PHPCS fails** - 52 style errors across 12 files (47 auto-fixable via `phpcbf`, 5 manual). These exist partly from long-standing technical debt and partly from our new DBAL4 code (Keywords files, Connection.php imports).
2. **`4.4.x` is not in the CI push-trigger list** - `.github/workflows/ci.yml` only triggers on `3.10.x`, `3.0.*`, `main`, `feature/*`. The branch needs adding to both `push.branches` and `pull_request.branches`.

PHPUnit test failures on the old `f7de7ac` commit were caused by the pre-DBAL4-fix code; commit `05bc513` fixes those. After CI is triggered for `05bc513`, PHPUnit tests should pass (710 tests green locally).

After the CI gate is green, four open issues in the `4.4.0 - DBAL 4 Migration` milestone remain:
- **#99** Replace `getWrappedConnection()` with `getNativeConnection()`
- **#100** Update `ServerInfoAwareConnection` interface implementation
- **#101** Adapt `AbstractSchemaManager` constructor changes
- **#102** Remove `Type::getName()` usage

## Why this matters

The project needs a fully green CI before declaring v4.4.0 production-ready. The milestone has 4 open issues that represent functional API correctness, not just style. Completing all of them with a green CI enables closing the `4.4.0` milestone and releasing v4.4.1 (or re-tagging v4.4.0 as stable).

[Types]
No new types or interfaces are introduced; existing method signatures are corrected to match DBAL4 contracts.

### Key type changes (Phase 2):
- `Connection::getServerVersion()` - currently returns `string`; verify against DBAL4 `ServerInfoAwareConnection` (no interface change expected but must verify)
- `FirebirdSchemaManager::__construct()` - must accept DBAL4 `AbstractSchemaManager` parameter list (likely `Connection $connection, AbstractPlatform $platform` vs DBAL3's `Connection $connection`)
- `Type::getName()` replacement - use `Type::getTypeRegistry()->lookupName($type)` or `$type instanceof BooleanType` direct class checks

[Files]

Phase 1 files - CI fix (PHPCS + trigger):

- **`.github/workflows/ci.yml`** - Modified: add `4.4.x` to `push.branches` and `pull_request.branches` arrays
- **`src/Platforms/Keywords/FirebirdKeywords.php`** - Modified: add `@return array<string>` annotation to `getKeywords()` (line 16, non-auto-fixable)
- **`src/Platforms/Keywords/Firebird3Keywords.php`** - Modified: add `@return array<string>` annotation to `getKeywords()` (line 40, non-auto-fixable)
- **`src/Platforms/Keywords/Firebird4Keywords.php`** - Modified: add `@return array<string>` annotation to `getKeywords()` (line 35, non-auto-fixable)
- **`src/Platforms/Keywords/Firebird5Keywords.php`** - Modified: add `@return array<string>` annotation to `getKeywords()` (line 30, non-auto-fixable)
- **`src/Schema/FirebirdSchemaManager.php`** - Modified: fix doc comment style at line 236 (must use `/**` style, non-auto-fixable); remaining 5 errors are auto-fixable
- **`src/Platforms/FirebirdPlatform.php`** - Modified: fix "Use early exit" at line 1678 CHECK constraint block (non-auto-fixable); 19 other errors auto-fixable
- **`src/Driver/Firebird/Connection.php`** - Modified (auto-fix): remove unused imports, sort use statements, fix @inheritDoc
- **`src/Driver/Firebird/ConnectionWrapper.php`** - Modified (auto-fix): fix @inheritDoc
- **`src/Driver/FirebirdDriver.php`** - Modified (auto-fix): fix multi-line doc comment
- **`src/Platforms/Firebird3Platform.php`** - Modified (auto-fix): fix @inheritDoc

Phase 2 files - DBAL4 open issues:

- **`tests/Test/FunctionalTestCase.php`** - Modified (#99): replace `getWrappedConnection()` while-loop with DBAL4 `getNativeConnection()` unwrapping
- **`tests/Test/Integration/Satag/DoctrineFirebirdDriver/Driver/Firebird/ConnectionTest.php`** - Modified (#99): replace `getWrappedConnection()` calls (lines 25, 78) with `getNativeConnection()`
- **`src/Driver/Firebird/Connection.php`** - Modified (#100): verify/update `ServerInfoAwareConnection` interface or remove the interface if dropped in DBAL4
- **`src/Schema/FirebirdSchemaManager.php`** - Modified (#101): update constructor signature to match DBAL4 `AbstractSchemaManager`
- **`src/Schema/FirebirdSchemaManagerFactory.php`** - Modified (#101): update factory to pass correct constructor args
- **`src/Platforms/FirebirdPlatform.php`** - Modified (#102): replace any `Type::getName()` calls with class-based identification
- **`src/DBAL/FirebirdBooleanType.php`** - Modified (#102): remove `getName()` override if present
- **`CHANGELOG.md`** - Modified: document v4.4.1 or update v4.4.0 notes
- **`NEXT_STEPS.md`** - Modified: update status to reflect completed milestone

[Functions]

Phase 1 - add/fix doc annotations:

- **`FirebirdKeywords::getKeywords()`** - `src/Platforms/Keywords/FirebirdKeywords.php` line 14 - Add `/** @return array<string> */` annotation above method signature
- **`Firebird3Keywords::getKeywords()`** - `src/Platforms/Keywords/Firebird3Keywords.php` line 38 - Add `/** @return array<string> */` annotation
- **`Firebird4Keywords::getKeywords()`** - `src/Platforms/Keywords/Firebird4Keywords.php` line ~33 - Add `/** @return array<string> */` annotation
- **`Firebird5Keywords::getKeywords()`** - `src/Platforms/Keywords/Firebird5Keywords.php` line ~28 - Add `/** @return array<string> */` annotation
- **`FirebirdPlatform::_getCreateTableSQL()`** - `src/Platforms/FirebirdPlatform.php` ~line 1678 - Refactor `if (! empty($checkConstraints))` block to use early exit pattern

Phase 2 - DBAL4 API changes:

- **`FunctionalTestCase::getFirebirdConnection()`** - `tests/Test/FunctionalTestCase.php` line 177 - Replace `while (method_exists($connection, 'getWrappedConnection'))` loop with `getNativeConnection()` for DBAL4
- **`Connection::getServerVersion()`** - `src/Driver/Firebird/Connection.php` line 272 - Verify signature matches DBAL4's `ServerInfoAwareConnection`; update `implements` clause on class if interface was removed or renamed
- **`FirebirdSchemaManager::__construct()`** - `src/Schema/FirebirdSchemaManager.php` - Update parameter list to match DBAL4's `AbstractSchemaManager::__construct(Connection $connection, AbstractPlatform $platform)` (DBAL3 had `Connection $connection, AbstractPlatform $platform` too, but verify parameter types haven't changed to concrete classes)
- **Type identification in `FirebirdPlatform`** - `src/Platforms/FirebirdPlatform.php` - Replace any `$type->getName() === 'boolean'` with `$type instanceof BooleanType` or `Type::getTypeRegistry()->lookupName($type) === 'boolean'`

[Classes]

No new classes. Modifications only:

- **`Connection`** (`src/Driver/Firebird/Connection.php`) - Verify `implements \Doctrine\DBAL\Driver\Connection` is still correct and no additional DBAL4 interfaces are needed; check if `ServerInfoAwareConnection` was merged into `Driver\Connection` in DBAL4
- **`FirebirdSchemaManager`** (`src/Schema/FirebirdSchemaManager.php`) - Update constructor, keep `extends AbstractSchemaManager`
- **`FirebirdBooleanType`** (`src/DBAL/FirebirdBooleanType.php`) - Remove `getName()` method if present (DBAL4 removed this abstract method)

[Dependencies]
No new Composer dependencies required. All changes use already-required `doctrine/dbal: ^4.1` APIs.

The DBAL4 API to use for type lookup (if needed): `Doctrine\DBAL\Types\Type::getTypeRegistry()->lookupName($type)` or class-based `instanceof` checks. Both are available in `doctrine/dbal: ^4.1`.

[Testing]

Local verification before each push:

1. **PHPCS clean**: `vendor/bin/phpcs --report=json 2>/dev/null | jq '.totals'` must show `{"errors": 0, "warnings": 0, "fixable": 0}`
2. **PHPStan clean**: `vendor/bin/phpstan analyse src/ --level=8 --no-progress --memory-limit=1G`
3. **Unit tests** (no DB required): `cd tests && ../vendor/bin/phpunit --configuration phpunit.xml --no-coverage --filter "Unit" 2>&1 | tail -10 | cat`
4. **Platform tests** (no DB required): `cd tests && ../vendor/bin/phpunit --configuration phpunit.xml --no-coverage --testsuite Platforms 2>&1 | tail -10 | cat`

Remote CI verification:

- After push, monitor: `gh run list --branch 4.4.x --limit 3 --json status,conclusion,name,createdAt`
- Target: all jobs green including `Code Quality (PHP 8.4)`, `PHP 8.x / Firebird 3.0`, and `CI Summary`

[Implementation Order]

Ordered to minimize conflicts and allow incremental verification:

1. **Run phpcbf auto-fix** on all src/ files: `vendor/bin/phpcbf src/ tests/` - fixes 47 of 52 errors
2. **Manual fix**: Add `@return array<string>` annotations to all 4 Keywords `getKeywords()` methods
3. **Manual fix**: Fix `FirebirdPlatform::_getCreateTableSQL()` CHECK block to use early-exit (return early if empty)
4. **Manual fix**: Fix `FirebirdSchemaManager.php` line 236 doc comment to `/**` style
5. **Verify PHPCS clean**: `vendor/bin/phpcs --report=json 2>/dev/null | jq '.totals'`
6. **Add `4.4.x` to CI trigger**: edit `.github/workflows/ci.yml` push and pull_request branches list
7. **Run PHPStan**: verify still clean after phpcbf changes
8. **Commit Phase 1**: `fix(ci): fix all PHPCS errors and add 4.4.x to CI trigger`
9. **Push and monitor**: `git push origin 4.4.x` then `gh run watch`
10. **Issue #99**: Update `FunctionalTestCase.php` and Integration `ConnectionTest.php` - replace `getWrappedConnection()` with `getNativeConnection()` pattern
11. **Issue #100**: Investigate `ServerInfoAwareConnection` in DBAL4 vendor source; update `Connection.php` implements clause if needed
12. **Issue #101**: Investigate `AbstractSchemaManager` in DBAL4 vendor source; update `FirebirdSchemaManager` constructor and `FirebirdSchemaManagerFactory`
13. **Issue #102**: Search `src/` for `getName()` calls on Type objects; replace with class-based identification
14. **Run full local test suite** (unit + platform tests); verify PHPStan + PHPCS clean
15. **Commit Phase 2**: `fix(dbal4): resolve remaining DBAL4 migration issues #99 #100 #101 #102`
16. **Update CHANGELOG.md** with Phase 2 changes
17. **Close issues #99-#102** on GitHub; close milestone `4.4.0 - DBAL 4 Migration`
18. **Push and verify CI green**
