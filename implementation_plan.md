# Implementation Plan - Fix Test Failures (Statement Re-execution & Object In Use)

[Overview]
Fix `Statement` re-execution logic to safely release previous result cursors before creating new ones, and ensure test isolation by preventing connection reuse after `AutoIncrementColumnTest`.

These changes address the `Invalid cursor reference` warnings/failures in `StatementTest` and the `TABLE "AUTO_INCREMENT_TABLE" is in use` error in `BinaryDataAccessTest`.

[Types]
No public type changes.

[Files]
- `src/Driver/Firebird/Result.php`: Enhance `free()` to be idempotent (prevent double-free).
- `src/Driver/Firebird/Statement.php`: Track active `Result` and free it before re-execution.
- `tests/Test/Functional/AutoIncrementColumnTest.php`: Mark connection not reusable to prevent stale transaction leaks.

[Functions]
- `Result::free`: Updated to set `$this->firebirdResultResource = null` after freeing, preventing repeated calls from destructor.
- `Statement::execute`: Updated to check for `$this->currentResult`, call `free()` if present, and store the new result.
- `AutoIncrementColumnTest::tearDown`: Updated to call `$this->markConnectionNotReusable()`.

[Classes]
- `Result`: Modified logic.
- `Statement`: Added `private ?Result $currentResult = null` property.
- `AutoIncrementColumnTest`: Modified `tearDown`.

[Dependencies]
No new dependencies.

[Implementation Order]
1. Modify `Result.php` to make `free()` idempotent.
2. Modify `Statement.php` to manage `Result` lifecycle during re-execution.
3. Modify `AutoIncrementColumnTest.php` to enforce connection isolation.
4. Run tests to verify fixes (`StatementTest` and `BinaryDataAccessTest`).
