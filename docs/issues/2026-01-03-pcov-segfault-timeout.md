# Issue: PCOV Segfault and Timeout in Full CQC Run

## Description
When running the full CQC suite with PCOV enabled (`./docker-cqc.sh --coverage`), the execution fails with a timeout in `testIntrospectReservedKeywordTableViaListTables` and subsequently crashes with a Segmentation Fault.

## Symptoms
1. **Timeout**: `Satag\DoctrineFirebirdDriver\Test\Functional\Schema\Firebird3SchemaManagerTest::testIntrospectReservedKeywordTableViaListTables` fails with "Execution aborted after 5 seconds".
2. **Segfault**: After generating the coverage report, PHP crashes with `Segmentation fault (core dumped)`.

## Analysis
- **Individual Tests Pass**: Running `testIntrospectReservedKeywordTableViaListTables` individually with coverage passes in ~1.2 seconds.
- **No Coverage Pass**: Running the full suite without coverage passes in ~3 minutes 20 seconds with no segfaults.
- **Syntax Errors Fixed**: Fixed typed constants (PHP 8.3 feature) that were causing syntax errors in PHP 8.1 environment.

## Root Cause
- **Timeout**: Likely due to PCOV overhead combined with the number of tables in the database during the full run, or cumulative memory usage.
- **Segfault**: Likely an issue in `php-firebird` extension interacting with PCOV or PHP shutdown phase when coverage is enabled.

## Workaround
- Run tests without coverage for stability verification.
- Run individual tests with coverage for specific checks.
- Use `--quick` mode in `docker-cqc.sh` for static analysis.

## Future Actions
- Investigate `php-firebird` extension stability with PCOV.
- Optimize `listTables()` in `FirebirdSchemaManager` to avoid N+1 queries.
- Consider increasing PHPUnit timeout for coverage runs.
