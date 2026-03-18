# Learning: Initial DBAL 4.x Migration Blockers

**Date**: 2026-03-18
**Context**: Transitioning `doctrine-firebird-driver` from DBAL 3.x to 4.x.

## Blockers Identified

Upon bumping `doctrine/dbal` to `^4.4` in the `4.4.x` branch, the following fatal errors and incompatibilities were identified:

1. **Removed Interfaces**:
   - `Doctrine\DBAL\VersionAwarePlatformDriver`: Removed in DBAL 4. Drivers are now expected to handle version-specific platform creation differently (often via `ServerVersionProvider`).
   - `Doctrine\DBAL\Driver\ServerInfoAwareConnection`: Removed in DBAL 4.

2. **Signature Changes**:
   - `Driver::getDatabasePlatform()`: Now requires a `Doctrine\DBAL\ServerVersionProvider $versionProvider` argument.
   - `Connection::quote()`: Now only accepts a `string $value` and must return a `string`. The second `$type` argument was removed.
   - `Connection::lastInsertId()`: Return type changed from `string|int|false` to `string|int`.
   - `Statement::bindValue()`: Return type changed from `bool` to `void`.
   - `Statement::bindParam()`: Return type changed from `bool` to `void`.

3. **Type Changes**:
   - `ParameterType`: Now an Enum instead of a set of constants. Comparisons like `$type === ParameterType::STRING` now compare an object/enum-case, which may break some legacy integer-based logic if not updated.

## Next Steps for Migration

1. Update `FirebirdDriver::getDatabasePlatform()` to accept `ServerVersionProvider`.
2. Fully implement the new `ParameterType` enum logic across the driver.
3. Refactor tests to use the new DBAL 4 connection unwrapping patterns (as documented in `docs/research/dbal4-migration.md`).
4. Address the 50+ Psalm errors introduced by the strict typing and interface changes in DBAL 4.

## Prevention Pattern

When performing major version migrations, first address "Fatal Error" blockers (interface/signature mismatches) before attempting to fix functional test failures. Use static analysis (PHPStan/Psalm) to identify all signature changes early.
