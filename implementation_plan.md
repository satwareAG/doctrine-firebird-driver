# Implementation Plan: PHPUnit Script Bugfix and Optimization

[Overview]
Fix the false failure reporting in tests/phpunit.sh where PHPUnit passes but the script reports failure, and optimize the script following bash best practices.

The root cause is missing the `-T` flag when running `docker compose run` commands. When docker-compose.yml has `tty: true` and `stdin_open: true`, the pseudo-TTY allocation interferes with proper exit code propagation. The docker-cqc.sh script in the same project correctly uses `-T` flag, `timeout`, and `< /dev/null` patterns that are missing from phpunit.sh.

The optimization focuses on:
1. Fixing exit code propagation (the critical bug)
2. Adding timeout protection against hangs
3. Reducing verbose noise from docker compose output
4. Fixing ShellCheck warnings (SC2155)
5. Consistent pattern with docker-cqc.sh

[Types]
No new types required - this is a bash script modification.

This implementation involves only bash script changes with no type definitions.

[Files]
Modify the single file tests/phpunit.sh to fix the bug and optimize output.

**Modified Files:**
- `tests/phpunit.sh` - Main test runner script (all changes)

**Changes Summary:**
1. Add `run_in_docker()` helper function (extracted from docker-cqc.sh pattern)
2. Fix all `docker compose run` invocations to use `-T` flag
3. Suppress docker compose output noise with `2>&1 | grep -v "^WARN\|^Container"` patterns
4. Fix SC2155 warnings for readonly declarations
5. Add timeout protection on test execution
6. Close stdin with `< /dev/null` to prevent hangs

[Functions]
Add one new function and modify several existing functions in tests/phpunit.sh.

**New Functions:**
1. `run_in_docker()` (after line ~260, after `wait_for_containers`)
   - Signature: `run_in_docker(cmd, timeout)`
   - Purpose: Execute commands in docker with proper TTY handling and timeout
   - Uses: `-T` flag, `timeout` command, `< /dev/null`

**Modified Functions:**
1. `run_tests_for_version()` (line 319)
   - Change: Use `run_in_docker` instead of direct `docker compose run`
   - Reason: Proper exit code propagation

2. `main()` (line 341)
   - Change: Fix composer update call to use `-T` flag
   - Change: Fix mkdir call to use `-T` flag  
   - Change: Suppress WARN messages from docker compose commands
   - Change: Add quiet mode for build commands

**Variable Declarations (lines 24-26):**
- Fix SC2155: Separate declaration from assignment for `SCRIPT_NAME`, `SCRIPT_DIR`, `PROJECT_ROOT`

[Classes]
Not applicable - this is a bash script with no class definitions.

No classes to modify.

[Dependencies]
No new dependencies required.

The script already uses:
- bash (GNU Bash)
- docker/docker compose
- ShellCheck (for validation)

No package changes needed.

[Testing]
Manual testing required to verify the fix.

**Test Procedure:**
1. Run `shellcheck tests/phpunit.sh` - should pass with no warnings
2. Run `./tests/phpunit.sh` - should report PASSED when PHPUnit shows "OK, but there were issues!"
3. Run `./tests/phpunit.sh -v all` (optional) - verify all Firebird versions work
4. Verify output noise is reduced (no WARN messages from docker compose)
5. Verify docker containers still function correctly

**Success Criteria:**
- PHPUnit "OK, but there were issues!" results in script exit code 0
- All 1585 tests pass without false failure report
- ShellCheck passes with `--severity=warning`
- Reduced output noise (minimal WARN messages)

[Implementation Order]
Implement changes in this specific order to ensure incremental testing.

1. **Fix SC2155 warnings** (lines 24-26)
   - Separate readonly declarations from command substitution assignments
   - Low risk, can be tested immediately with shellcheck

2. **Add `run_in_docker()` function** (after line ~260)
   - Add the helper function with proper `-T`, timeout, and stdin handling
   - Pattern taken from working docker-cqc.sh implementation

3. **Update `run_tests_for_version()`** (line 330)
   - Replace direct docker compose run with `run_in_docker` call
   - This is the critical fix for the bug

4. **Update composer update call** (line 386)
   - Add `-T` flag and stdin redirection
   - Ensures consistent behavior

5. **Update mkdir call** (line 390)
   - Add `-T` flag and stdin redirection
   - Ensures consistent behavior

6. **Suppress docker compose noise** (lines 361-369, 376, etc.)
   - Add output filtering for WARN messages
   - Redirect stderr where appropriate

7. **Final ShellCheck validation**
   - Run `shellcheck --severity=warning tests/phpunit.sh`
   - Verify no new warnings introduced

8. **Integration test**
   - Run full test suite and verify correct exit code handling
