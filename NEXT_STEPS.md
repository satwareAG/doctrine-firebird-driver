# Next Steps - doctrine-firebird-driver

**Last session:** 2026-04-08
**Branch:** `4.4.x` | **Status:** Active CI Hardening
**Target:** `doctrine/dbal` v4.4.3 | **PHP:** 8.2 / 8.3 / 8.4 / 8.5 | **Firebird:** 3.0 / 4.0 / 5.0

---

## Current State (4.4.x branch)

### Commits (latest first)

| Hash | Description |
|------|-------------|
| d67f777 | fix(dbal4): fix DBAL4 test compatibility - TableDiff, ForeignKeyConstraint, inline comments, keywords |
| 234d8e3 | fix(dbal4): remove DBAL3-only APIs and fix DBAL4 incompatibilities |
| fbb4ba0 | fix: remove DBAL4-incompatible platform method calls in tests |
| aafc450 | fix: remove DBAL4-incompatible Types::ARRAY/OBJECT references |
| dc0a581 | fix: replace Table objects with ->getName() in addForeignKeyConstraint calls |
| 4953075 | fix: replace getColumnComment() with Column::getComment(), update Psalm baseline |
| cecefd0 | fix: DBAL 4.4.x compatibility - remove isCommentedDoctrineType, fix scale/precision |
| f1f9765 | ci: add 4.4.x branch to push/pull_request triggers |

### Fixes Applied (DBAL4 migration)

- [x] `Statement::bindParam()` removed - replaced with `bindValue()` in tests
- [x] `Connection::getWrappedConnection()` removed - added `getFirebirdDriverConnection()` to `ConnectionWrapper`
- [x] `Type::getName()` removed - removed from PHPUnit mocks (MethodCannotBeConfiguredException fix)
- [x] `TableDiff::getName()` / `TableDiff::getNewName()` removed - replaced with `getOldTable()`
- [x] `TableDiff` public properties removed - replaced with accessor methods
- [x] `setNestTransactionsWithSavepoints(false)` throws `InvalidArgumentException` (not deprecation)
- [x] `ForeignKeyConstraint::__construct()` requires `string` name (not `?string`)
- [x] `getInlineColumnCommentSQL()` throws `NotSupported` when unsupported - tests skip properly
- [x] `getReservedKeywordsClass()` removed - tests updated to use `getReservedKeywordsList()`
- [x] `TrimMode` is now an enum in DBAL4 - test updated
- [x] `isCommentedDoctrineType()` removed from AbstractPlatform
- [x] `getColumnComment()` removed - replaced with `Column::getComment()`
- [x] DBAL4 `Types::ARRAY` / `Types::OBJECT` removed from tests

---

## Remaining CI Work

### Known Failures (from last CI run)

The most recent fixes address root causes found in CI run `24086278038`. After pushing
commit d67f777, CI should re-run automatically. Monitor results at:
https://github.com/satwareAG/doctrine-firebird-driver/actions

### If CI still shows failures

Look for these patterns in CI logs:
1. **`MethodCannotBeConfiguredException`** - another mock configuring a non-existent method
2. **`Error: Call to undefined method`** - DBAL3 method still used somewhere
3. **`TypeError`** - signature mismatch between our code and DBAL4 interfaces
4. **`NotSupported` exception** - calling unsupported platform operation that now throws

Use the debug cycle:
```bash
# Run specific test class locally (requires Docker Firebird)
cd tests && docker-compose up -d
vendor/bin/phpunit tests/Test/Unit/Platforms/FirebirdPlatformTest.php --no-coverage
```

---

## DBAL 3 series (3.10.x branch) - COMPLETE

The `3.10.x` branch is in maintenance mode (critical fixes only).
- Last release: `v3.12.2` (2026-03-18)

---

## Architecture Decisions

- `ConnectionWrapper::getFirebirdDriverConnection()` replaces DBAL3's `getWrappedConnection()`
- All keyword lists now implement `createReservedKeywordsList()` (DBAL4 abstract method)
- PHPUnit 11 mocks must NOT configure methods that don't exist on the class being mocked
- `TableDiff` in DBAL4 is constructed with `new TableDiff(Table $oldTable, ...)` - no mock needed for empty diff tests
