# Implementation Plan: Psalm Firebird\Exception Stub Resolution

## [Overview]
Replace Psalm suppressions for `Firebird\Exception` UndefinedClass errors with a proper stub definition. This resolves static analysis errors by teaching Psalm about the php-firebird v7.0.0 Exception Mode API through complete type stubs rather than error suppression.

The php-firebird v7.0.0-rc.6+ extension introduces Exception Mode where Firebird functions throw `Firebird\Exception` objects instead of returning false. Our code uses this class in two places (Exception.php and ExceptionConverter.php), but Psalm doesn't know it exists because it's not defined in the stubs. Adding the proper stub class will eliminate the need for suppressions and reduce baseline noise.

This follows 2025 Psalm best practices: prefer complete type information over suppressions, use stubs for runtime-only classes, maintain accurate type declarations, and minimize baseline usage to only truly unavoidable issues.

## [Types]
Add `Firebird\Exception` class definition to the OO stubs.

**New Class in `stubs/FirebirdOO.php`:**
```php
namespace Firebird;

/**
 * Exception thrown by Firebird API functions in Exception Mode (php-firebird v7.0.0+).
 *
 * When Exception Mode is enabled, Firebird API functions throw this exception
 * instead of returning false on errors. Provides SQLSTATE codes and detailed
 * error information for better error handling.
 *
 * @since php-firebird 7.0.0-rc.6
 */
class Exception extends \Exception
{
    /**
     * Get the 5-character SQLSTATE code for this error.
     *
     * SQLSTATE codes provide standardized SQL:2003 error classification:
     * - "08006" = Connection failure
     * - "23000" = Integrity constraint violation
     * - "42000" = Syntax error or access violation
     * - "40001" = Deadlock
     *
     * @return string|null SQLSTATE code or null if not available
     */
    public function getSqlState(): ?string {}
    
    /**
     * Get the Firebird-specific error code.
     *
     * @return int Firebird error code (e.g., -803 for unique constraint violation)
     */
    public function getCode(): int {}
    
    /**
     * Get the error message.
     *
     * @return string Human-readable error description
     */
    public function getMessage(): string {}
}
```

## [Files]
Modify 3 existing files: stub enhancement, psalm config cleanup, docblock improvement.

**Files to modify:**

1. **`stubs/FirebirdOO.php`** (line ~410, after `DbInfo` class)
   - Add `Firebird\Exception` class definition with `getSqlState()` method
   - Include comprehensive PHPDoc explaining Exception Mode
   - Position after existing OO wrapper classes

2. **`psalm.xml.dist`** (lines 52-57, `<UndefinedClass>` block)
   - REMOVE the entire `<UndefinedClass>` suppression block we just added
   - This was the temporary fix - proper stub makes it unnecessary

3. **`src/Driver/Firebird/Exception.php`** (line 82, `@phpstan-param` docblock)
   - Update `@phpstan-param` from `Throwable` to `\Firebird\Exception`
   - This makes the docblock match the actual parameter type
   - Resolves the `MismatchingDocblockParamType` baseline entry

**No files to create or delete.**

## [Functions]
No function signature changes - only stub additions and docblock updates.

**Stub functions added:**
- `Firebird\Exception::getSqlState(): ?string` - Returns SQLSTATE code
- `Firebird\Exception::getCode(): int` - Returns Firebird error code (override parent)
- `Firebird\Exception::getMessage(): string` - Returns error message (override parent)

**Existing functions unchanged:**
- `Exception::fromFirebirdException()` - Parameter type already correct, docblock will be updated
- All other functions remain unchanged

## [Classes]
Add one stub class, refine one existing class docblock.

**New stub class:**
- `Firebird\Exception` in `stubs/FirebirdOO.php`
  - Extends `\Exception` (PHP base exception)
  - Adds `getSqlState()` method specific to Firebird
  - Overrides `getCode()` and `getMessage()` for clarity
  - Comprehensive PHPDoc explaining Exception Mode

**Modified class:**
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception`
  - Update `@phpstan-param` docblock on line 82
  - No code changes, only documentation

## [Dependencies]
No dependency changes required.

**Current versions (unchanged):**
- `vimeo/psalm`: ^5.0 (currently 5.26.1)
- `psalm/plugin-phpunit`: ^0.18

**Stub files:**
- `stubs/FirebirdOO.php` - Enhanced with new class
- `stubs/FirebirdStub.php` - Unchanged (procedural API stubs)
- Loaded via `psalm.xml.dist` `<stubs>` section (already configured)

## [Testing]
Validate with Psalm static analysis and baseline comparison.

**Validation steps:**

1. **Run Psalm with baseline update**:
   ```bash
   cd tests
   docker compose run --rm app vendor/bin/psalm --set-baseline=psalm-baseline.xml --no-cache
   ```
   Expected: Baseline should have FEWER entries (MismatchingDocblockParamType removed)

2. **Run Psalm analysis**:
   ```bash
   docker compose run --rm app vendor/bin/psalm --no-cache --show-info=false
   ```
   Expected: No `UndefinedClass` errors for `Firebird\Exception`

3. **Quick mode validation**:
   ```bash
   ./docker-cqc.sh --quick
   ```
   Expected: PHP_CodeSniffer, PHPStan, and Psalm all pass

4. **Baseline verification**:
   ```bash
   grep -A 3 "src/Driver/Firebird/Exception.php" psalm-baseline.xml
   ```
   Expected: Should show ONLY `PossiblyUnusedMethod` for `fromFirebirdException`, NOT `MismatchingDocblockParamType` or `UndefinedClass`

**Test success criteria:**
- Psalm analysis passes with 0 errors
- No `UndefinedClass` suppressions in `psalm.xml.dist`
- Baseline reduced by 1 entry (MismatchingDocblockParamType removed)
- `fromFirebirdException` still shows as `PossiblyUnusedMethod` (acceptable - it's called dynamically from ExceptionConverter)

## [Implementation Order]
Execute changes in dependency order to avoid intermediate broken states.

**Step-by-step sequence:**

1. **Add Firebird\Exception stub** (`stubs/FirebirdOO.php`)
   - Impact: Teaches Psalm about the class
   - Validation: Psalm should recognize `\Firebird\Exception` type

2. **Update docblock** (`src/Driver/Firebird/Exception.php`)
   - Change `@phpstan-param Throwable` to `@phpstan-param \Firebird\Exception`
   - Impact: Aligns docblock with actual parameter type
   - Validation: PHPStan should still pass, Psalm should be happier

3. **Remove UndefinedClass suppression** (`psalm.xml.dist`)
   - Delete entire `<UndefinedClass>` block (lines 52-57)
   - Impact: Forces Psalm to use stub instead of suppression
   - Validation: Psalm analysis should still pass without suppression

4. **Update Psalm baseline** (via command)
   - Run: `docker compose run --rm app vendor/bin/psalm --set-baseline=psalm-baseline.xml --no-cache`
   - Impact: Removes MismatchingDocblockParamType entry
   - Validation: Baseline should be smaller, only essential entries remain

5. **Full validation** (via docker-cqc.sh)
   - Run: `./docker-cqc.sh --quick`
   - Impact: Confirms all static analysis tools pass
   - Validation: Green checkmarks for all quality checks

**Critical sequencing notes:**
- Must add stub BEFORE removing suppression (otherwise Psalm temporarily fails)
- Must update docblock BEFORE baseline update (otherwise baseline update won't help)
- Baseline update should be LAST (cleans up after all fixes applied)

---

**Implementation agent notes:**
- Use section extraction commands to read specific sections when implementing
- Follow Baby Steps™: one file per commit, validate after each change
- Do NOT suppress errors - this plan REMOVES suppressions by fixing root cause
- If Psalm still complains after stub addition, verify stub syntax and namespace
