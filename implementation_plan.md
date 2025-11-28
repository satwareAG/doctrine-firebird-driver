# Implementation Plan - Code Quality and Error Handling Improvements

[Overview]
Address code quality feedback by removing error suppression (`@`) in `Connection.php` and `Statement.php`, and implementing robust resource safety in `Statement::execute()` using `try-catch-finally`. Also fix identified test failures in `ResultTest` and verifying `StatementTest` fixes.

[Types]
No new types.

[Files]
`src/Driver/Firebird/Connection.php`:
- Remove `@` from `fbird_commit`, `fbird_rollback`, `fbird_close`.
- Add explicit error checking and logged warnings/exceptions.

`src/Driver/Firebird/Statement.php`:
- Remove `@` from `fbird_execute`.
- Wrap resource intensive operations (BLOB binding) in `try-finally` to ensure `fclose` and `fbird_blob_close` are called even on error.
- Handle `fbird_execute` returning `false` properly with detail error messages.

`tests/Test/Unit/Driver/ResultTest.php`:
- Fix constructor test constraints/expectations if needed (based on recent findings).

[Functions]
Modified:
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection::commit`
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection::rollBack`
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement::execute`

[Implementation Order]
1. Refactor `Connection.php`.
2. Refactor `Statement.php`.
3. Fix `ResultTest.php`.
4. Verify all changes with test suite.

task_progress Items:
- [ ] Refactor `Connection.php` to remove `@` and add explicit error handling
- [ ] Refactor `Statement.php` to use `try-catch-finally` for resource cleanup
- [ ] Remove `@fbird_execute` suppression in `Statement.php` and improve error reporting
- [ ] Fix `ResultTest.php` test cases
- [ ] Run full test suite to ensure no regressions
