# Feature Specification: Force-New Connection Option

**Feature Branch**: `feat/force-new-connection`
**Created**: 2026-07-08
**Status**: Draft
**Issue**: #114

## Context

`fbird_connect()` reuses existing connections when called with identical parameters
(php-firebird `fbird_connection.c:377-378`; README L674-687). This matches PostgreSQL's
`pg_connect()` semantics and is desirable in production. However, in test suites that
boot/shutdown Doctrine kernels between tests (PHPUnit, Pest), this creates a race:

1. Test A: `Driver::connect()` -> `fbird_connect(host, db, user, pass)` -> link #1.
2. Kernel shutdown -> old driver `Connection` unreferenced -> PHP GC has not collected it yet.
3. Test B: `Driver::connect()` with identical params -> **returns the same link #1** (reuse).
4. GC eventually runs -> old `Connection::__destruct()` calls `fbird_close(link #1)`.
5. New `Connection` now holds a closed link -> `prepare()` throws
   `Connection is not valid or has been closed.` (`src/Driver/Firebird/Connection.php:250`).

The current workaround - calling `gc_collect_cycles()` after every kernel shutdown -
leaks driver internals into downstream test base classes. Confirmed in `amicron-platform`
CI (2 intermittent test errors).

php-firebird provides `FBIRD_CONNECT_FORCE_NEW` (value: 2, since v7.0.0; current
requirement is `ext-firebird: ^12.0`) to bypass connection reuse. This driver does not
yet expose it.

### Latent bug discovered during research

`Driver::connect()` never passed `$params['role']` to `fbird_connect()`, although
`docs/DSN.md` documents `role` as a supported parameter and `DsnParserTest` confirms it
flows into `$params['role']`. This is fixed in the same change since the `FORCE_NEW`
flag is the 8th argument, forcing us to pass the 7th (`role`) explicitly.

### Firebird role validation (from engine source)

During attachment (`scl.epp:1120-1150`), Firebird validates the requested role via
`SCL_role_granted()` (`scl.epp:983-1030`), which checks `RDB$ROLES` CROSS
`RDB$USER_PRIVILEGES` for an "M" (member) privilege granted to the user or PUBLIC.
If the role is not found or not granted, it is **silently dropped**
(`sql_role = NULL`, scl.epp:1143-1144) and `CURRENT_ROLE` falls back to
`NULL_ROLE = "NONE"` (`constants.h:93`).

Key implications:
- `RDB$ADMIN` is a system constant (`constants.h:94`), NOT a row in `RDB$ROLES`.
  Connecting with `role='RDB$ADMIN'` silently drops it; `CURRENT_ROLE` returns `NONE`.
- `CREATE ROLE` (`DdlNodes.epp:15294`) only inserts into `RDB$ROLES`; it does NOT
  auto-grant membership. An explicit `GRANT ... TO PUBLIC` (or to the user) is
  required for `SCL_role_granted()` to return true.
- The role name must match exactly (case-sensitive `CHAR` comparison in BLR).

## User Scenarios & Testing

### User Story 1 - Isolated connections in test suites (Priority: P1)

**Why this priority**: This is the primary motivation. Without it, downstream projects
must pollute test base classes with `gc_collect_cycles()` hacks.

**Independent Test**: Call `Driver::connect()` twice with identical params and
`forceNewConnection=true`; assert the two returned `Connection` objects hold distinct
native `Firebird\Connection` instances (by identity: `$c1->getNativeConnection() !==
$c2->getNativeConnection()`).

**Acceptance Scenarios**:
1. **Given** two consecutive `Driver::connect()` calls with identical params and
   `forceNewConnection=true`,
   **When** both connections are obtained,
   **Then** their native `Firebird\Connection` objects are distinct instances.
2. **Given** the first connection is destroyed (destructor runs `fbird_close`),
   **When** the second connection calls `prepare()`,
   **Then** no `Connection is not valid or has been closed` error is raised.

### User Story 2 - Default behavior unchanged (Priority: P1)

**Why this priority**: BC guarantee. Production must continue to benefit from connection
reuse without configuration changes.

**Independent Test**: Call `Driver::connect()` twice with identical params and no
`forceNewConnection` flag; assert both return the same native connection instance.

**Acceptance Scenarios**:
1. **Given** two consecutive `Driver::connect()` calls with identical params and
   `forceNewConnection` absent (or false),
   **When** both connections are obtained,
   **Then** their native `Firebird\Connection` objects are the same instance (reuse).

### User Story 3 - Role parameter honored (Priority: P2)

**Why this priority**: Latent bug fix bundled with this change. The `role` parameter is
already documented and parsed but silently dropped.

**Independent Test**: Call `Driver::connect()` with `role` set; connect with a live
Firebird database and verify the connection succeeds and the role is applied (behavioral
verification against a real DB).

**Acceptance Scenarios**:
1. **Given** `params['role'] = 'ADMIN'`,
   **When** `Driver::connect()` is called against a live Firebird database,
   **Then** the connection succeeds and the role is passed to `fbird_connect()`.
2. **Given** no `role` param,
   **When** `Driver::connect()` is called,
   **Then** `fbird_connect()` receives `null` as its 7th argument (default behavior).

### User Story 4 - DSN URL configuration (Priority: P2)

**Why this priority**: Users configure via `DATABASE_URL` in Symfony `.env`.

**Independent Test**: Parse `firebird://user:pass@host/db?forceNewConnection=1` via
`DsnParser`; assert `$params['forceNewConnection'] === '1'`.

**Acceptance Scenarios**:
1. **Given** a DSN with `?forceNewConnection=1`,
   **When** parsed by `DsnParser` and passed to `Driver::connect()`,
   **Then** the driver creates a force-new connection.
2. **Given** a DSN without the query parameter,
   **When** parsed and connected,
   **Then** default reuse behavior applies.

## Requirements

### Functional Requirements

- **FR-001**: `Driver::connect()` MUST accept a `forceNewConnection` boolean parameter
  (top-level `$params` key, camelCase to match Doctrine DBAL conventions:
  `driverClass`, `wrapperClass`, `persistent`, etc.).
- **FR-002**: When `forceNewConnection` is truthy and `persistent` is false,
  `Driver::connect()` MUST call `fbird_connect()` with `FBIRD_CONNECT_FORCE_NEW` as
  the 8th argument (`$flags`).
- **FR-003**: When `forceNewConnection` is absent/false, behavior MUST be identical to
  the current implementation (connection reuse; `flags` defaults to 0).
- **FR-004**: `forceNewConnection` MUST be ignored for persistent connections
  (`fbird_pconnect()` has no `$flags` parameter; persistent connections use a separate
  pool and are not affected by the reuse race).
- **FR-005**: `Driver::connect()` MUST pass `$params['role'] ?? ''` as the 7th argument
  (`$role`) to both `fbird_connect()` and `fbird_pconnect()`. An empty string is
  used instead of `null` because the C implementation (`zend_parse_parameters`
  with format `s`) requires a string, not null - despite the stubs declaring
  `?string`.
- **FR-006**: The DSN query parameter `forceNewConnection=1` MUST be supported without
  changes to `DsnParser` (Doctrine's `parse_str` + `array_merge` already delivers it
  into `$params`).

### Key Entities

- **`Driver::connect()`** (`src/Driver/Firebird/Driver.php`): Modified to read
  `forceNewConnection` and `role` from `$params` and pass them to `fbird_connect()` /
  `fbird_pconnect()`.
- **`FBIRD_CONNECT_FORCE_NEW`** (constant, value: 2): Already declared in
  `stubs/firebird-stubs.php:40` and `php_fbird_includes.h:262`; always available under
  `ext-firebird: ^12.0`.

### Non-Functional Requirements

- **NFR-001**: Zero BC break - default behavior (no `forceNewConnection` param) must be
  byte-identical to current behavior for both persistent and non-persistent paths.
- **NFR-002**: PHPStan Level 8 analysis MUST pass with no new baseline entries.
- **NFR-003**: PHPCS MUST pass with no violations.
- **NFR-004**: The change MUST be covered by at least one functional test verifying
  connection identity distinctness under `forceNewConnection=true`.
- **NFR-005**: `docs/DSN.md` MUST be updated to document the new parameter in both the
  URL query-parameter table and the array parameter reference, and to document `role`
  in the URL query table (currently only in the array reference).

## Success Criteria

### Measurable Outcomes

- **SC-001**: A functional test demonstrates that two `Driver::connect()` calls with
  `forceNewConnection=true` yield distinct native `Firebird\Connection` instances.
- **SC-002**: A functional test demonstrates that two `Driver::connect()` calls without
  the flag yield the same native connection instance (reuse preserved).
- **SC-003**: A functional test verifies that `role` is passed to `fbird_connect()`
  by connecting with a role against a live Firebird database.
- **SC-004**: `DsnParserTest` includes a case for `?forceNewConnection=1`.
- **SC-005**: `docs/DSN.md` lists `forceNewConnection` in both parameter tables.
- **SC-006**: PHPStan Level 8 and PHPCS report zero new issues.
- **SC-007**: `amicron-platform` CI can drop `gc_collect_cycles()` from test base classes
  after enabling `forceNewConnection=true` in its test Doctrine config (validated
  downstream, not in this repo).

## Out of Scope

- Changes to `fbird_pconnect()` (persistent connections are unaffected by the race).
- Auto-detection of test environments (the flag is opt-in, not auto-enabled).
- Changes to Doctrine's `DsnParser` (not needed; `parse_str` handles it).
- Refactoring the `Connection::__destruct()` close logic (working as designed).
- The `gc_collect_cycles()` workaround removal in `amicron-platform` (downstream task).
