# Spec: php-firebird v13.4.0 Security Hardening Adoption

## Status: Implemented

**Version**: 4.8.0
**Branch**: 4.5.x
**Date**: 2026-10-31
**Coordination**: #268 | Upstream: satwareAG/php-firebird#728 (epic), #737 (spec)

## Context

php-firebird v13.4.0 (2026-10-31) ships the security hardening train
(epic #728, closes #729-#736). This driver is the primary DBAL consumer of
the `fbird_*`/`Firebird\*` privileged layers. This spec records the security
contract between both sides and the adoption decision: floor
`ext-firebird ^13.2.12` -> `^13.4.0` (#268).

Because the caret range `^13.2.12` already admitted v13.4.0, the floor bump's
effect is exclusion: v13.2.x and v13.3.x (pre-hardening) are no longer
resolvable alongside this driver.

## Security contract

### Identifier case-handling (prior art: #155)

- Unquoted identifiers are uppercase-folded by the engine; quoted identifiers
  are verbatim. Unquoted lowercase names keep the uppercase-fold contract
  across the extension boundary (upstream #730 preserves this explicitly for
  the driver's `dropTableForce()` call site, pinned at
  `tests/Test/Unit/Driver/ConnectionTest.php:342`).
- Under v13.4.0, names that cannot represent valid identifiers (trailing SQL
  comments such as `SAFE_A -- junk`) fail closed with `Invalid table name`
  instead of executing - previously a silent table drop (#730).

### Privileged-API call sites (audited 2026-10-31, #268)

| API | Call sites | Contract under v13.4.0 |
|-----|-----------|------------------------|
| `fbird_kill_attachment` | `Connection.php` (killAttachment) | unchanged |
| `fbird_drop_table_force` | `Connection.php` (dropTableForce) | identifier validated, fail-closed on unrepresentable names (#730) |
| `fbird_drop_db` | `FirebirdSchemaManager.php` (dropDatabase) | resource overload; string-overload `SYSDBA`/`masterkey` fallback removed upstream (#731) - no reliance here (audited, finding 4 of #268) |
| `fbird_list_fields`, `fbird_meta_data`, `fbird_blob_export`, `fbird_backup`, `fbird_restore` | zero | schema introspection queries `RDB$` directly with bound `UPPER(?)`; upstream #729/#733 fixes are defense in depth for downstream extension users |

### Credential sourcing

- No silent defaults at the extension boundary: `fbird_drop_db()` string mode
  requires credentials since #731. This driver's connect path sources
  credentials from DBAL connection params.
- Known residue: `Driver.php` module-level `SYSDBA`/`masterkey` constants are
  this driver's own last-resort defaults (pre-dating this spec). Policy: no
  new code paths may rely on them; removal is tracked via #268.
- Extension-side: DPB/SPB credential staging buffers are wiped after
  hand-off (#736) - benefits this driver's every connect, zero wire delta.

### Savepoint and sequence name validation (#732)

- DBAL-generated savepoint names (`DOCTRINE2_SAVEPOINT_n`) are alnum-safe.
- BREAKING for custom names: `Connection::createSavepoint()` and sequence
  names with invalid identifier characters now throw extension-side.
- Length gate is version-aware: 63 bytes (FB 4.0+/5.0 client builds), 31
  bytes (3.0.x) - matches engine metadata limits.

## Changes

- `composer.json`: `ext-firebird ^13.4.0`, `satwareag/php-firebird-stubs ^13.4.0`
- `ci.yml`: extension build/cache pins v13.2.12 -> v13.4.0 (matrix keys
  self-bust on version; quality job bust suffix v14 -> v15)
- `specs/metadata.json`: upstream pin 13.4.0
- `windows.yml` DLL pin: deliberately deferred - it is the observed artifact
  of the #167 soak; coordinated bump at soak closure (2026-11-16+)

## Validation

- Full functional matrix (FB 3.0/4.0/5.0) against the v13.4.0 extension (#268
  ask 3) - CI validates the pinned extension; upstream PR gates already run
  this suite against php-firebird main.
- #730 case-fold: `ConnectionTest.php:342` (`test_table` unquoted lowercase)
  stays green.
- #732: functional savepoint/sequence coverage must stay green on all three
  engine versions.

## Downstream

Private consumers (names redacted per IPADP) pair this driver with the
extension; handover folded into #259: consumers must resolve `ext-firebird
>= 13.4.0` with their next composer update of this driver.
