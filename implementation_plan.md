# Implementation Plan: Quality Improvements

Continue quality improvements from `docs/audit-code-quality-2026-03.md` on branch `001-quality-improvements`.

---

## ✅ Phase 4 - Remove Redundant @ Suppressions (COMPLETE)
Remove all redundant `@` suppression operators from driver code, keeping only the existing `try-catch(Throwable)` blocks for error handling.

- **Status**: COMPLETE
- **Outcome**: 0 `@fbird_` suppressions in codebase. Static analysis green. Unit tests green.

---

## 🚀 Phase 5 - Modernize Resource Type Guards (TODO)

Replace legacy `is_resource()` checks and `get_resource_type()` string comparisons with modern patterns or helper methods to improve type safety and prepare for the Firebird extension's eventual move to object-based resources.

### [Overview]
The driver relies heavily on `is_resource()` which provides no information about the *type* of resource. Some comparisons also use `get_resource_type() === 'firebird result'`, which is fragile across extension versions. We will consolidate these checks.

### [Files to Modify]
- `src/Driver/Firebird/Connection.php`
- `src/Driver/Firebird/Statement.php`
- `src/Driver/Firebird/Result.php`

### [Implementation Steps]
1.  **Consolidate Resource Checks**: Ensure all resource checks in `Connection.php` use `isConnectionValid()` or `isTransactionValid()`.
2.  **Modernize Type Checks**: Replace literal string comparisons for resource types with class constants.
3.  **Improve Property Typing**: Update `@var` annotations to be more specific where possible.
4.  **Verify**: Run PHPStan (Level 8) and Psalm to ensure no type regressions.
5.  **Test**: Run unit and functional tests.

### [Success Criteria]
- Reduced number of raw `is_resource()` calls in favor of semantic methods.
- No literal string comparisons for `get_resource_type()`.
- Static analysis remains clean.
