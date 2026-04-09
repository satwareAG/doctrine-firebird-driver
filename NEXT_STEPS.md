# Next Steps - doctrine-firebird-driver

**Last session:** 2026-04-09
**Branch:** `4.4.x` | **Status:** CI GREEN - DBAL4 migration complete
**Target:** `doctrine/dbal` v4.4.3 | **PHP:** 8.2 / 8.3 / 8.4 / 8.5 | **Firebird:** 3.0 / 4.0 / 5.0

---

## Current State (4.4.x branch)

### CI Status

**All jobs green** as of CI run `24192492873` (commit `e5bc55e`).

### Commits (latest first, this session)

| Hash | Description |
|------|-------------|
| e5bc55e | fix: update testLastInsertIdSequence for DBAL4 (no $name in lastInsertId) |
| e85d91e | fix: skip BatchTest gracefully when Firebird\Batch class is unavailable |
| 1f5b4ca | fix: skip bool→string normalisation in comparator for integer-type columns |
| 99dde2f | fix: narrow bool→string conversion in getDefaultValueDeclarationSQL to non-integer/non-boolean columns |
| c9fefc9 | fix: remove stale psalm baseline entry for getDefaultValueDeclarationSQL |
| 1794b91 | fix: multiple DBAL4 compatibility issues (phpcs, StatementTest, TypeError) |
| d67f777 | fix(dbal4): fix DBAL4 test compatibility - TableDiff, ForeignKeyConstraint, inline comments, keywords |

### Fixes Applied (this session)

- [x] `getDefaultValueDeclarationSQL` TypeError for bool defaults - narrow conversion to non-integer/non-boolean types only
- [x] `FirebirdComparator::normalizeColumn` bool→string conversion skips integer types (prevents `DEFAULT 0` in SQL)
- [x] `BatchTest` now guards `class_exists('Firebird\Batch')` - graceful skip on php-firebird < v7.0.0
- [x] `testLastInsertIdSequence` - DBAL4 dropped `$name` from `lastInsertId()`; use `GEN_ID(seq, 0)` directly

### Key Technical Decisions (DBAL4)

- `getDefaultValueDeclarationSQL`: Only convert bool→string for non-integer, non-boolean column types.
  DBAL4 handles `PhpIntegerMappingType` via string concatenation (no TypeError) and `BooleanType` via `convertBooleans()`.
- `FirebirdComparator::normalizeColumn`: Skip bool→string for integer types because normalized values
  flow into `TableDiff` SQL generation - converting `false`→`'0'` causes `DEFAULT 0` instead of `DEFAULT `.
- `Connection::lastInsertId()` in DBAL4 has no `$name` parameter. Use `GEN_ID(seq, 0)` for sequence lookups.

---

## All Fixes Applied (DBAL4 migration - complete)

- [x] `Statement::bindParam()` removed - replaced with `bindValue()` in tests
- [x] `Connection::getWrappedConnection()` removed - added `getFirebirdDriverConnection()` to `ConnectionWrapper`
- [x] `Type::getName()` removed - removed from PHPUnit mocks
- [x] `TableDiff::getName()` / `getNewName()` removed - replaced with `getOldTable()`
- [x] `TableDiff` public properties removed - replaced with accessor methods
- [x] `setNestTransactionsWithSavepoints(false)` throws `InvalidArgumentException`
- [x] `ForeignKeyConstraint::__construct()` requires `string` name
- [x] `getInlineColumnCommentSQL()` throws `NotSupported` - tests skip properly
- [x] `getReservedKeywordsClass()` removed - use `getReservedKeywordsList()`
- [x] `TrimMode` is enum in DBAL4 - test updated
- [x] `isCommentedDoctrineType()` removed from AbstractPlatform
- [x] `getColumnComment()` removed - use `Column::getComment()`
- [x] DBAL4 `Types::ARRAY` / `Types::OBJECT` removed from tests
- [x] `getDefaultValueDeclarationSQL` TypeError for bool defaults
- [x] `FirebirdComparator::normalizeColumn` integer type bool normalisation
- [x] `BatchTest` graceful skip when `Firebird\Batch` class unavailable
- [x] `testLastInsertIdSequence` DBAL4 `lastInsertId()` API change

---

## Next Work

### Suggested (no blockers)

1. **Merge `4.4.x` → `main`** - DBAL4 migration is stable with full CI coverage
2. **Tag release** - First stable DBAL4 release (e.g. `v4.0.0`)
3. **Update README** - Document DBAL4 requirement (`doctrine/dbal: ^4.4`)
4. **CHANGELOG** - Add DBAL4 migration section

---

## DBAL 3 series (3.10.x branch) - COMPLETE

The `3.10.x` branch is in maintenance mode (critical fixes only).
- Last release: `v3.12.2` (2026-03-18)

---

## Architecture Decisions

- `ConnectionWrapper::getFirebirdDriverConnection()` replaces DBAL3's `getWrappedConnection()`
- All keyword lists implement `createReservedKeywordsList()` (DBAL4 abstract method)
- PHPUnit 11 mocks must NOT configure methods that don't exist on the class being mocked
- `TableDiff` in DBAL4: `new TableDiff(Table $oldTable, ...)` - no mock needed for empty diff tests
- `Connection::lastInsertId()` DBAL4: no `$name` param - use platform SQL for sequence lookups
