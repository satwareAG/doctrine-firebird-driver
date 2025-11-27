# Implementation Plan - Optimized Transaction Handling & Firebird 4.0+ Support

[Overview]
Enhance the Doctrine Firebird Driver to support proper nested transaction tracking via counters (ensuring atomicity), implement Firebird 4.0+ platform features (new data types, drop table optimizations), and improve Boolean type handling.

This implementation addresses critical transaction integrity issues where inner commits were breaking outer transaction atomicity, and modernizes the driver for current Firebird versions.

[Types]
No public type changes, but `FirebirdPlatform` will now support `DATETIMETZ` and `TIMETZ` mapping.

[Files]
- `src/Driver/Firebird/Connection.php`: Modify transaction logic to use robust counters and remove `commit_ret` for nested levels.
- `src/Platforms/FirebirdPlatform.php`: Refactor `getDropTableSQL` for recursive dependency dropping; update Boolean logic; add 4.0+ DDL methods.
- `src/Platforms/Firebird4Platform.php`: Ensure it inherits/overrides correctly for 4.0 specific types.
- `tests/Test/Functional/Driver/Firebird/TransactionNestingTest.php`: New test file for transaction validation.

[Functions]
- `Connection::beginTransaction`: Updates to increment `fbirdTransactionLevel` unconditionally.
- `Connection::commit`: Updates to decrement level and commit only at level 0.
- `Connection::rollBack`: Updates to decrement level and rollback only at level 0.
- `FirebirdPlatform::getDropTableSQL`: Modified to include view/trigger cleanups.
- `FirebirdPlatform::getBooleanTypeDeclarationSQL`: Updated for version-aware logic.
- `FirebirdPlatform::getDateTimeTzTypeDeclarationSQL`: Added.
- `FirebirdPlatform::getTimeTzTypeDeclarationSQL`: Added.

[Classes]
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection`: Logic update only.
- `Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform`: Logic update + new methods.

[Dependencies]
No new external dependencies. Requires `php-firebird` extension (already present).

[Implementation Order]
1. Modify `Connection.php` to implement the Transaction Counter pattern (preserving atomicity).
2. Update `FirebirdPlatform.php` with Drop Table optimizations and Boolean handling.
3. Add Firebird 4.0+ type support to Platform classes.
4. Create and run `TransactionNestingTest.php` to verify atomicity and nesting behavior.
5. Verify Drop Table logic with existing schema tests.

[Tracking]
Progress is tracked dynamically via the `task_progress` tool parameter during execution. Refer to the active session history for live status updates.
