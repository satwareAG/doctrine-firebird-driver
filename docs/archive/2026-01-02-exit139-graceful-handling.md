# Implementation Plan

[Overview]
Fix phpunit.sh script to handle exit code 139 (SIGSEGV) gracefully when PHPUnit reports successful test results.

The script currently fails when PHP crashes during shutdown with SIGSEGV (exit code 139) even though all tests passed. This is caused by a known bug in the php-firebird extension during persistent connection cleanup (documented in docs/issues/2025-01-02-schema-test-timeout-segfault.md and GitHub issues satwareAG/php-firebird#50, #51).

The fix captures PHPUnit output and parses it to detect successful test execution. When exit code 139 occurs but PHPUnit reported "OK", the script should treat this as success with a warning message instead of failure. This is a targeted fix (~40 lines) that doesn't change the script's behavior for any other exit codes.

Scope:
- Modify `run_in_docker()` to capture output for PHPUnit commands
- Add new `run_phpunit_in_docker()` function with exit code 139 handling
- Update `run_tests_for_version()` to use the new function
- Add helper function to parse PHPUnit output for success patterns

[Types]
No type changes required - this is a bash script modification.

The script uses local variables for:
- `exit_code`: integer (0, 124, 139, or other)
- `output_file`: string (temporary file path)
- `phpunit_success`: boolean (true/false as string)

[Files]
Single file modification to implement exit code 139 handling.

Files to modify:
- `tests/phpunit.sh` - Main test runner script
  - Add `is_phpunit_success()` helper function (lines ~260-275)
  - Add `run_phpunit_in_docker()` function (lines ~280-330)
  - Modify `run_tests_for_version()` to use new function (lines ~340-365)

No new files created.
No files deleted.

[Functions]
Add two new functions and modify one existing function.

New functions:
1. `is_phpunit_success()` - Parse PHPUnit output to detect success
   - File: `tests/phpunit.sh`
   - Location: After `run_in_docker()` function (~line 282)
   - Parameters: `$1` = output file path
   - Returns: 0 if PHPUnit output indicates success, 1 otherwise
   - Logic: grep for "^OK" pattern in output (matches "OK!" and "OK, but there were issues!")

2. `run_phpunit_in_docker()` - Execute PHPUnit with exit code 139 handling
   - File: `tests/phpunit.sh`
   - Location: After `is_phpunit_success()` function (~line 295)
   - Parameters: `$1` = command, `$2` = timeout (optional, default 1200)
   - Returns: 0 on success (including exit 139 with PHPUnit OK), exit_code otherwise
   - Logic:
     1. Create temp file for output capture
     2. Run command with tee to capture output while streaming
     3. Capture exit code via ${PIPESTATUS[0]}
     4. If exit code 0: cleanup, return 0
     5. If exit code 139: check is_phpunit_success()
        - If success: print warning, cleanup, return 0
        - If failure: print error, cleanup, return 139
     6. For other exit codes: print error, cleanup, return exit_code

Modified functions:
1. `run_tests_for_version()` - Use run_phpunit_in_docker instead of run_in_docker
   - File: `tests/phpunit.sh`
   - Location: lines 340-359
   - Change: Replace `run_in_docker "$cmd" 1200` with `run_phpunit_in_docker "$cmd" 1200`

[Classes]
No class changes required - this is a bash script.

[Dependencies]
No dependency changes required.

The script already uses:
- bash built-ins (local, trap, etc.)
- coreutils (mktemp, tee, grep)
- docker compose

[Testing]
Manual testing required to verify the fix.

Test scenarios:
1. **Normal success**: Run tests that pass without SIGSEGV
   - Command: `./tests/phpunit.sh -s unit` (if unit tests don't trigger SIGSEGV)
   - Expected: "ALL TESTS PASSED!", exit code 0

2. **Exit 139 with PHPUnit OK**: Run full test suite (triggers SIGSEGV)
   - Command: `./tests/phpunit.sh`
   - Expected: Warning about SIGSEGV, "ALL TESTS PASSED!", exit code 0

3. **Exit 139 with PHPUnit FAILURES**: Simulate by modifying a test to fail
   - Expected: "SOME TESTS FAILED!", exit code 1

4. **Other exit codes**: Test timeout handling
   - Command: `./tests/phpunit.sh -- --filter NonExistentTest`
   - Expected: Appropriate error message, non-zero exit code

5. **Verbose mode**: Verify output capture doesn't break verbose logging
   - Command: `./tests/phpunit.sh --verbose`
   - Expected: Full PHPUnit output visible, correct result

Validation commands:
```bash
# Test 1: Run with coverage to verify script completes
./tests/phpunit.sh -c

# Test 2: Check exit code
./tests/phpunit.sh; echo "Exit code: $?"

# Test 3: Verbose mode
./tests/phpunit.sh --verbose
```

[Implementation Order]
Implement changes in order to minimize risk and allow incremental testing.

1. **Add `is_phpunit_success()` function** (~line 282)
   - Add after `run_in_docker()` function
   - Simple grep-based pattern matching
   - Test in isolation if needed

2. **Add `run_phpunit_in_docker()` function** (~line 295)
   - Add after `is_phpunit_success()`
   - Implements output capture and exit code 139 handling
   - Key logic: temp file, tee, PIPESTATUS, conditional handling

3. **Update `run_tests_for_version()` function** (~line 352)
   - Change single line: `run_in_docker` → `run_phpunit_in_docker`
   - Maintains same interface, no other changes needed

4. **Test the implementation**
   - Run `./tests/phpunit.sh` 
   - Verify warning message for SIGSEGV
   - Verify "ALL TESTS PASSED!" when tests pass
   - Verify exit code is 0

5. **Update documentation**
   - Mark action item "[x] Update CI to handle exit code 139 gracefully" in docs/issues/2025-01-02-schema-test-timeout-segfault.md
