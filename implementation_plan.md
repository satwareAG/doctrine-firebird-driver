# Implementation Plan

[Overview]
Upgrade php-firebird from v10.3.6 to v10.3.7 and validate fixes for three upstream bugs (#183, #184, #185).

php-firebird v10.3.7 was released with fixes for three bugs filed by the doctrine-firebird-driver team during Phase 1 test stabilization. The fixes address: SIGSEGV during PHP shutdown (#183), OO API handle loss after tearDown/reconnect (#184), and IBatch handle invalidation before execute (#185). This plan upgrades the Docker test environment, removes the workarounds added in Phase 1, re-runs the full test suite on FB4 and FB5 to validate the fixes, and closes the upstream issues. See GitHub issue: https://github.com/satwareAG/doctrine-firebird-driver/issues/97

Current state (pre-upgrade):
- php-firebird v10.3.6 in `tests/app/Dockerfile`
- BatchTest has `skipIfKnownBatchHandleIssue()` workaround for #185
- Full suite crashes at ~65% with SIGSEGV (#183)
- Integration-ReadOnly has 9 errors on FB5 from OO API handle loss (#184)
- Branch: `001-quality-improvements` at commit 412401c

Expected state (post-upgrade):
- php-firebird v10.3.7 in Dockerfile
- BatchTest workaround removed (tests should pass natively)
- Full suite completes without SIGSEGV
- Integration-ReadOnly passes all 24 tests on FB5
- Upstream issues #183, #184, #185 closed

[Types]
No type changes required.

[Files]
Update Dockerfile, BatchTest, and documentation files.

Files to modify:
- `tests/app/Dockerfile` (lines 7, 32, 40, 43) - Update version from v10.3.6 to v10.3.7 with changelog comment
- `tests/Test/Functional/BatchTest.php` - Remove `skipIfKnownBatchHandleIssue()` method and all try/catch wrappers around `$batch->execute()` calls. The setUp() SIGFPE guard for #180 (parameterless statements) must remain since #180 is NOT fixed in v10.3.7.
- `NEXT_STEPS.md` - Update extension version, test baseline, known blockers table
- `implementation_plan.md` - This file (replaced with current plan)

Files to create:
- None

Files to delete:
- None

[Functions]
Remove BatchTest workaround functions.

Removed functions:
- `BatchTest::skipIfKnownBatchHandleIssue(Throwable $e): void` in `tests/Test/Functional/BatchTest.php` - No longer needed since php-firebird#185 is fixed in v10.3.7

Modified functions:
- `BatchTest::testBatchInsertBasic()` - Remove try/catch wrapper, call `$batch->execute()` directly
- `BatchTest::testBatchInsertWithBlob()` - Remove try/catch wrapper, call `$batch->execute()` directly
- `BatchTest::testBatchInsertPerformance()` - Remove try/catch wrapper, call `$batch->execute()` directly

[Classes]
No class changes.

[Dependencies]
No dependency changes. `composer.json` already has `"ext-firebird": "^10.3.2"` which covers v10.3.7.

[Testing]
Rebuild Docker app container and re-run full test suites on FB4 and FB5 to validate upstream fixes.

Validation commands:
```bash
# Rebuild app container with v10.3.7
cd tests && docker compose build --no-cache app

# Verify extension version
docker compose exec -T app php -r "echo phpversion('firebird') . PHP_EOL;"
# Expected: 10.3.7

# FB4 Unit (quick sanity check)
docker compose exec -T -e DB_HOST=firebird4 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit-firebird4.xml --testsuite=Unit 2>&1 | tail -5"

# FB4 Integration-ReadOnly (validates #184 fix)
docker compose exec -T -e DB_HOST=firebird4 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit-firebird4.xml --testsuite=Integration-ReadOnly 2>&1 | tail -10"

# FB4 Functional with BatchTest (validates #185 fix)
docker compose exec -T -e DB_HOST=firebird4 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit-firebird4.xml --filter=BatchTest 2>&1 | tail -15"

# FB5 Full suite (validates #183 fix - should complete without SIGSEGV)
docker compose exec -T -e DB_HOST=firebird5 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit-firebird5.xml 2>&1 | tail -10"

# FB4 Full suite
docker compose exec -T -e DB_HOST=firebird4 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit-firebird4.xml 2>&1 | tail -10"
```

Expected results after v10.3.7:
- Unit: 1569 OK (unchanged)
- Integration-ReadOnly: 24/24 pass on both FB4 and FB5 (was 15/24 on FB5)
- Functional BatchTest: 3/3 pass (was 3/3 skipped)
- Full suite: Completes with exit code 0 (was SIGSEGV at 65%)

[Implementation Order]
Sequential steps to minimize risk with validation at each stage.

1. Update `tests/app/Dockerfile` to checkout v10.3.7 (update version comments and git checkout tag)
2. Rebuild Docker app container (`docker compose build --no-cache app`)
3. Verify php-firebird 10.3.7 is loaded in container
4. Run FB4 Unit suite (quick sanity - should remain 1569 OK)
5. Run FB4 Integration-ReadOnly (validates #184 OO API handle fix)
6. Run FB4 BatchTest only (validates #185 IBatch fix - expect pass not skip)
7. Remove `skipIfKnownBatchHandleIssue()` and try/catch wrappers from BatchTest
8. Run FB4 BatchTest again to confirm tests pass without workaround
9. Run FB5 full suite (validates #183 SIGSEGV fix - expect clean exit)
10. Run FB4 full suite (validate clean exit)
11. Update NEXT_STEPS.md with new baseline and v10.3.7 status
12. Commit all changes
13. Close upstream issues #183, #184, #185 with validation results
14. Close doctrine-firebird-driver #97
15. Push to origin
