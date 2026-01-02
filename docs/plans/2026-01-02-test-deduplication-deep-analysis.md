# Test Deduplication Deep Analysis

**Date**: 2026-01-02
**Branch**: `optimize/test-suite-performance`
**Current Coverage**: 82.46% (1740/2110 lines)
**Current Tests**: 1584 tests

## [Overview]

Deep analysis of potential test duplications between Functional and Integration test suites to identify safe removal candidates that maintain ≥82% code coverage.

This analysis examines three overlapping test areas:
1. **Statement tests** - 15 Functional + 10 Integration tests
2. **Transaction tests** - 6 Functional + 12 Integration tests  
3. **Connection tests** - Already addressed (1 duplicate removed)

The goal is to identify tests that exercise identical code paths without adding unique coverage value, enabling their removal to optimize test suite execution time while maintaining the 82%+ coverage target.

## [Analysis]

### Statement Test Coverage Analysis

**Source file**: `src/Driver/Firebird/Statement.php`
**Current coverage**: 124/137 statements (90.51%), 6/8 methods

#### Functional/StatementTest.php (15 tests)
| Test Method | Code Path Exercised | Unique? |
|-------------|---------------------|---------|
| testStatementIsReusableAfterFreeingResult | execute(), Result::free() | ✓ |
| testReuseStatementWithLongerResults | execute() with varying result sizes | ✓ |
| testFetchLongBlob | LARGE_OBJECT handling in bindValueInternal() | ✓ UNIQUE |
| testIncompletelyFetchedStatementDoesNotBlockConnection | Connection state handling | ✓ |
| testReuseStatementAfterFreeingResult | execute() reuse pattern | ✓ |
| testReuseStatementWithParameterBoundByReference | bindParam() by-reference | ✓ UNIQUE |
| testReuseStatementWithReboundValue | bindValue() rebinding | ✓ |
| testReuseStatementWithReboundParam | bindParam() rebinding | ✓ |
| testBindParamWithNullLength | bindParam() with null | Partial |
| testBindInvalidNamedParameter | Invalid param exception | ✓ UNIQUE |
| testParameterBindingOrder | Parameter order in execute() | ✓ UNIQUE |
| testFetchInColumnMode | Result fetchOne() | Overlap |
| testExecuteQuery | executeQuery() | Overlap |
| testExecuteQueryWithParams | executeQuery() with params | Overlap |
| testExecuteStatement | executeStatement() | Overlap |

#### Integration/StatementTest.php (10 tests)
| Test Method | Code Path Exercised | Overlaps With |
|-------------|---------------------|---------------|
| testFetchWorks | fetchAssociative/fetchNumeric | Partial - different fetch modes |
| testFetchAllWorks | fetchAllAssociative/fetchAllNumeric | UNIQUE - tests fetchAll variants |
| testFetchColumnWorks | fetchNumeric | testFetchInColumnMode |
| testGetIteratorWorks | Result iteration | UNIQUE - array conversion |
| testExecuteWorks | execute() basic | testExecuteQuery |
| testExecuteWorksWithParameters | execute() with params | testExecuteQueryWithParams |
| testExecuteThrowsExceptionWhenSQLIsInvalid | Exception handling | UNIQUE - syntax error testing |
| testExecuteThrowsExceptionWhenParameterizedSQLIsInvalid | Exception handling | UNIQUE - param error testing |
| testBindValueWorks | bindValue() | testReuseStatementWithReboundValue |
| testBindParamWorks | bindParam() named | testReuseStatementWithReboundParam |

#### Safe Removal Candidates - Statement Tests

**Integration/StatementTest candidates for removal:**
1. `testFetchColumnWorks` - Overlaps with Functional `testFetchInColumnMode`
2. `testExecuteWorks` - Overlaps with Functional `testExecuteQuery`
3. `testExecuteWorksWithParameters` - Overlaps with Functional `testExecuteQueryWithParams`
4. `testBindValueWorks` - Overlaps with Functional `testReuseStatementWithReboundValue`
5. `testBindParamWorks` - Overlaps with Functional `testReuseStatementWithReboundParam`

**Integration/StatementTest tests to KEEP (unique coverage):**
1. `testFetchWorks` - Tests both fetchAssociative AND fetchNumeric in same test
2. `testFetchAllWorks` - UNIQUE: tests fetchAll variants not in Functional
3. `testGetIteratorWorks` - UNIQUE: tests array conversion pattern
4. `testExecuteThrowsExceptionWhenSQLIsInvalid` - UNIQUE: syntax error exception
5. `testExecuteThrowsExceptionWhenParameterizedSQLIsInvalid` - UNIQUE: param error exception

### Transaction Test Coverage Analysis

**Source files**: `src/Driver/Firebird/Connection.php` (transaction methods)
**Current coverage**: 218/341 statements (63.93%)

#### Functional/Driver/Firebird/TransactionTest.php (3 tests)
| Test Method | Code Path | Purpose |
|-------------|-----------|---------|
| testBeginTransactionCommit | beginTransaction(), commit() | Basic commit flow |
| testTransactionIsolationLevel | setTransactionIsolation() | Isolation level attribute |
| testSetTransactionQueryInterception | SET TRANSACTION interception | Firebird-specific |

#### Functional/Connection/TransactionNestingTest.php (3 tests)
| Test Method | Code Path | Purpose |
|-------------|-----------|---------|
| testNestedCommitDoesNotPersistUntilOuterCommit | Savepoint behavior | Nesting semantics |
| testNestedStructureSuccess | Multi-level commit | Success path |
| testNestedRollbackRevertsChanges | Savepoint rollback | Rollback isolation |

#### Integration/TransactionTest.php (12 tests)
| Test Method | Code Path | Overlaps With |
|-------------|-----------|---------------|
| testWillAutoCommitBottomLevelTransaction | Auto-commit | UNIQUE - auto-commit specific |
| testCanSuccessfullyCommitASingleTransactionForInsert | commit() + INSERT | testBeginTransactionCommit (partial) |
| testCanSuccessfullyCommitASingleTransactionForUpdate | commit() + UPDATE | testBeginTransactionCommit (partial) |
| testCanSuccessfullyCommitMultipleTransactionsForInsert | Multi-level INSERT | testNestedStructureSuccess (similar) |
| testCanSuccessfullyCommitMultipleTransactionsForUpdate | Multi-level UPDATE | testNestedStructureSuccess (similar) |
| testCanSuccessfullyRollbackASingleTransactionForInsert | rollback() + INSERT | UNIQUE - explicit rollback |
| testCanSuccessfullyRollbackASingleTransactionForUpdate | rollback() + UPDATE | UNIQUE |
| testCanSuccessfullyRollbackMultipleTransactionsForInsert | Multi-level rollback INSERT | testNestedRollbackRevertsChanges (similar) |
| testCanSuccessfullyRollbackMultipleTransactionsForUpdate | Multi-level rollback UPDATE | testNestedRollbackRevertsChanges (similar) |
| testCanSuccessfullyCommitAndRollbackMultipleTransactionsForInsert | Mixed commit/rollback | UNIQUE - hybrid pattern |
| testCanSuccessfullyCommitAndRollbackMultipleTransactionsForUpdate | Mixed commit/rollback | UNIQUE |

#### Transaction Tests Assessment

**Key difference**: Integration tests are MORE comprehensive (12 tests) than Functional tests (6 tests).

**Functional tests to potentially merge into Integration:**
1. `testBeginTransactionCommit` - Basic case covered by Integration single-transaction tests
2. `testNestedStructureSuccess` - Covered by Integration multi-transaction tests

**Tests to KEEP:**
- `testTransactionIsolationLevel` - UNIQUE: tests isolation level attribute
- `testSetTransactionQueryInterception` - UNIQUE: Firebird-specific SET TRANSACTION interception
- `testNestedCommitDoesNotPersistUntilOuterCommit` - Tests savepoint semantics specifically
- `testNestedRollbackRevertsChanges` - Tests savepoint isolation specifically
- All Integration tests - comprehensive coverage

## [Recommendation]

### Safe Removal Strategy (Baby Steps)

**Round 1: Statement Test Deduplication** (5 tests, ~2-3% time reduction)
Remove from `Integration/StatementTest.php`:
- `testFetchColumnWorks`
- `testExecuteWorks`  
- `testExecuteWorksWithParameters`
- `testBindValueWorks`
- `testBindParamWorks`

**Round 2: Transaction Test Consolidation** (2 tests, ~1% time reduction)
Remove from `Functional/Driver/Firebird/TransactionTest.php`:
- `testBeginTransactionCommit`

Remove from `Functional/Connection/TransactionNestingTest.php`:
- `testNestedStructureSuccess`

### Tests NOT to Remove

**Integration/StatementTest.php - KEEP:**
- `testFetchWorks` - Diverse fetch mode coverage
- `testFetchAllWorks` - fetchAll variants
- `testGetIteratorWorks` - Iterator pattern
- `testExecuteThrowsExceptionWhenSQLIsInvalid` - Exception testing
- `testExecuteThrowsExceptionWhenParameterizedSQLIsInvalid` - Exception testing

**Functional tests with UNIQUE value - KEEP ALL:**
- Blob handling, by-reference params, parameter ordering, invalid param exceptions

## [Implementation Order]

1. **Create branch checkpoint** - Tag current state
2. **Remove 5 Integration/StatementTest methods** - Most clear overlaps
3. **Run coverage validation** - Verify ≥82.46%
4. **Commit if coverage maintained**
5. **Remove 2 Transaction test methods** - Secondary overlaps
6. **Run coverage validation** - Verify ≥82.46%
7. **Commit if coverage maintained**
8. **Document final metrics**

## [Risk Assessment]

| Action | Risk | Mitigation |
|--------|------|------------|
| Remove Statement tests | Low - clear overlaps | Coverage check after each removal |
| Remove Transaction tests | Medium - different base classes | Test both FunctionalTestCase and Integration paths |
| Coverage drop | Low | Revert immediately if <82% |

## [Expected Outcome]

- **Tests removed**: 7 (5 Statement + 2 Transaction)
- **Tests remaining**: 1577 (from 1584)
- **Coverage target**: ≥82.46% (maintained)
- **Time savings**: Estimated 3-5% reduction in execution time
