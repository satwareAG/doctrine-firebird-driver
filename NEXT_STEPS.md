# Next Steps - doctrine-firebird-driver

**Last updated:** 2026-07-19
**Branch:** `4.4.x` | **Target:** `v4.5.0` | **PHP:** 8.2-8.5 | **Firebird:** 3.0/4.0/5.0
**Extension:** php-firebird v13.0.0 | **DBAL:** 4.4.3

---

## Current State

### v4.5.0 — php-firebird v13.0 on DBAL 4.4.x

**Status:** Ready for release.

The `4.4.x` branch has been restructured to merge the modern `3.10.x` base
(v3.13.0-v3.19.0, 181 commits) with DBAL4-specific API adaptations on top.
The result is a clean driver that works with DBAL 4.4.x + ext-firebird v13.0.0.

### Test Results

| Suite | Tests | Errors | Failures | Skipped |
|-------|-------|--------|----------|---------|
| Unit | 1460 | 3 (pre-existing PHP 8.5) | 0 | 45 |
| Functional FB3 | 530 | 0 | 0 | 40 |
| Functional FB4 | 530 | 0 | 0 | 36 |
| Functional FB5 | 530 | 0 | 0 | 36 |
| Integration-ReadOnly | 24 | 0 | 0 | 0 |
| Integration-Write | 96 | 0 | 0 | 3 |

### Quality Gates

| Gate | Result |
|------|--------|
| PHPStan Level 8 | 0 errors (90 baselined DBAL 4.4->5.0 deprecation warnings) |
| ext-firebird | v13.0.0 loaded |
| DBAL | 4.4.3 installed |
| Stubs | v13.0.0 |

### What was done (v4.5.0)

1. **Merge 3.10.x into 4.4.x** — brought all 181 modern commits (v3.13.0-v3.19.0)
   including v13 support, #127 BLOB fix, #114/#115 forceNewConnection, #124 SQL
   Parser decoupling, #117/#119 charset asymmetry docs + tests.
2. **DBAL4 API adaptations** — re-applied Connection/Statement/Platform/Schema
   signature changes for DBAL4 (quote, lastInsertId, beginTransaction/commit/
   rollBack void, getNativeConnection, TransactionIsolationLevel enum,
   ColumnDiff API, getCreateTableSQL, getLocateExpression, doModifyLimitQuery).
3. **CI promotion** — FB4/FB5 promoted from experimental to required.
4. **v13 optimizations** — R3 (fbird_ping), R4 (fbird_server_version replaces
   service-attach), R9 (fbird_escape_literal).
5. **Code review fixes** — dead code in ConnectionWrapper::lastInsertId(),
   setLastInsertTable moved after failure check, misnamed test renamed.
6. **SchemaTest investigation** — root cause identified (Firebird metadata locks
   at transaction level), issue filed to php-firebird (#540).

### DBAL 3 series (3.10.x branch) — COMPLETE

The `3.10.x` branch is in maintenance mode. Last release: `v3.19.0` (2026-07-19).
All critical fixes are in v3.19.0. No further 3.10.x releases planned unless
critical bugs are found.

### Known issues

1. **Metadata lock hang** (php-firebird #540) — `fbird_commit_ret()` holds
   metadata locks from SELECT cursors, causing DDL to hang after schema
   introspection. Workaround: `gc_collect_cycles()` before DDL. Filed:
   https://github.com/satwareAG/php-firebird/issues/540

2. **DBAL 4.4 -> 5.0 deprecations** — 90 PHPStan baseline entries for
   deprecated methods (`getQuotedName`, `getName`, etc.). Forward-looking;
   will be addressed when DBAL 5.0 is released.

3. **3 pre-existing PHP 8.5 errors** — PhpunitScriptTest `realpath()` returns
   `false` for non-existent paths on PHP 8.5. Same as 3.10.x.

### Deferred for future releases

- R1 (BLOB sub_type via fbird_field_info) — needs middleware refactor
- R5-R7 (statement timeout, schema introspection, DecFloat) — medium effort
- R10-R12 (error_field, blob_export, per-stmt timeout) — low ROI
- Merge 4.4.x -> main — separate decision
- Firebird 6.0 features — deferred to v14

### Spec

See `specs/003-php-firebird-v13-on-dbal4/spec.md` for full design document.
