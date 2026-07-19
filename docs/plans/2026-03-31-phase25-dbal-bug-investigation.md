# Phase 2.5: Functional Test BINARY_FETCH_TABLE Lock Investigation

**Date**: 2026-03-31
**Status**: Complete
**Branch**: 3.10.x
**Issue**: #103

## Problem

`BinaryDataAccessTest::setUp()` calls `dropAndCreateTable()` which fails with
`TableExistsException: BINARY_FETCH_TABLE already exists` because the previous
test's `markConnectionNotReusable()` + `disconnect()` cycle fails to fully
release Firebird metadata locks before the next test's `setUp()` runs.

## Root Cause

1. `BinaryDataAccessTest::tearDown()` calls `markConnectionNotReusable()`
2. `FunctionalTestCase::disconnect()` attempts to drop created tables, but
   Firebird's metadata lock from the implicit transaction (INSERT in setUp)
   prevents the DROP TABLE from succeeding
3. `disconnect()` silently swallows the "in use" error after 3 retries
4. `disconnect()` then closes the DBAL connection and nulls shared connection
5. Next test's `connect()` creates a NEW connection
6. `setUp()` -> `dropAndCreateTable()` -> `dropTableIfExists()` retries but
   the old connection's resources may not be fully released (PHP reference
   cycles keep the `fbird_*` resource alive)
7. `createTable()` fails with "already exists"

## Fix (commit f6f51b8)

In `dropAndCreateTable()`, when `createTable()` throws "already exists":

1. Close the DBAL connection (`$this->connection->close()`)
2. Force PHP garbage collection (`gc_collect_cycles()`) to release any
   lingering `fbird_*` resource references
3. Wait 200ms for Firebird server to release metadata locks
4. DBAL lazy-reconnects on the next query
5. Retry `dropTableIfExists()` + `commit()` + `createTable()`

## Previous Fixes (same session)

- **commit 0512822**: Cross-process DB guard for Integration tests (ALBUM
  count check), mark `lastInsertId` test incomplete for php-firebird v10.3.9
- **commit 5108ce4**: Initial retry logic (100ms, driver-level rollback) -
  insufficient because PHP reference cycles prevented full lock release
