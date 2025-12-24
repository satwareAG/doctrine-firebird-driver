# Implementation Plan

[Overview]
Resolve all 12 open GitHub issues using a Foundation First approach, starting with ext-firebird v7.0.0-rc.7 upgrade.

This plan addresses issues in dependency order to avoid conflicts and maximize code reuse. The php-firebird v7.0.0-rc.7 upgrade (#36) enables TIME fixes (#33), Exception Mode API (#35), and fork-safety detection (#37). Deprecation cleanup (#24) and obsolete issue consolidation complete the work.

Key observations from codebase analysis:
- Issue #23 (transaction deadlock) is ALREADY FIXED - Connection.php uses `fbird_trans_start()` with proper options array
- Issue #35 (Exception Mode) is partially implemented - `fbird_set_exception_mode()` called in Connection constructor
- Issues #28, #29, #34 are superseded by #36 (ext-firebird upgrade)

[Types]
No new type definitions required for this implementation.

Existing types remain unchanged:
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection` - Main connection class
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement` - Statement with bindParam deprecation
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter` - May be enhanced for SQLSTATE

[Files]
Files will be modified to address the open issues.

**Modified Files:**
1. `composer.json` - Update ext-firebird constraint from `"*"` to `"^7.0.0-rc.7"`
2. `src/Driver/Firebird/Connection.php` - Add fork-safety documentation and detection enhancements
3. `src/Driver/Firebird/ExceptionConverter.php` - Optional: Add SQLSTATE support from Firebird\Exception
4. `tests/Test/Functional/TypeConversionTest.php` - Enable TIME test cases (remove skips)
5. `README.md` - Add fork-safety documentation section
6. `CHANGELOG.md` - Document all changes for next release

**Files to Review (for issue closure):**
- `docs/issues/2025-11-25-transaction-deadlock-fix-plan.md` - Verify #23 is resolved
- `docs/plans/2025-12-22-php-firebird-v7-integration.md` - Reference for extension features

[Functions]
No new functions required. Minor modifications to existing functions.

**Connection.php Enhancements:**
- `isConnectionValid()` - Already detects 'Unknown' resource type (fork invalidation)
- `isTransactionValid()` - Already detects 'Unknown' resource type
- Add docblock comments explaining fork-safety behavior

**ExceptionConverter.php (Optional Enhancement):**
- `convert()` - Could leverage `Firebird\Exception::getSqlState()` for better error classification
- Current implementation already handles exception conversion adequately

[Classes]
No new classes required. Minor enhancements to existing classes.

**Connection class enhancements:**
- Path: `src/Driver/Firebird/Connection.php`
- Add fork-safety documentation to class docblock
- Add constants documenting fork-detection behavior
- Methods `isConnectionValid()` and `isTransactionValid()` already handle invalid resources

**Statement class cleanup (Issue #24):**
- Path: `src/Driver/Firebird/Statement.php`
- `bindParam()` already has deprecation trigger - mark for removal in DBAL 4.x
- No code changes needed, just documentation update

[Dependencies]
Single dependency constraint update required.

**composer.json changes:**
```json
"require": {
    "ext-firebird": "^7.0.0-rc.7"  // Changed from "*"
}
```

This enables:
- TIME encoding fix (v7.0.0-rc.3+)
- Exception Mode API (v7.0.0-rc.6+)
- Fork-safety resource invalidation (v7.0.0-rc.7)
- Alias padding fix for metadata operations

[Testing]
Enable previously skipped TIME-related tests and verify all existing tests pass.

**Test files to modify:**
- `tests/Test/Functional/TypeConversionTest.php` - Enable TIME test cases

**Verification steps:**
1. Run full test suite: `vendor/bin/phpunit`
2. Run PHPStan: `vendor/bin/phpstan analyse src/ --level=8`
3. Run PHPCS: `vendor/bin/phpcs`
4. Verify TIME tests no longer skipped

**Test coverage targets:**
- Maintain or improve existing coverage (≥80%)
- All TIME-related tests should pass with ext-firebird v7.0.0-rc.7

[Implementation Order]
Execute changes in this order to minimize conflicts and validate at each step.

1. **Issue #36 - ext-firebird upgrade** (5 min)
   - Update composer.json: `"ext-firebird": "^7.0.0-rc.7"`
   - Run `composer validate`

2. **Issue #33 - Enable TIME tests** (15 min)
   - Search for TIME-related skip markers in tests
   - Remove skip conditions where TIME fix applies
   - Run PHPUnit to verify tests pass

3. **Issue #35 - Exception Mode verification** (10 min)
   - Verify `fbird_set_exception_mode()` is called in Connection constructor
   - Already implemented - just verify and document

4. **Issue #37 - Fork-safety documentation** (20 min)
   - Add docblock comments to Connection.php explaining fork behavior
   - Update README.md with fork-safety section
   - Add test case if pcntl extension available (optional)

5. **Issue #24 - bindParam documentation** (5 min)
   - Document removal timeline in CHANGELOG
   - No code changes needed - deprecation already in place

6. **Close obsolete issues** (5 min)
   - Close #28, #29, #34 as superseded by #36
   - Close #23 as already fixed (verify in git history)

7. **Update CHANGELOG.md** (10 min)
   - Document ext-firebird v7.0.0-rc.7 requirement
   - Document TIME test enablement
   - Document fork-safety support
   - Prepare for v3.11.0 release

8. **Final verification** (15 min)
   - Run full test suite
   - Run static analysis (PHPStan, Psalm)
   - Run code style checks (PHPCS)
   - Verify all open issues can be closed
