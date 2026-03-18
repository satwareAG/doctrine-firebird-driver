# Implementation Plan

[Overview]
Resolve the remaining PHPUnit deprecations, fix a PHPStan return type warning, and prune the Psalm baseline to achieve a clean v3.12.0 stable release state.

The project is nearing a stable release, but a few minor quality issues remain. Two PHPUnit deprecations were observed in recent runs, likely due to missing parameter types in functional tests. Additionally, PHPStan reports an unused return type in `FirebirdSchemaManager`, and Psalm's baseline contains entries that are no longer applicable. This plan focuses on surgically addressing these items within the Docker environment.

[Types]
No changes to the type system are required, only refinements to PHPDoc and method return types to match existing implementation.

[Files]
Refine internal documentation and clean up static analysis configuration.

Detailed breakdown:
- Existing files to be modified:
    - `src/Schema/FirebirdSchemaManager.php`: Override inherited PHPDoc return type.
    - `psalm-baseline.xml`: Pruned via automated Psalm execution.
    - `tests/Test/Functional/*`: Update tests once specific deprecations are identified.

[Functions]
Refine function metadata for better static analysis.

Detailed breakdown:
- Modified functions:
    - `Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager::_getPortableViewDefinition()`: Update PHPDoc to specifically return `View`.

[Classes]
No changes to class structure.

[Dependencies]
No changes to dependencies.

[Implementation Order]
Execute in a sequence that minimizes context loss and ensures immediate verification.

1. Run Firebird 3 functional tests in Docker with verbose output to identify the exact lines causing the 2 PHPUnit deprecations.
2. Fix identified deprecations in the test files.
3. Update `src/Schema/FirebirdSchemaManager.php` to resolve the PHPStan warning.
4. Run Psalm in Docker with `--set-baseline` to clean up `psalm-baseline.xml`.
5. Run the full quality and test suite to verify the fix.

task_progress Items:
- [ ] Step 1: Identify PHPUnit deprecations via verbose Docker run
- [ ] Step 2: Fix identified deprecations in functional tests
- [ ] Step 3: Resolve PHPStan warning in FirebirdSchemaManager
- [ ] Step 4: Prune Psalm baseline in Docker
- [ ] Step 5: Final verification of full matrix and quality suite
