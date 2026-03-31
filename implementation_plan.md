# Implementation Plan

[Overview]
Consolidate and modernize resource type guards in `Connection.php`, `Statement.php`, and `Result.php` for `php-firebird` v10.3.x compatibility.

The driver currently uses direct `is_resource()` and `get_resource_type()` checks scattered across several classes. This plan aims to consolidate these checks into semantic validation methods (`isConnectionValid()`, `isStatementValid()`, `isResultValid()`) and ensure they are used consistently. This improves type safety and prepares the codebase for the extension's eventual transition to object-based resources while maintaining strict compatibility with `php-firebird` v10.3.x.

[Types]
Enhance `@psalm-assert-if-true` and `@phpstan-assert-if-true` annotations for resource validation methods.

Resource types for `php-firebird` v10.3.x:
- Connection: `Firebird link`, `Firebird persistent link`
- Transaction: `Firebird transaction`
- Statement/Result: `Firebird query`, `firebird result`

[Files]
Modernize resource type guards in core driver files.

Detailed breakdown:
- `src/Driver/Firebird/Connection.php`: Update `isConnectionValid()` and ensure its consistent use.
- `src/Driver/Firebird/Statement.php`: Implement/Update `isStatementValid()` and ensure its consistent use.
- `src/Driver/Firebird/Result.php`: Update `isResultValid()` and ensure its consistent use.
- `src/Driver/Firebird/TransactionManager.php`: Consolidate transaction resource validation.

[Functions]
Update validation functions and their callers.

Detailed breakdown:
- `Connection::isConnectionValid()`: Standardize resource type checks for `php-firebird` v10.3.x.
- `Statement::isStatementValid()`: Implement semantic check for `Firebird query` resource.
- `Result::isResultValid()`: Standardize checks for `firebird result` and `Firebird query`.
- `TransactionManager::isTransactionValid()`: Standardize checks for `Firebird transaction`.

[Classes]
No new classes are required; existing driver classes will be modified.

Detailed breakdown:
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection`: Consistent use of `isConnectionValid()`.
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement`: Consistent use of `isStatementValid()`.
- `Satag\DoctrineFirebirdDriver\Driver\Firebird\Result`: Consistent use of `isResultValid()`.

[Dependencies]
Compatibility must be maintained with `php-firebird` v10.3.x only.

[Implementation Order]
Step-by-step modernization of resource guards.

task_progress Items:
- [ ] Step 1: Update `Connection::isConnectionValid()` and ensure all methods use it.
- [ ] Step 2: Implement/Update `Statement::isStatementValid()` and ensure all methods use it.
- [ ] Step 3: Update `Result::isResultValid()` and ensure all methods use it.
- [ ] Step 4: Verify and update `TransactionManager::isTransactionValid()`.
- [ ] Step 5: Run static analysis (PHPStan/Psalm) to verify type safety.
- [ ] Step 6: Run functional tests to ensure no regressions.
