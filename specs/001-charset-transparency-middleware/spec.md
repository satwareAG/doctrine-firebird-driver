# Feature Specification: Transparent Charset Translation Middleware

**Feature Branch**: `001-charset-transparency-middleware`
**Created**: 2026-03-10
**Status**: Implemented (extended GH-116: `query()`/`exec()`/`prepare()` SQL body re-encoding)

## Context

Amicron ERP Firebird databases use the `ISO8859_1` connection charset while the actual
wire data is stored and transmitted in Windows-1252 encoding. PHP applications (and
Doctrine DBAL) operate in UTF-8. Without a translation layer, all VARCHAR, TEXT,
and BLOB TEXT columns containing non-ASCII characters (German umlauts, euro sign, etc.)
are silently corrupted on read and write.

The requirement comes from `satag-amicron-entity-bundle`, which must connect with
`charset=ISO8859_1` and receive/send UTF-8 strings transparently.

## User Scenarios & Testing

### User Story 1 - VARCHAR read transparency (Priority: P1)

**Why this priority**: VARCHAR is the most common column type in Amicron (names, addresses,
article descriptions). Corruption here breaks core business data.
**Independent Test**: Provide a mock result row with WIN1252 bytes; assert `fetchOne`,
`fetchAssociative`, `fetchNumeric`, `fetchAllAssociative`, `fetchAllNumeric`, and
`fetchFirstColumn` all return the correct UTF-8 string.

**Acceptance Scenarios**:
1. **Given** a Firebird result row with a VARCHAR column whose bytes are Windows-1252 encoded,
   **When** the result is fetched via any DBAL fetch method,
   **Then** the returned PHP string is valid UTF-8 identical to the original Unicode value.
2. **Given** a non-string column value (integer, float, null, boolean),
   **When** the result is fetched,
   **Then** the value is returned unchanged without any encoding attempt.

### User Story 2 - VARCHAR write / search transparency (Priority: P1)

**Why this priority**: INSERT, UPDATE, and WHERE clause parameters must be encoded
or search results are empty or garbled.
**Independent Test**: Create a mock statement; call `bindValue` or `execute` with a
UTF-8 string; assert the inner statement receives the Windows-1252 byte sequence.

**Acceptance Scenarios**:
1. **Given** a UTF-8 search string passed as a `bindValue` parameter (ParameterType::STRING),
   **When** the statement middleware processes the call,
   **Then** the inner driver statement receives the Windows-1252 encoded byte string.
2. **Given** inline parameters passed to `execute([$searchTerm])`,
   **When** the statement middleware processes the call,
   **Then** string values are encoded to Windows-1252 and non-string values pass through unchanged.

### User Story 3 - BLOB TEXT (stream) transparency (Priority: P1)

**Why this priority**: Amicron stores multi-line text fields (memos, notes) as BLOB TEXT
columns. The Firebird driver returns these as PHP stream resources, not plain strings.
**Independent Test**: Write WIN1252 bytes into a `php://temp` stream; pass it as a mock
result column; assert the fetched value is the correct UTF-8 string.

**Acceptance Scenarios**:
1. **Given** a BLOB TEXT column returned as a PHP resource stream containing Windows-1252 bytes,
   **When** the result middleware decodes the row,
   **Then** the stream is fully read, decoded to UTF-8, and returned as a PHP string.
2. **Given** a stream containing only ASCII bytes,
   **When** the result middleware decodes the row,
   **Then** the returned string is identical (ASCII is a subset of both encodings).

### User Story 4 - Round-trip correctness for Amicron special characters (Priority: P1)

**Why this priority**: The exact character set used by Amicron must survive a full
encode/decode cycle. Any character lost results in persistent data corruption.
**Independent Test**: For each string in the provider, encode UTF-8 to WIN1252 via
the statement middleware then decode via the result middleware; assert equality.

**Acceptance Scenarios**:
1. **Given** the strings `['Faßbrause für 30€?', 'Ärger mit Öl', 'Straße 123',
   'Müller & Söhne', '€uro', 'äöüÄÖÜß', 'Produkt: Grüner Tee 500g']`,
   **When** each is encoded UTF-8 to Windows-1252 and decoded Windows-1252 to UTF-8,
   **Then** each decoded string is byte-identical to the original UTF-8 input.

### User Story 5 - Middleware stack wiring (Priority: P2)

**Why this priority**: The middleware must be correctly wired into the DBAL driver
chain so all connections automatically benefit without per-query configuration.
**Independent Test**: Instantiate `CharsetMiddleware`, call `wrap()`; assert the
returned driver is a `CharsetDriverMiddleware` with the configured encodings.

**Acceptance Scenarios**:
1. **Given** a `CharsetMiddleware` configured with `dbCharset=Windows-1252` and
   `appCharset=UTF-8`,
   **When** `wrap($driver)` is called,
   **Then** the returned driver wraps the original and applies encoding on all connections.
2. **Given** the amicron entity bundle service configuration,
   **When** the Symfony container is compiled,
   **Then** `CharsetMiddleware` is registered as a `doctrine.middleware` tagged service
   with `priority=100`.

### User Story 6 - Parameterless query / DML transparency (Priority: P1, GH-116)

**Why this priority**: DBAL `Connection::executeQuery()` and `executeStatement()` take
a fast path through `Driver\Connection::query()` / `::exec()` when no bound parameters
are supplied (see `vendor/doctrine/dbal/src/Connection.php` L1105-1106, L1215-1216).
Without overrides on these methods, every parameterless `SELECT`/`INSERT`/`UPDATE`/`DELETE`
silently bypasses the entire charset middleware chain: SQL literals are sent raw, and
result rows are not decoded back to PHP encoding. This also covers QueryBuilder literal
fragments like `->andWhere("name LIKE '%Münster%'")`, which `getSQL()` concatenates
byte-for-byte into the final SQL string - never a bound parameter, so
`CharsetStatementMiddleware::bindValue()` never sees them.
**Independent Test**: Construct a `CharsetConnectionMiddleware` wrapping a mock
`Driver\Connection`; call `query()` / `exec()` with a UTF-8 SQL literal; assert the
inner connection receives the Windows-1252 byte sequence and that `query()` returns
a `CharsetResultMiddleware`.

**Acceptance Scenarios**:
1. **Given** a SQL string containing UTF-8 literals (e.g. `SELECT 'Müller' AS ort`),
   **When** `CharsetConnectionMiddleware::query()` is invoked,
   **Then** the inner driver connection receives the SQL with the literal re-encoded
   to the database encoding (Windows-1252 bytes), and the returned `Result` is wrapped
   in `CharsetResultMiddleware` so fetched rows decode back to UTF-8.
2. **Given** a DML string containing UTF-8 literals (e.g. `UPDATE t SET name='Faß'`),
   **When** `CharsetConnectionMiddleware::exec()` is invoked,
   **Then** the inner driver connection receives the SQL with the literal re-encoded
   to the database encoding.
3. **Given** a SQL string containing UTF-8 literals prepared via `prepare()`,
   **When** the statement is later executed,
   **Then** the inner driver connection received the SQL body with literals re-encoded
   to the database encoding, in addition to any bound-parameter encoding performed by
   `CharsetStatementMiddleware`.
4. **Given** a QueryBuilder fragment `->andWhere("name LIKE '%suchemitÄß%'")` with no
   other bound parameters,
   **When** `executeQuery($qb->getSQL())` runs (parameterless shortcut),
   **Then** the literal survives the round trip: SQL is re-encoded inbound and result
   rows are decoded outbound by `CharsetConnectionMiddleware::query()`.

## Requirements

### Functional Requirements

- **FR-001**: System MUST encode all `ParameterType::STRING` values from UTF-8 to the
  configured database charset before passing them to the inner driver statement.
- **FR-002**: System MUST decode all string-typed result column values from the database
  charset to UTF-8 before returning them to the application.
- **FR-003**: System MUST read stream resources (BLOB TEXT) in full, decode the bytes from
  the database charset to UTF-8, and return a PHP string.
- **FR-004**: System MUST pass through non-string column values (int, float, null, bool)
  without any encoding attempt.
- **FR-005**: System MUST pass through `false` result values (empty result sets) from all
  fetch methods without modification.
- **FR-006**: System MUST apply the encoding transparently at the driver middleware layer
  so neither the `Connection` nor `Type` classes require encoding logic.
- **FR-007**: System SHOULD support `Connection::quote()` by encoding the input value
  to the database charset before calling the inner connection's `quote()`.
- **FR-008**: System MUST re-encode the SQL body of every parameterless query passed
  to `CharsetConnectionMiddleware::query()` from the PHP encoding to the database
  encoding, and MUST wrap the returned `Result` in `CharsetResultMiddleware` so fetched
  values are decoded back to the PHP encoding. (GH-116)
- **FR-009**: System MUST re-encode the SQL body of every parameterless statement passed
  to `CharsetConnectionMiddleware::exec()` from the PHP encoding to the database encoding.
  (GH-116)
- **FR-010**: System MUST re-encode the SQL body of every statement passed to
  `CharsetConnectionMiddleware::prepare()` from the PHP encoding to the database
  encoding before delegating to the inner connection, so that QueryBuilder literal
  fragments and other inline string literals are transparently transcoded in the
  prepared-statement path as well. (GH-116)

### Key Entities

- **CharsetMiddleware**: DBAL `Middleware` implementation; entry point for wiring.
  Wraps the driver with a `CharsetDriverMiddleware`.
- **CharsetConnectionMiddleware**: Wraps `Driver\Connection`; encodes `quote()` inputs;
  re-encodes the SQL body of `query()`, `exec()`, and `prepare()` from PHP encoding to
  database encoding; wraps `query()` results in `CharsetResultMiddleware`; produces
  `CharsetStatementMiddleware` from `prepare()`.
- **CharsetStatementMiddleware**: Wraps `Driver\Statement`; encodes `bindValue()` and
  inline `execute()` parameters; returns `CharsetResultMiddleware` from `execute()`.
- **CharsetResultMiddleware**: Wraps `Driver\Result`; decodes string and stream column
  values in all six fetch methods.

### Non-Functional Requirements

- **NFR-001**: All four middleware classes MUST achieve 100% line coverage in the unit
  test suite.
- **NFR-002**: The middleware MUST add zero overhead for non-string values (no
  `mb_convert_encoding` call for int/float/null/bool/false).
- **NFR-003**: PHPStan Level 8 analysis MUST pass with no new baseline entries.
- **NFR-004**: PHP Coding Standards (ECS/PHPCS) MUST pass with no violations.

## Success Criteria

### Measurable Outcomes

- **SC-001**: All unit tests for the four middleware classes pass with exit code 0.
- **SC-002**: The test suite contains at least 103 test cases (33 original + 70 round-trip)
  with 339 assertions, achieving 100% line and method coverage for all four middleware
  classes.
- **SC-003**: PHPStan Level 8 analysis reports zero errors and zero new baseline entries.
- **SC-004**: ECS/PHPCS reports zero violations.
- **SC-005**: A string containing `ä`, `ö`, `ü`, `ß`, `€` bound as a query parameter
  and fetched from a mock result returns the original UTF-8 string unchanged (verified
  by `CharsetEncodingRoundTripTest::testBindValueAndFetchOneRoundTrip`).

## Out of Scope

- Encoding detection or auto-detection (encoding pair is configured explicitly).
- Support for encodings other than UTF-8 app / Windows-1252 (or ISO-8859-1) database.
- Modifying the Firebird platform LIKE escaping logic.
- Integration tests requiring a live Firebird database instance (those are in the
  functional test suite, gated by `DB_*` environment variables).
- The `Utf8String` and `Utf8Text` Doctrine type classes in the amicron entity bundle
  (they delegate entirely to this middleware and are identity pass-throughs).
- DBAL 4.x compatibility (`composer.json` currently restricts to `^3.10`; the existing
  `quote()` signature would need reconciliation with DBAL 4's tightened contract).
  Tracked separately from GH-116.
- Charset-aware handling of native escape-hatch methods on `Driver\Firebird\Connection`
  (`executeAuto()`, `queryInTransaction()`, `createBatch()`, `executeBatch()`,
  `getNativeConnection()`). These bypass the DBAL middleware stack entirely and remain
  non-charset-aware; callers must encode values manually. Tracked in
  [GH-119](https://github.com/satwareAG/doctrine-firebird-driver/issues/119);
  decision: **document only** (option 1). Escape hatches are explicit opt-outs;
  callers using them have chosen to bypass DBAL abstractions and are responsible
  for their own encoding.
