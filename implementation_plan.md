# Implementation Plan: Phase 4 - Remove Redundant @ Suppressions

Continue quality improvements from `docs/audit-code-quality-2026-03.md` on branch `001-quality-improvements`.

## [Overview]
Remove all 23 redundant `@` suppression operators from driver code, keeping only the existing `try-catch(Throwable)` blocks for error handling.

The audit identified that every `@` suppression is paired with `try-catch(Throwable)`, making `@` completely redundant. The `@` operator suppresses `E_WARNING`/`E_NOTICE` but `try-catch` already catches exceptions. Removing `@` unmasks hidden warnings without changing error behavior.

## [Types]
No type system changes in this phase.

## [Files]

**Modified files:**
- `src/Driver/Firebird/Statement.php` - Remove `@` from lines 245, 297
- `src/Driver/Firebird/Result.php` - Remove `@` from lines 84, 109, 236, 281
- `src/Driver/Firebird/Driver.php` - Remove `@` from lines 64, 86, 88
- `src/Driver/Firebird/Connection.php` - Remove `@` from lines 201, 203, 209, 318, 431, 436, 446, 483, 487, 496, 536, 583, 636, 663, 690, 1040
- `src/Schema/FirebirdSchemaManager.php` - Remove `@` from lines 126, 155

## [Functions]
Each function: remove `@` prefix from `fbird_*` calls, keep surrounding `try-catch` unchanged.

**Statement.php:**
- `execute()` - `@fbird_execute` → `fbird_execute`
- `advance()` - `@fbird_fetch_assoc` → `fbird_fetch_assoc`

**Result.php:**
- `fetchOne()` - `@fbird_fetch_row` → `fbird_fetch_row`
- `fetchAssociative()` - `@fbird_fetch_assoc` → `fbird_fetch_assoc`
- `fetchNumeric()` - `@fbird_fetch_row` → `fbird_fetch_row`
- `fetchAssociativeWithDateObjects()` - `@fbird_fetch_assoc` → `fbird_fetch_assoc`

**Driver.php:**
- `connect()` - `@fbird_service_attach` → `fbird_service_attach`
- `connect()` - `@fbird_pconnect` → `fbird_pconnect`
- `connect()` - `@fbird_connect` → `fbird_connect`

**Connection.php:** All `@fbird_*` calls → `fbird_*` (16 occurrences across commit, rollback, prepare, savepoint methods)

**FirebirdSchemaManager.php:**
- `_getPortableTableColumnDefinition()` - 2 `@` removals

## [Classes]
No class changes.

## [Dependencies]
No dependency changes.

## [Testing]
- Run `vendor/bin/phpstan analyse` - must pass with 0 errors
- Run `vendor/bin/psalm` - must pass with 0 errors
- Existing functional tests remain the safety net (require Docker/Firebird)

## [Implementation Order]
1. Remove `@` from Statement.php (2 occurrences)
2. Remove `@` from Result.php (4 occurrences)
3. Remove `@` from Driver.php (3 occurrences)
4. Remove `@` from Connection.php (16 occurrences)
5. Remove `@` from FirebirdSchemaManager.php (2 occurrences)
6. Run PHPStan and Psalm to verify no regressions
7. Commit: `refactor(error-handling): remove redundant @ suppressions, keep try-catch`