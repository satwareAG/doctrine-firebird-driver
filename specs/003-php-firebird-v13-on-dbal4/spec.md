# Spec: php-firebird v13.0 on DBAL 4.4.x

## Status: Implemented

**Version**: 4.5.0
**Branch**: 4.4.x
**Date**: 2026-07-19

## Context

The `4.4.x` branch targets Doctrine DBAL 4.4.x. It was dormant since
`v4.4.1` (2026-04-10), running on `php-firebird v7.3.0` with `ext-firebird: *`.

This spec covers the work to bring the branch to parity with the `3.10.x`
branch (v3.19.0) and adopt `php-firebird v13.0.0` optimizations.

## Approach

Instead of piecemeal backports, the `3.10.x` branch (v3.13.0 through
v3.19.0, 181 commits) was merged into `4.4.x` using `git merge -X theirs`.
This brought:

- `ext-firebird: ^13.0` constraint (was `*`)
- `satwareag/php-firebird-stubs: ^13.0.0` (was `^7.3|^10.0`)
- CI matrix: php-firebird v13.0.0 (was v7.3.0)
- #127 binary BLOB corruption fix in charset middleware
- #114/#115 forceNewConnection option
- #124 SQL Parser/Visitor decoupling from DBAL internals
- #117/#119 charset encoding asymmetry docs + regression test
- Modern `=== null` / `=== false` checks (no `is_resource()` on connections)
- All new tests from 3.10.x

DBAL4-specific API adaptations were then re-applied on top of the merged base.

## DBAL4 API changes adopted

### Driver signatures (DBAL4 `Driver\Connection` interface)

| Method | DBAL3 | DBAL4 |
|--------|-------|-------|
| `quote()` | `quote($value, $type)` | `quote(string $value): string` |
| `lastInsertId()` | `lastInsertId($name = null)` | `lastInsertId(): int\|string` (throws `NoIdentityValue`) |
| `beginTransaction()` | `: bool` | `: void` |
| `commit()` | `: bool` | `: void` |
| `rollBack()` | `: bool` | `: void` |
| `getNativeConnection()` | not required | `: object\|resource` (throws if null) |
| `bindValue()` | `: bool` | `: void` |
| `bindParam()` | `: bool` | removed |
| `exec()` | available | removed (use `executeStatement()`) |

### Driver extensions (non-DBAL API)

- `lastInsertIdBySequence(string $name): int|string|false` — Firebird-specific
  extension for named generator/sequence lookups. Replaces the DBAL3
  `lastInsertId($name)` path.

### Platform changes

- `getCreateTableSQL(Table $table)` — single parameter (DBAL4 dropped `$createFlags`)
- `getLocateExpression(string, string, ?string)` — typed params (DBAL4)
- `doModifyLimitQuery(string, ?int, int)` — typed params (DBAL4)
- `ColumnDiff::getChangedColumns()` — replaces `getModifiedColumns()` + `getRenamedColumns()`
- `ColumnDiff::getOldColumn()` — replaces `fromColumn` / `getOldColumnName()`
- `TransactionIsolationLevel` — enum (was int constants)
- `setSchemaAssetsFilter()` — requires callable (not null)
- `setNestTransactionsWithSavepoints(false)` — no longer supported

### SQL changes

- `doModifyLimitQuery`: switched from legacy `ROWS n TO m` to SQL:2008
  `FETCH FIRST n ROWS ONLY` / `OFFSET n ROWS FETCH NEXT n ROWS ONLY`.
  Firebird 3.0+ supports both; DBAL4 tests expect SQL:2008.

## php-firebird v13.0 optimizations adopted

### R3: `fbird_ping()` — Connection::ping()

New `ping()` method using `fbird_ping()` (v13.0.0) for lightweight
`IAttachment::ping()` roundtrip. Verifies server is alive without
SQL parsing overhead.

### R4: `fbird_server_version()` — Driver::connect()

Replaced `fbird_service_attach` + `fbird_server_info` + `fbird_service_detach`
with post-connect `fbird_server_version()`. Eliminates the fragile
service-attach roundtrip (which fails silently on some Docker images
where `service_mgr` is not defined).

**Contract update (2026-10-31, #257):** the return value is treated as a
version-bearing string and parsed defensively - the first
`V?(\d+\.\d+(\.\d+)?(\.\d+)?)` token is extracted via regex scan; on
`false`, empty, or no token the `'3.0'` minimum default is kept. The
byte-offset parse of the raw `isc_info_firebird_version` buffer (1 byte
impl type + 1 byte length) was removed: php-firebird v13.3.1 (#696 fix)
returns the clean server version string (`LI-V3.0.14.33856 Firebird 3.0`),
older releases return clump bytes the offsets cannot address - neither
shape survives byte-slicing, and a mangled string fails the anchored
`^(LI|WI)-` regex in `createDatabasePlatformForVersion()`, silently
falling back to the base platform. The token scan is stable across all
three historical return shapes (raw buffer, #696 garble, clean string)
because the server's own version digits are the first version-like token
in each.

### R9: `fbird_escape_literal()` — Connection::quote()

Replaced manual `"'" . fbird_escape_string($value) . "'"` with
`fbird_escape_literal()` (v13.0.0). Same result, handled server-side.

## Security contract (extension integration)

Added 2026-10-31 per coordination issue #268 (php-firebird v13.4.0 security
train, upstream epic satwareAG/php-firebird#728). Documents the security
invariants of this driver's `fbird_*`/`Firebird\*` usage as input to the
upstream hardening PRs.

### Identifier case-handling contract

- Firebird stores unquoted identifiers uppercased. This driver emits quoted
  identifiers only when the name requires it; unquoted names are passed
  case-folded exactly (contract pinned upstream in satwareAG/php-firebird#730,
  keeps `tests/Test/Unit/Driver/ConnectionTest.php:342` call sites green).
- Introspection reads `RDB$` directly with bound `UPPER(?)` plus the driver's
  own quote-doubling (`src/Driver/Firebird/Connection.php:429`); no
  `fbird_list_fields` / `fbird_meta_data` interpolation paths.
- Known gap (prior art: #155): introspection cannot distinguish identifiers
  created quoted (stored mixed-case) from unquoted (stored uppercased);
  lowercase-presence is the heuristic. Quoted mixed-case views/sequences are
  documented as unsupported. Closing this gap requires upstream signal
  (Option C in #155).

### Privileged-API call sites (exhaustive, src/)

| API | Call site | Mode |
|-----|-----------|------|
| `fbird_kill_attachment` | `src/Driver/Firebird/Connection.php:731` | connection resource + explicit attachment id |
| `fbird_drop_table_force` | `src/Driver/Firebird/Connection.php:749` | connection resource + identifier (case-fold contract above) |
| `fbird_drop_db` | `src/Schema/FirebirdSchemaManager.php:111` | **resource-mode only**: explicit `fbird_connect()` with credential params first (`:94`, empty-string defaults, auth fails loudly when unset) |

Zero callers of `fbird_list_fields`, `fbird_meta_data`, `fbird_blob_export`,
`fbird_backup`, `fbird_restore` in src/ and tests/ (verified by repo grep,
2026-10-31). The deletion of the `fbird_drop_db()` string-mode
SYSDBA/masterkey fallback in v13.4.0 (satwareAG/php-firebird#731) is safe for
this consumer; confirmation posted upstream 2026-10-31.

### Credential-sourcing for connections (no silent defaults)

- The driver's own defaults live at `src/Driver/Firebird/Driver.php:55-56`
  (`$params['user'] ?? 'SYSDBA'`, `$params['password'] ?? 'masterkey'`). These
  are driver-level defaults applied before any extension call, independent of
  the extension's (now deleted) string-mode fallback: every connect call in
  src/ passes credentials explicitly.
- `FirebirdSchemaManager::dropDatabase()` deliberately deviates: empty-string
  credential defaults (`?? ''`) so an unconfigured drop fails at auth instead
  of succeeding silently against privileged defaults.
- Test/debug fixtures (integration constants, `tests/debug/*`) hardcode
  SYSDBA/masterkey for containers only; no production path reads them.

## Deferred optimizations

| R# | Optimization | Reason |
|----|-------------|--------|
| ~~R1~~ | ~~BLOB sub_type via `fbird_field_info()`~~ | **DONE** in v4.5.2 (commit `25455f4`). `Result::getBlobSubTypes()` + `CharsetResultMiddleware::decodeValue()` use `fbird_field_info()` sub_type lookup with NULL-byte heuristic fallback. |
| R2 | Per-connection error context | Marginal benefit for single-connection usage |
| R5 | Statement timeout option | Needs connection param wiring + FB4+ guards |
| R6 | Schema introspection via `fbird_list_tables` | Breaks DBAL's SQL-string abstraction |
| R7 | DecFloat native type | BC concern; needs `decfloatNative` option gate |
| R8 | `fbird_fetch_all()` | Incompatible with per-row BLOB/charset processing |
| R10 | `fbird_error_field()` | Additive; no current demand |
| R11 | `fbird_blob_export()` | No downstream demand |
| R12 | Per-statement timeout | DBAL has no per-query timeout concept |

## Known issues

### Metadata lock hang after schema introspection (php-firebird #540)

Firebird holds metadata locks at the transaction level. `fbird_commit_ret()`
(used for auto-commit simulation) keeps the transaction open, which holds
metadata locks from SELECT cursors. DDL operations following schema
introspection hang indefinitely.

This cannot be fixed at the driver level — it requires an extension-level
feature. Issue filed: https://github.com/satwareAG/php-firebird/issues/540

Workaround: `gc_collect_cycles()` before DDL operations to force PHP to
destroy Result objects. With a clean database (no stale tables), all tests
pass without hangs.

### DBAL 4.4 -> 5.0 forward-looking deprecations

90 PHPStan baseline entries for deprecated DBAL 4.4 methods
(`getQuotedName`, `getName`, `getModifiedColumns`, etc.). These are
forward-looking deprecations that DBAL 4.4 itself uses internally.
They will be addressed when DBAL 5.0 is released.

## Out of scope

- DBAL 5.0 migration (future release)
- ~~R1~~ (done in v4.5.2), R2-R8, R10-R12 optimizations (future release)
- Merge 4.4.x -> main (separate decision)
- Firebird 6.0 features (deferred to v14)

## Verification

### v4.5.0 (initial release, 2026-07-19)

| Check | Result |
|-------|--------|
| PHPStan level 8 (src/) | 0 errors (90 baselined deprecation warnings) |
| Unit tests | 1460 tests, 3 pre-existing PHP 8.5 errors, 0 failures |
| Functional FB3 | 530 tests, 0 errors, 0 failures |
| Functional FB4 | 530 tests, 0 errors, 0 failures |
| Functional FB5 | 530 tests, 0 errors, 0 failures |
| Integration-ReadOnly | 24 tests, 0 errors, 0 failures |
| Integration-Write | 96 tests, 0 errors, 0 failures |
| ext-firebird | v13.0.0 loaded |
| DBAL | 4.4.3 installed |
| Stubs | v13.0.0 |

### v4.5.1 (follow-up release, 2026-07-20)

| Check | Result |
|-------|--------|
| PHPStan level 8 | 0 errors |
| Psalm | 0 errors (112 baselined pre-existing) |
| PHP_CodeSniffer | 0 errors |
| Unit tests | 1620 tests, 0 errors, 0 failures, 49 skipped |
| Functional FB3 | 575 tests, 0 errors, 0 failures, 59 skipped |
| Functional FB4 | 575 tests, 0 errors, 0 failures, 55 skipped |
| Functional FB5 | 575 tests, 0 errors, 0 failures, 55 skipped |
| Integration-ReadOnly | 24 tests, 0 errors, 0 failures |
| Integration-Write | 96 tests, 0 errors, 0 failures, 3 skipped |
| CI (all jobs) | 14/14 green |

### Post-implementation notes (v4.5.1)

**Additional fixes beyond the original spec:**
- `FirebirdComparator::__construct` now accepts `ComparatorConfig` (was silently dropped)
- `FirebirdPlatform::columnsEqual()` override for autoincrement detection
- `FirebirdSchemaManager::dropDatabase()` `is_resource()` -> `=== false` bug fix
- `FirebirdSchemaManager::createDatabase()` uses `fbird_create_database()` (deprecation + security)
- `Driver::connect()` `@` suppression on all 3 connect branches
- `float`/`real` type mappings corrected to `SMALLFLOAT`
- Windows CI updated to php-firebird v13.0.0 DLL

**Test coverage expanded beyond spec:**
- 17 platform tests from DBAL 4.x AbstractPlatformTestCase (104 instances)
- 43 comparator tests from DBAL 4.x AbstractComparatorTestCase
- Adapted functional test classes: ForeignKeyConstraintTest, AlterTableTest, BigIntTypeTest, SequenceTest, ResultMetadataTest

### v4.5.2 (R1 implementation, 2026-07-20)

| Check | Result |
|-------|--------|
| PHPStan level 8 | 0 errors |
| Psalm | 0 errors (112 baselined) |
| PHP_CodeSniffer | 0 errors |
| Unit tests | 1620 tests, 0 errors, 49 skipped |
| Functional (per FB version) | 576 tests, 0 errors, 54-58 skipped |

**R1 implementation:**
- `Result::getBlobSubTypes()`: lazy-cached `fbird_field_info()` sub_type lookup
- `CharsetResultMiddleware::decodeValue()`: uses sub_type 0 (binary) → passthrough, sub_type 1 (text) → transcode
- Fallback: NULL-byte heuristic when inner Result is not native Firebird Result

### v4.6.0 (Diagnostics & Observability, 2026-08-08)

| Check | Result |
|-------|--------|
| PHPStan level 8 | 0 errors (90 baselined DBAL 4.4->5.0 deprecations) |
| Psalm | 0 errors (114 baselined: +2 `PossiblyUnusedMethod` for accessors) |
| PHP_CodeSniffer | 0 errors |
| Unit tests | 1624 tests, 0 errors, 48 skipped (DBAL 4.4.4 auto-lifted `detectRenamedIndexes` skip) |
| Functional (per FB version) | 576 tests, 0 errors, 54-58 skipped |
| Integration | 24 + 96 tests, 0 errors, 3 skipped |
| CI | 14/14 green (CodeQL, Windows, Matrix) |

**Changes:**
- `Exception::getFbirdErrCode(): int` and `Exception::getFbirdErrMsg(): string` named accessors (#147)
- `ext-firebird` constraint bumped to `^13.0.1` (excludes v13.0.0 with 15 critical bugs)
- `php-firebird-stubs` bumped to `^13.0.1` (v13.0.3)
- `squizlabs/php_codesniffer` bumped to `^4.0.2` (CVE-2026-67434)
- 3 Dependabot PRs merged (checkout 7.0, codeql-action 4.37.4)
- #145/#146 closed as won't-fix (belongs in consumer middleware)
