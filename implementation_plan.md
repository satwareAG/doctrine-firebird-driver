# Implementation Plan

[Overview]
Stabilize the doctrine-firebird-driver test suite and complete quality improvements on the `001-quality-improvements` branch.

After the php-firebird v10.3.6 upgrade and the three test failure fixes (FB4 SIGFPE, FB5 config, AlbumTest seeding), the driver needs stabilization across three areas: (1) mark known php-firebird upstream failures so the test suite produces a clean baseline, (2) complete the resource type guard modernization from `Connection.php`/`Statement.php`/`Result.php`, and (3) add missing functional test coverage from the DBAL 3.10.x gap analysis (issues #59-#68). The goal is a green CI pipeline with PHPStan Level 8, zero baseline errors, and clear separation between driver bugs and upstream extension bugs.

Current metrics (as of 2026-03-31):
- PHPStan Level 8: 0 errors, empty baseline
- `@fbird_*` suppressions in src/: 0 (all removed)
- Connection.php: 903 LOC (down from 1320, TransactionManager extracted)
- Open issues: #96 (reconnection bug), #59-#79 (gap analysis, all open)
- FB4 Functional: ~646 tests, BatchTest crashes with invalid batch handle (php-firebird#180), SIGSEGV at exit
- Integration-ReadOnly: 15/16 pass (1 OO API handle error - pre-existing)
- Branch: `001-quality-improvements` based on `3.10.x`

[Types]
No new types required. Existing `Enum/ExecutionMode.php` and `Enum/TransactionState.php` are sufficient.

The resource validation methods already exist but need audit for consistency:
- `Connection::isConnectionValid()` - validates `Firebird link`/`Firebird persistent link`
- `Connection::isTransactionValid()` - delegates to `TransactionManager`
- `Statement` - needs `isStatementValid()` standardization
- `Result` - needs `isResultValid()` standardization

PHPStan `@phpstan-assert-if-true` annotations should be added to all validation methods.

[Files]
Modify existing driver files and add test infrastructure for known-failure marking.

Files to modify:
- `src/Driver/Firebird/Connection.php` - Audit resource guards, ensure `isConnectionValid()` used consistently
- `src/Driver/Firebird/Statement.php` - Add/standardize `isStatementValid()`, use before every `fbird_*` call
- `src/Driver/Firebird/Result.php` - Add/standardize `isResultValid()`, use before every `fbird_*` call
- `src/Driver/Firebird/TransactionManager.php` - Audit `isTransactionValid()` consistency
- `tests/Test/Functional/BatchTest.php` - Mark tests that depend on php-firebird batch handle lifecycle as skipped with clear upstream reference
- `tests/phpunit-firebird4.xml` - Add Integration test suites
- `tests/phpunit-firebird5.xml` - Add Integration test suites

Files to create:
- `tests/Test/Functional/Schema/DefaultValueTest.php` - Issue #66
- `tests/Test/Functional/Schema/ComparatorTest.php` - Issue #67
- `tests/Test/Functional/TransactionTest.php` - Issue #65 (if not already complete)
- `tests/Test/Unit/Driver/ResultTest.php` - Already exists, extend with `isResultValid()` tests
- `tests/Test/Unit/Driver/StatementTest.php` - Already exists, extend with `isStatementValid()` tests

Files to delete:
- None

[Functions]
Standardize resource validation and add missing test coverage.

New functions:
- `Statement::isStatementValid(): bool` in `src/Driver/Firebird/Statement.php` - Returns true if internal statement resource is valid Firebird query handle. Add `@phpstan-assert-if-true` annotation.
- `Result::isResultValid(): bool` in `src/Driver/Firebird/Result.php` - Returns true if internal result resource is valid. Add `@phpstan-assert-if-true` annotation.

Modified functions:
- `Connection::isConnectionValid()` in `src/Driver/Firebird/Connection.php` - Audit callers, ensure every public method that touches `$this->connection` calls this first or handles invalid state gracefully.
- `TransactionManager::isTransactionValid()` in `src/Driver/Firebird/TransactionManager.php` - Add `@phpstan-assert-if-true` annotation, audit all callers.
- `Statement::execute()` in `src/Driver/Firebird/Statement.php` - Add pre-flight `isStatementValid()` check.
- `Result::fetchNumeric()`, `Result::fetchAssociative()`, `Result::fetchOne()`, `Result::fetchAllNumeric()`, `Result::fetchAllAssociative()`, `Result::fetchFirstColumn()` in `src/Driver/Firebird/Result.php` - Add pre-flight `isResultValid()` check.
- `BatchTest::testBatchInsertBasic()`, `testBatchInsertWithBlob()`, `testBatchInsertPerformance()` in `tests/Test/Functional/BatchTest.php` - Wrap in try/catch for known invalid batch handle errors, skip with upstream reference.

Removed functions:
- None

[Classes]
No new classes. Existing driver classes modified for consistency.

Modified classes:
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection` (903 LOC) - Resource guard audit
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement` (390 LOC) - Add `isStatementValid()`, use consistently
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Result` (380 LOC) - Add `isResultValid()`, use consistently
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\TransactionManager` (385 LOC) - Annotation improvements
- `Satag\DoctrineFirebirdDriver\Test\Functional\BatchTest` - Known-failure handling
- `Satag\DoctrineFirebirdDriver\Test\Functional\TransactionTest` - New or extended (Issue #65)
- `Satag\DoctrineFirebirdDriver\Test\Functional\Schema\DefaultValueTest` - New (Issue #66)
- `Satag\DoctrineFirebirdDriver\Test\Functional\Schema\ComparatorTest` - New (Issue #67)

[Dependencies]
No dependency changes required.

Current dependencies are correct:
- `ext-firebird: ^10.3.2` (php-firebird v10.3.6 installed)
- `doctrine/dbal: ^3.10` (DBAL 3.x series)
- PHPStan Level 8 with strict-rules, doctrine, and deprecation-rules extensions

[Testing]
Stabilize test suite to produce a clean, reproducible baseline across FB3/FB4/FB5.

Test strategy:
1. **Unit tests**: Extend `ConnectionTest`, `StatementTest`, `ResultTest`, `TransactionManagerTest` with validation method coverage
2. **Functional tests**: Mark known php-firebird upstream failures (BatchTest batch handle lifecycle, SIGSEGV at exit) with `markTestSkipped()` and issue references
3. **Integration tests**: Already working after AlbumTest seeding fix. Add Integration suite to FB4/FB5 configs.
4. **New functional tests**: TransactionTest (#65), DefaultValueTest (#66), ComparatorTest (#67)
5. **PHPStan**: Must remain at Level 8 with 0 errors and empty baseline after all changes
6. **Target**: All Functional+Unit tests green on FB4, Integration-ReadOnly 16/16

Validation commands:
```bash
# PHPStan
php vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress

# FB4 Functional
docker compose exec -T -e DB_HOST=firebird4 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit-firebird4.xml"

# FB4 Integration-ReadOnly
docker compose exec -T -e DB_HOST=firebird4 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit.xml --testsuite=Integration-ReadOnly"

# FB5 Full
docker compose exec -T -e DB_HOST=firebird5 app bash -c "cd /app && php vendor/bin/phpunit --no-coverage -c tests/phpunit-firebird5.xml"
```

[Implementation Order]
Sequential phases to minimize risk and ensure each step is independently verifiable.

Phase 1 - Test Suite Stabilization (Highest Priority):
1. Mark BatchTest known failures with `markTestSkipped()` and php-firebird#180 reference
2. Add Integration suites to `phpunit-firebird4.xml` and `phpunit-firebird5.xml`
3. Run full FB4 suite and capture clean baseline (expected: all pass or skip, no crashes)
4. Run full FB5 suite and verify clean baseline

Phase 2 - Resource Type Guard Completion:
5. Audit `Connection::isConnectionValid()` usage - ensure all public methods check first
6. Add `Statement::isStatementValid()` with `@phpstan-assert-if-true` annotation
7. Add `Result::isResultValid()` with `@phpstan-assert-if-true` annotation
8. Audit `TransactionManager::isTransactionValid()` - add annotations
9. Update unit tests for validation methods
10. Run PHPStan Level 8 - must remain 0 errors

Phase 3 - Gap Analysis Tests (Sprint 1-2 Issues):
11. Implement TransactionTest (#65) - test transaction isolation, savepoints, nested transactions
12. Implement DefaultValueTest (#66) - test schema manager default value handling
13. Implement ComparatorTest (#67) - test schema comparator for false-positive detection
14. Run full suite on FB4 and FB5 to verify no regressions

Phase 4 - Finalize and Merge:
15. Final PHPStan + full test suite verification
16. Update NEXT_STEPS.md with current status
17. Squash-merge or rebase `001-quality-improvements` into `3.10.x`
