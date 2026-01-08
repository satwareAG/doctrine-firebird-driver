# Implementation Plan - Driver AutoCommit Fix

[Overview]
Refactor `Connection::autoCommit` and `Statement::execute` to distinguish between DML and DQL (SELECT) operations.
The goal is to use `fbird_commit` (Full Commit) for DML to release locks immediately, resolving test isolation issues, while retaining `fbird_commit_ret` for SELECTs to preserve open cursors.

[Types]
No new types. Modification of existing method signatures.

[Files]
- `src/Driver/Firebird/Connection.php`: Modify `autoCommit`.
- `src/Driver/Firebird/Statement.php`: Modify `execute`.
- `tests/Test/FunctionalTestCase.php`: Review cleanup logic (optional, primarily driver fix).
- `tests/Test/Functional/Platform/AddColumnWithDefaultTest.php`: Revert the temporary fix (`markConnectionNotReusable`) to verify the driver fix works.

[Functions]
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection::autoCommit`
  - Change signature: `public function autoCommit(bool $releaseLocks = false): void`
  - Logic: If `$releaseLocks` is true, use `fbird_commit`. Else use `fbird_commit_ret`.
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement::execute`
  - Logic:
    - Detect if result is resource (SELECT) or bool/int (DML).
    - If DML: Capture `affected_rows` BEFORE commit. Call `autoCommit(true)`. Pass captured count to `Result`.
    - If SELECT: Call `autoCommit(false)`. Pass resource to `Result`.

[Classes]
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement`
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection`

[Dependencies]
No dependency changes.

[Implementation Order]
1. Revert temporary fix in `AddColumnWithDefaultTest.php` (ensure it fails again to establish baseline).
2. Modify `Connection::autoCommit` to support `$releaseLocks` parameter.
3. Modify `Statement::execute` to capture affected rows and call `autoCommit` with correct flag.
4. Verify fix with `AddColumnWithDefault` + `AlterColumn` test combination.
5. Run full test suite to ensure no regressions (especially SELECTs/Cursors).

task_progress Items:
- [ ] Revert temporary fix in `AddColumnWithDefaultTest.php`
- [ ] Verify that test combination fails again (reproduction)
- [ ] Modify `src/Driver/Firebird/Connection.php` to update `autoCommit` signature and logic
- [ ] Modify `src/Driver/Firebird/Statement.php` to handle affected rows and conditional commit
- [ ] Verify fix with specific tests
- [ ] Run full test suite
