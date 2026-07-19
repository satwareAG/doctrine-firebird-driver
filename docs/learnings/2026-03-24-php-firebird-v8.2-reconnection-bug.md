# php-firebird v8.2.0 Reconnection Bug

**Date:** 2026-03-24
**Severity:** Critical (blocks all integration tests)
**Status:** Workaround pending

## Symptom

After `FirebirdSchemaManager::createDatabase()` creates a DB file via `isql` subprocess, the SAME PHP process cannot connect to that file via `fbird_connect()`. Connection resource is internally invalid.

## Diagnostic Evidence

| Check | Expected | Actual |
|-------|----------|--------|
| `fbird_connect()` return | `resource` | `resource` (but invalid) |
| `RDB$GET_CONTEXT('SYSTEM','DB_NAME')` | `/firebird/data/test.fdb` | Empty string |
| `SELECT 1 FROM RDB$DATABASE` | 1 row | 0 rows |
| DDL execution | Creates tables | No error, no effect |
| `isql` same DB path | Works | Works correctly |
| DB file on disk | Exists | Exists at correct path |

## Root Cause

php-firebird v8.2.0 internal state corruption after `isql` subprocess creates the database. The `fbird_connect()` C function returns a resource handle but the internal Firebird attachment is invalid. All subsequent operations silently fail without errors.

## Key Insight

DDL statements execute WITHOUT throwing errors on an invalid connection. This is the dangerous part - the failure is completely silent. Always verify DB state after connection, never assume DDL success.

## Workaround

Execute ALL DDL and seed SQL via `isql` subprocess (same pattern as `createDatabase()`). Only use `fbird_connect()` for the actual test queries after the DB is fully populated.

## Anti-Patterns Discovered

1. **Never assume `fbird_connect()` success = valid connection** - Always health-check
2. **Never assume DDL executed = tables exist** - Always verify with `SELECT COUNT(*)`
3. **Static caches on connection state** - Use instance-level caches (ConnectionWrapper fix)
4. **Regex on quoted identifiers** - `extractIdentityColumn()` fails on `"ALBUM"` - use raw SQL