# Implementation Plan - DBAL 4.x Forward Compatibility

[Overview]
Prepare the doctrine-firebird-driver for the upcoming DBAL 4.x release by addressing deprecated APIs and implementing modern alternatives.

This implementation focuses on removing or planning the removal of DBAL 3.x features that are removed in DBAL 4.x, such as `listTableDetails()`, the `ServerInfoAwareConnection` interface, and signature changes in `VersionAwarePlatformDriver`.

[Types]
No changes to existing types.

[Files]
Detailed breakdown:
- Existing files to be modified:
  - `src/Schema/FirebirdSchemaManager.php`: Replace `doListTableDetails()` logic or ensure it's compatible with `introspectTable()`.
  - `src/Driver/Firebird/ConnectionWrapper.php`: Replace `listTableColumns()` with `introspectTable()` if appropriate, or ensure it uses the modern path.
  - `src/Platforms/FirebirdPlatform.php`: Ensure `createSchemaManager()` is properly deprecated/handled.

[Functions]
Detailed breakdown:
- Modified functions:
  - `Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager::listTableDetails`: Already deprecated, ensure it correctly delegates to `introspectTable()` logic.
  - `Satag\DoctrineFirebirdDriver\Driver\Firebird\ConnectionWrapper::getIdentityColumnForTable`: Change `listTableColumns()` to `introspectTable()`.

[Classes]
Detailed breakdown:
- Modified classes:
  - `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection`: Audit usage of `ServerInfoAwareConnection`.
  - `Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver`: Audit usage of `VersionAwarePlatformDriver`.

[Dependencies]
No new dependencies.

[Implementation Order]
1. Refactor internal usages of `listTableDetails()` and `listTableColumns()` to `introspectTable()`.
2. Update `FirebirdSchemaManager` to ensure `introspectTable()` is the primary path.
3. Document remaining manual migration steps for DBAL 4.0.x branch transition.

task_progress Items:
- [ ] Refactor `ConnectionWrapper::getIdentityColumnForTable()` to use `introspectTable()`
- [ ] Verify `FirebirdSchemaManager::introspectTable()` is correctly inherited and working
- [ ] Audit and document `ServerInfoAwareConnection` replacement
- [ ] Audit and document `VersionAwarePlatformDriver` signature changes
- [ ] Run functional tests to ensure no regressions in schema introspection
