# Next Steps — doctrine-firebird-driver

**Last session:** 2026-03-24 (php-firebird v8.2.0 reconnection bug diagnosed)
**Branch:** `3.10.x` | **Status:** Blocked by php-firebird upstream bug

---

## 🔴 Active Blocker: php-firebird v8.2.0 Reconnection Bug

**Issue:** After `FirebirdSchemaManager::createDatabase()` creates a DB file via `isql` subprocess, the SAME PHP process cannot connect to that file via `fbird_connect()`. Connection gets `null` resource, empty DB path, `false` health check. All DDL/seed SQL executed on this broken connection silently fails, resulting in 0 rows for every test.

**Evidence:**
- `RDB$GET_CONTEXT('SYSTEM','DB_NAME')` returns empty string after `fbird_connect()`
- `SELECT 1 FROM RDB$DATABASE` returns no rows
- DDL statements execute WITHOUT throwing errors (silent failure on null resource)
- `isql` can connect and query the same DB file fine
- DB file exists at correct path (`/firebird/data/test.fdb`)

**Root Cause:** php-firebird v8.2.0 internal state corruption after `isql` subprocess creates the DB. The `fbird_connect()` C function returns success but the resource is internally invalid.

**Workaround Plan (for tomorrow):**
1. Rewrite `AbstractIntegrationTestCase::installFirebirdDatabase()` to execute ALL DDL+seed SQL via `isql` subprocess (not PHP connection)
2. Add `TestUtil::runIsql()` helper that reuses the `proc_open` pattern from `createDatabase()`
3. After `isql` populates the DB, attempt ONE `fbird_connect()` for the actual test
4. If single connect still fails: confirmed unworkable php-firebird bug, skip tests with clear message
5. File upstream issue on php-firebird repo

**Files to modify:**
- `tests/Test/TestUtil.php` - Add `runIsql()` method
- `tests/Test/Integration/AbstractIntegrationTestCase.php` - Rewrite `installFirebirdDatabase()`
- `src/Schema/FirebirdSchemaManager.php` - Reference for `isql` pattern

**DO NOT REVERT changes already made:**
- `ConnectionWrapper.php`: instance cache, try/catch for TableDoesNotExist
- `AbstractIntegrationTestCase.php`: raw SQL seed with double-quoted table names

---

## ✅ DBAL 3 series (3.10.x branch) - php-firebird v8 UPGRADE - COMPLETE

All goals for the v8 upgrade and modernization have been fulfilled:
- ✅ **php-firebird v8.0.0 Requirement** - Updated `composer.json` and removed all legacy guards.
- ✅ **Test Suite Modernized** - Removed redundant version checks, deleted SQLite-only tests.
- ✅ **Upstream Collaboration** - Created 7 GitHub issues (#119-#125) on php-firebird.

---

## 🚀 Future: DBAL 4.x Migration (4.4.x branch)

Blocked until reconnection bug is resolved or worked around.

### Roadmap for 4.4.x:
1. **Branch Setup**: Create `4.4.x` from `3.10.x`.
2. **Dependency Update**: Require `doctrine/dbal: ^4.1`.
3. **API Refactoring**: Replace deprecated interfaces, update return types.
4. **CI Matrix**: PHP 8.4/8.5 + Firebird 4/5.

---

## 📦 Maintenance (3.10.x branch)

Critical fixes only. All new features target `4.4.x`.

### Session 2026-03-24 Changes (WIP, not committed):
- `ConnectionWrapper.php`: Instance-level identity column cache, TableDoesNotExist guard
- `AbstractIntegrationTestCase.php`: Raw SQL seed with double-quoted table names
- `TestUtil.php`: Connection caching, health check verification
- `Driver.php`: Connect string improvements
- `Connection.php`: Various defensive checks
- Multiple functional test fixes
- `Dockerfile`: PHP 8.4.19, php-firebird v8.2.0
- CI workflow updates