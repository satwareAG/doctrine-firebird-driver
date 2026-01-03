# Implementation Plan: Full CQC Verification & Performance Optimization Phase 2

[Overview]
Execute a full Docker CQC (Code Quality Control) run to verify the stability of the test suite with the updated `php-firebird` extension (v7.0.0-rc.44) and the removal of duplicate transaction tests. Following successful verification, proceed with profiling and optimizing the slowest tests.

This plan prioritizes establishing a clean, crash-free baseline before applying further performance optimizations.

[Types]
No new types required.

[Files]
**Files to Verify (CQC):**
- All 127 test files in `tests/Test/`
- Static analysis: `src/` directory

**Files to Analyze (Profiling):**
1. `tests/Test/Functional/Schema/Firebird3SchemaManagerTest.php`
2. `tests/Test/Integration/Doctrine/DBAL/Database/TransactionTest.php`
3. `tests/Test/Integration/Doctrine/ORM/QueryBuilder/AlbumTest.php`

**Files to Modify (Optimization):**
- `tests/Test/Functional/Schema/SchemaManagerFunctionalTestCase.php` (Potential optimization in `setUp`/`cleanupSchemaTestTables`)

[Functions]
**Functions to Profile:**
1. `SchemaManagerFunctionalTestCase::setUp()`
2. `SchemaManagerFunctionalTestCase::cleanupSchemaTestTables()`

[Classes]
No new classes. Modifications will be limited to existing test classes for optimization purposes.

[Dependencies]
- `php-firebird` extension: v7.0.0-rc.44 (Already updated in Dockerfile)
- Docker containers: Firebird 2.5, 3.0, 4.0, 5.0

[Implementation Order]
1. **Full Docker CQC Verification**
   - Execute `cd tests && ./docker-cqc.sh --coverage`
   - Verify exit code 0 (no segfaults)
   - Verify all tests pass
   - Verify coverage ≥80%

2. **Profile Slowest Tests**
   - Run PHPUnit with timing output
   - Identify bottlenecks in SchemaManager and Integration tests

3. **Apply Targeted Optimizations**
   - Optimize `setUp`/`tearDown` in identified slow tests
   - Implement specific optimizations (e.g., batch cleanup)

4. **Final Verification**
   - Re-run CQC to confirm performance gains and stability

task_progress Items:
- [ ] Step 1: Run full Docker CQC verification
- [ ] Step 2: Analyze CQC results (verify no segfaults)
- [ ] Step 3: Profile slowest tests
- [ ] Step 4: Apply targeted optimizations
- [ ] Step 5: Final CQC verification
