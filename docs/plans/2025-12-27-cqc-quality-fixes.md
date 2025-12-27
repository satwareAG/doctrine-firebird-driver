# Implementation Plan

[Overview]
Fix quality issues identified in CQC pipeline for PHP 8.4: PHPCS heredoc tab error, Psalm PHP 8.4 compatibility, and PHPStan segfault handling.

The `tests/docker-cqc.sh -p 8.4` pipeline identified several issues requiring attention:

1. **PHPCS Error** (1 remaining): Tab character in heredoc closer at `tests/Test/Functional/DataAccessTest.php:458`
2. **Psalm E_STRICT Deprecation**: Psalm v5.0 uses deprecated `E_STRICT` constant in PHP 8.4, causing runtime warnings
3. **PHPStan Segfault**: Parallel worker segfault (exit code 139) occurring even with `maximumNumberOfProcesses: 1` due to php-firebird extension UAF bug

**Root Causes:**
- PHPCS: Editor mixed tabs/spaces in heredoc syntax
- Psalm: vimeo/psalm ^5.0 uses `E_STRICT` constant which is deprecated in PHP 8.4 (removed in PHP 9.0)
- PHPStan: php-firebird v7.0.0-rc.11 has use-after-free (UAF) bug in persistent connection handling (being fixed in `/home/mw/CLionProjects/php-firebird`)

**Solution Strategy:**
- Keep Psalm v5.x for PHP 8.1+ compatibility
- Suppress E_STRICT deprecation warning for PHP 8.4+ runs via error_reporting in CQC script
- Do NOT upgrade to Psalm v6.x (requires PHP ~8.1.31+ minimum patch version)

[Types]
No type changes required.

All fixes involve configuration updates and code formatting corrections.

[Files]
Three files require modification.

**Modified Files:**

1. `tests/Test/Functional/DataAccessTest.php`
   - Line 458: Replace tab character in heredoc closer `SQL;` with spaces
   - The `testSqliteLocateEmulation()` method has a heredoc with tab indentation that violates PSR-12

2. `tests/docker-cqc.sh`
   - Add PHP version detection for PHP 8.4+
   - Suppress `E_STRICT` deprecation warning when running Psalm on PHP 8.4+
   - Use `error_reporting()` or `-d error_reporting=...` flag to exclude `E_STRICT` (value 2048)

3. `docs/issues/2025-12-23-phpstan-parallel-segfault-investigation.md`
   - Update status to reflect that segfault still occurs with `maximumNumberOfProcesses: 1`
   - Add note about php-firebird UAF fix in progress
   - Reference the UAF fix strategy from `/home/mw/CLionProjects/php-firebird/docs/planning/UAF_FIX_NEXT_STEPS.md`

**No Changes Needed:**
- `composer.json` - Keep Psalm v5.x for broad PHP 8.1+ compatibility
- `psalm.xml.dist` - Configuration is correct
- `phpcs.xml.dist` - Configuration is correct (has duplicate rules but doesn't affect functionality)

[Functions]
No function changes required.

The PHPCS fix is a whitespace-only change in a test method.

[Classes]
No class changes required.

[Dependencies]
No dependency changes required.

**Keep Current Versions:**

| Package | Version | Reason |
|---------|---------|--------|
| `vimeo/psalm` | `^5.0` | Maintains PHP 8.1.0+ compatibility |
| `psalm/plugin-phpunit` | `^0.18` | Compatible with Psalm v5.x |

**Note on php-firebird:**
- Current: v7.0.0-rc.11 (in Dockerfile)
- Issue: UAF bug causes PHPStan segfault even in single-process mode
- Status: Fix in progress at `/home/mw/CLionProjects/php-firebird`
- Workaround: Current parallel limit is already set; segfaults may persist until extension is fixed
- Action: After php-firebird fix is released, update Dockerfile to new RC version

**Why NOT upgrade Psalm to v6.x:**
- Psalm v6.0 requires PHP ~8.1.31+ (specific patch version constraint)
- This would break compatibility with PHP 8.1.0 through 8.1.30
- Project requires `"php": "^8.1"` which means any 8.1.x version
- E_STRICT deprecation is cosmetic (warning only, doesn't affect analysis)

[Testing]
Run CQC pipeline to verify all fixes.

**Test Commands:**

1. **PHPCS Verification:**
```bash
vendor/bin/phpcs tests/Test/Functional/DataAccessTest.php --report=full
# Expected: No errors
```

2. **Psalm Verification:**
```bash
vendor/bin/psalm --no-cache
# Expected: No E_STRICT deprecation warnings in output
```

3. **Full CQC Pipeline:**
```bash
tests/docker-cqc.sh -p 8.4
# Expected: All phases pass (PHPStan may still segfault due to extension issue)
```

4. **PHPStan Verification (informational):**
```bash
vendor/bin/phpstan analyse --no-progress
# Note: Segfault may still occur until php-firebird extension is fixed
```

**Validation Criteria:**
- [ ] PHPCS reports 0 errors, 0 warnings for DataAccessTest.php
- [ ] Psalm runs without E_STRICT deprecation notices
- [ ] Psalm baseline regeneration works without errors
- [ ] PHPUnit tests continue to pass (1585 tests, ≥83% coverage)

[Implementation Order]
Sequential fixes starting with quick wins.

1. **Fix PHPCS heredoc tab error** (5 minutes)
   - Read `tests/Test/Functional/DataAccessTest.php` around line 458
   - Replace tab before `SQL;` closer with appropriate spaces (matching heredoc indent level)
   - Verify with `vendor/bin/phpcs tests/Test/Functional/DataAccessTest.php`

2. **Suppress E_STRICT deprecation in CQC script** (10 minutes)
   - Edit `tests/docker-cqc.sh`
   - Add PHP version detection in the Psalm phase
   - For PHP 8.4+, run Psalm with: `php -d error_reporting="E_ALL & ~E_DEPRECATED" vendor/bin/psalm`
   - This suppresses the E_STRICT deprecation warning without modifying Psalm source
   - Alternative: Use `2>&1 | grep -v "E_STRICT is deprecated"` to filter output

3. **Update PHPStan investigation document** (5 minutes)
   - Document that segfault now occurs even with single process
   - Add cross-reference to php-firebird UAF fix progress
   - Note that CQC script reports "PASSED" but this is misleading due to incomplete analysis

4. **Run full CQC verification** (30 minutes)
   - Execute `tests/docker-cqc.sh -p 8.4`
   - Verify PHPCS phase passes with 0 errors
   - Verify Psalm phase runs without deprecation warnings
   - Document any remaining PHPStan issues for tracking

5. **Document findings in CHANGELOG** (5 minutes)
   - Add entry for E_STRICT deprecation suppression
   - Note PHP 8.4 compatibility improvements
   - Document that PHPStan segfault is pending php-firebird extension fix
