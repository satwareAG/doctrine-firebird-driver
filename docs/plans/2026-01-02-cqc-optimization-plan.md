# CQC Optimization Plan

**Date**: 2026-01-02
**Status**: Active
**GitHub Issues**: #54, #55, #56

---

[Overview]
Optimize docker-cqc.sh script and fix driver issues discovered during QA run.

This plan addresses three categories of issues discovered during the 2026-01-02 QA run:
1. Script false positives (PHPStan SIGSEGV reported as PASSED)
2. Test failures (AlbumTest transaction handle error)
3. Code quality improvements (Psalm auto-fixes)

All work is tracked via GitHub issues for global visibility.

[Types]
No new types required for this optimization.

All changes are to existing functions and shell scripts. The driver's type system remains unchanged.

[Files]
Files to modify for script optimization and driver fixes.

**Script Modifications:**
- `tests/docker-cqc.sh` - Fix PHPStan false positive detection (Issue #54)

**Driver Modifications:**
- `tests/Test/Integration/ReadOnlyIntegrationTestCase.php` - Fix transaction handling (Issue #55)
- Various source files - Psalm auto-fixes (Issue #56)

**No new files required.**

[Functions]
Function modifications for proper error detection.

**docker-cqc.sh Modifications:**
- `run_phpstan()` - Add exit code 139 detection, disable parallel mode
- `run_in_docker()` - Capture and return accurate exit codes

**Driver Modifications:**
- `ReadOnlyIntegrationTestCase::setUp()` - Ensure proper transaction state
- Various methods - Psalm auto-fixes for unused variables and docblock mismatches

[Classes]
No class-level modifications required.

All changes are at the function/method level. No new classes, no class renames, no inheritance changes.

[Dependencies]
No dependency changes required.

Current dependencies are sufficient. The php-firebird extension SIGSEGV is tracked separately in satwareAG/php-firebird#50 and #51.

[Testing]
Verification approach for all changes.

**For Issue #54 (docker-cqc.sh):**
1. Run `./tests/docker-cqc.sh --quick` and verify PHPStan reports FAILED/INCOMPLETE on SIGSEGV
2. Verify exit code is non-zero when severe errors occur

**For Issue #55 (AlbumTest):**
1. Run `vendor/bin/phpunit --filter AlbumTest`
2. Verify testSelectWithHaving passes

**For Issue #56 (Psalm):**
1. Run Psalm auto-fix dry-run first
2. Run full test suite after fixes
3. Verify no regressions

[Implementation Order]
Ordered implementation sequence.

1. **Issue #54**: Fix docker-cqc.sh PHPStan false positive
   - Modify `run_phpstan()` to check for exit code 139
   - Add `--jobs 1` to disable parallel mode (workaround)
   - Add grep check for "severe errors" in output

2. **Issue #56**: Apply Psalm auto-fixes
   - Run dry-run to preview changes
   - **REJECTED**: Psalm wanted to delete 200+ lines of public API methods (PossiblyUnusedMethod)
   - These are intentionally public API methods, not unused code
   - Only safe fix applied: UnusedVariable ($success = true ’ $success = false) in rollBack()
   - Issue #56 should be closed as "won't fix" for the PossiblyUnusedMethod issues

3. **Issue #55**: Fix AlbumTest transaction error
   - Investigate ReadOnlyIntegrationTestCase transaction setup
   - Ensure transaction is started before query execution
   - Run full test suite

---

## GitHub Issue References

| Issue | Title | Priority |
|-------|-------|----------|
| #54 | docker-cqc.sh: False positive on PHPStan SIGSEGV | High |
| #55 | AlbumTest::testSelectWithHaving: Invalid transaction handle | High |
| #56 | Apply Psalm auto-fixes for 18 type issues | Medium |

## Related php-firebird Issues

| Issue | Title | Status |
|-------|-------|--------|
| #50 | SIGSEGV in php-firebird RC | Open |
| #51 | Root cause: EG() access during MSHUTDOWN | Open |