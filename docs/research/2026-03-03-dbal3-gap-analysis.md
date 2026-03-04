---
description: >-
  Gap analysis of Doctrine DBAL 3.10.x test suite and features vs the
  satwareAG/doctrine-firebird-driver. Identifies missing tests, architecture
  gaps, and ease-of-use improvements with prioritized implementation order.
tags:
  - dbal
  - gap-analysis
  - testing
  - architecture
  - firebird
last_updated: '2026-03-03'
---

# DBAL 3.10.x Gap Analysis — doctrine-firebird-driver

**Branch analysed:** DBAL `3.10.x` (latest 3.x)
**Driver branch:** `3.0.x` (commit `c6bde89`)
**Date:** 2026-03-03
**Current test count:** 595 unit tests, 1116 assertions, coverage ~82.84%

---

## Executive Summary

Comparing the Doctrine DBAL 3.10.x test suite against the `satwareAG/doctrine-firebird-driver`
reveals **13 missing functional test classes**, **4 missing architecture features**, and several
type-level test gaps. The most impactful priorities are:

1. Exception-mapping tests (FK / unique constraint violations) — prove the ExceptionConverter works end-to-end
2. A Firebird-specific **Comparator** class — fixes false-positive schema diffs
3. **TransactionTest** — validates the custom transaction/savepoint logic
4. **DefaultValueTest** — ensures schema default values survive roundtrip introspection
5. **SchemaManagerFactory** support — required by DBAL 3.6+ and mandatory for DBAL 4.x

---

## 1. Missing Functional Tests

### 1.1 Priority 1 — High impact, low-to-medium effort

| Test Class | DBAL Path | What It Validates | Effort |
|-----------|-----------|-------------------|--------|
| `ForeignKeyConstraintViolationsTest` | `Functional/ForeignKeyConstraintViolationsTest.php` | INSERT/UPDATE that violates FK → `ForeignKeyConstraintViolationException` thrown | Low |
| `UniqueConstraintViolationsTest` | `Functional/UniqueConstraintViolationsTest.php` | INSERT of duplicate key → `UniqueConstraintViolationException` thrown | Low |
| `BooleanBindingTest` | `Functional/BooleanBindingTest.php` | Binding PHP `true`/`false` as statement params; critical because Firebird uses CHAR(1) or native boolean | Low |
| `TransactionTest` | `Functional/TransactionTest.php` | `beginTransaction`, `commit`, `rollBack`, nested savepoints, isolation levels | Medium |
| `DefaultValueTest` | `Functional/Schema/DefaultValueTest.php` | Schema default values (string, int, bool, null) survive create→introspect→compare roundtrip | Medium |
| `NewPrimaryKeyWithNewAutoIncrementColumnTest` | `Functional/Platform/NewPrimaryKeyWithNewAutoIncrementColumnTest.php` | Adding a PK + autoincrement column to an existing table via `ALTER TABLE` | Low |

### 1.2 Priority 2 — Medium impact, medium effort

| Test Class | DBAL Path | What It Validates | Effort |
|-----------|-----------|-------------------|--------|
| `ComparatorTest` (Schema) | `Functional/Schema/ComparatorTest.php` | Schema diff functional correctness — comparator produces sane `TableDiff` | Medium |
| `SchemaManagerTest` | `Functional/Schema/SchemaManagerTest.php` | Comprehensive SM coverage (supplements `SchemaManagerFunctionalTestCase`) | Medium |
| `SchemaTest` | `Functional/Schema/SchemaTest.php` | Full schema create/migrate/drop lifecycle via `AbstractSchemaManager::createSchema()` | Medium |
| `LockMode/NoneTest` | `Functional/LockMode/NoneTest.php` | `LockMode::NONE` does not throw on a standard SELECT | Low |
| `BooleanBindingTest` (already P1) | - | — | — |

### 1.3 Priority 3 — Nice-to-have, lower urgency

| Test Class | DBAL Path | What It Validates | Effort |
|-----------|-----------|-------------------|--------|
| `ConnectionLostTest` | `Functional/Connection/ConnectionLostTest.php` | Connection drop detected: query after killed connection raises `ConnectionLost` | Medium |
| `SQL/ParserTest` | `Functional/SQL/ParserTest.php` | SQL parser tokenisation correctness on Firebird DDL/DML | Low |
| `PrimaryReadReplicaConnectionTest` | `Functional/PrimaryReadReplicaConnectionTest.php` | `PrimaryReadReplicaConnection` pattern with Firebird URLs | Medium |

### 1.4 Regression/Ticket Tests — DBAL 3.10.x has, Firebird does not

DBAL ships `Functional/Ticket/DBAL168Test`, `DBAL202Test`, `DBAL461Test`, `DBAL510Test`,
`DBAL752Test`. These are ORM-era regression tests. The Firebird driver should add a
`Functional/Ticket/` directory to track its own regressions (e.g. named-parameter conversion,
BLOB handling, SIGSEGV-triggering queries).

---

## 2. Missing Architecture / Feature Gaps

### 2.1 🔴 Firebird-specific Comparator (high priority)

**DBAL pattern:** `src/Platforms/MySQL/Comparator.php`, `SQLite/Comparator.php`,
`SQLServer/Comparator.php` — each platform ships a `Comparator` that extends
`AbstractComparatorTestCase` and overrides column-diff logic to exclude false positives
(e.g. MySQL ignores detected length changes for `TEXT` → `MEDIUMTEXT`).

**Firebird impact:**
- Firebird has quirks: CHAR/VARCHAR length units (bytes vs chars), domain types, computed
  columns, boolean CHAR columns. Without a custom Comparator, `listTableColumns` produces
  column metadata that round-trips slightly differently, causing spurious `ALTER TABLE`
  statements in migrations.
- **Unit test to add:** `tests/Test/Platforms/Schema/FirebirdComparatorTest.php` extending
  `AbstractComparatorTestCase`.
- **Implementation:** `src/Platforms/SQL/FirebirdComparator.php` (or integrated into
  `Schema/FirebirdSchemaManager`).

```php
// Example
namespace Satag\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Schema\Comparator;

final class FirebirdComparator extends Comparator
{
    // Override to handle CHAR(1) ↔ boolean, domain types, computed columns
}
```

### 2.2 🔴 SchemaManagerFactory support (high priority, DBAL 4.x mandatory)

**DBAL pattern:** `Schema/DefaultSchemaManagerFactory` was added in DBAL 3.6. The old
`AbstractPlatform::createSchemaManager()` path is deprecated.

**Current state:** `FirebirdPlatform::createSchemaManager()` directly instantiates
`FirebirdSchemaManager`. There is no `FirebirdSchemaManagerFactory` class and the driver
does not reference `SchemaManagerFactory`.

**Impact:** DBAL 4.x will remove `createSchemaManager()` from `AbstractPlatform`. Without a
factory, the driver will break on DBAL 4.x upgrade.

**Implementation:**

```php
// src/Schema/FirebirdSchemaManagerFactory.php
namespace Satag\DoctrineFirebirdDriver\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;

final class FirebirdSchemaManagerFactory implements SchemaManagerFactory
{
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new FirebirdSchemaManager($connection, $connection->getDatabasePlatform());
    }
}
```

**Test to add:** Unit test verifying factory produces `FirebirdSchemaManager`.

### 2.3 🟡 Driver Middleware support (medium priority)

**DBAL pattern:** All bundled drivers (MySQL, PgSQL, SQLite, SQLSrv, IBMDB2) implement the
`Driver/Middleware/Abstract{Connection,Driver,Result,Statement}Middleware` APIs.

**Current state:** The Firebird driver has no middleware infrastructure in `src/`. Middleware
allows users to wrap the driver with logging, profiling, or retry logic without subclassing.

**Impact:** Users cannot plug standard DBAL middleware (e.g. `Logging\Middleware`) into the
Firebird driver chain. This is increasingly expected by DBAL users.

**Implementation:**
- `FirebirdDriver` / `Driver` need to implement the `Driver\Middleware` contract.
- Low risk — mostly delegate/proxy pattern. The abstract middleware classes handle most of it.

### 2.4 🟡 ConnectionLost exception detection (medium priority)

**DBAL pattern:** `Exception/ConnectionLost.php` — `ExceptionConverter` should detect
Firebird-specific SQLSTATE/error codes that indicate a lost connection (e.g. isc_network_error,
isc_lost_db_connection) and map them to `ConnectionLost`.

**Current state:** `FirebirdExceptionConverter` maps FK violations, unique constraints,
not-null, deadlocks, read-only — but does **not** map connection-lost scenarios.

**Firebird error codes to map:**

| GDSCODE | Description | DBAL Exception |
|---------|-------------|----------------|
| `335544721` | Network write failed — broken pipe | `ConnectionLost` |
| `335544723` | Database connection lost | `ConnectionLost` |
| `335544726` | Lost connection to server | `ConnectionLost` |

**Test to add:** `Functional/Connection/ConnectionLostTest.php` (skip on environments where
killing a Firebird service is not possible; use mock for unit test).

### 2.5 🟡 Firebird 4/5 Keyword Lists (medium priority)

**DBAL pattern:** DBAL ships per-version keyword lists — `MySQL80Keywords`, `MySQL84Keywords`,
`MariaDb117Keywords`, `PostgreSQL100Keywords` etc.

**Current state:** Only `FirebirdKeywords` (base) and `Firebird3Keywords` exist. Firebird 4
introduced `TIMEZONE`, `TIME ZONE`, `DECFLOAT`, `INT128` as keywords/types. Firebird 5 adds
more.

**Impact:** `ReservedKeywordsValidator` (used by ORM) will miss Firebird 4/5 reserved words,
causing silent quoting failures in production.

**Implementation:**
- `src/Platforms/Keywords/Firebird4Keywords.php` extending `Firebird3Keywords`
- `src/Platforms/Keywords/Firebird5Keywords.php` extending `Firebird4Keywords`
- Wire into `Firebird4Platform::getReservedKeywordsClass()` / `Firebird5Platform`

---

## 3. Missing Type Unit Tests

DBAL 3.10.x has 28 type unit tests in `tests/Types/`. The Firebird driver has functional
type tests (`AsciiStringTest`, `BinaryTest`, `BooleanTest`, `DecimalTest`, `GuidTest`,
`JsonTest`) but lacks:

| Missing Test | Relevance to Firebird | Priority |
|-------------|----------------------|----------|
| `DateImmutableTypeTest` | High — date handling has Firebird-specific format quirks | P1 |
| `DateTimeImmutableTypeTest` | High — datetime round-trip via Firebird's ISO format | P1 |
| `TimeImmutableTypeTest` | High — time-only field handling | P1 |
| `DateTimeTzImmutableTypeTest` | Medium — Firebird 4+ adds `TIME WITH TIME ZONE` | P2 |
| `DateTimeTzTest` (mutable) | Medium — pair with immutable test | P2 |
| `FloatTest` | Medium — Firebird DOUBLE PRECISION precision | P2 |
| `IntegerTest` | Medium — DBAL integer type mapping | P2 |
| `SmallIntTest` | Medium — SMALLINT type mapping | P2 |
| `BlobTest` (unit) | Low — blob encoding edge cases | P3 |
| `StringTest` | Low — mostly generic | P3 |

**Note:** For Date/Time immutable tests the best approach is to add them under
`tests/Test/Unit/Types/` mirroring DBAL's structure, since these are pure unit tests that
require no DB connection.

---

## 4. Ease-of-Use Improvements Inspired by Other Drivers

### 4.1 Firebird Comparator for accurate migrations

The #1 complaint-driver for any third-party Doctrine driver is **false-positive migrations**.
A custom Comparator normalises type metadata before comparison, preventing ghost `ALTER TABLE`
statements. See §2.1 above.

### 4.2 `introspectTable()` modern path

DBAL 3.6+ deprecated `AbstractSchemaManager::listTableDetails()` in favour of
`introspectTable()`. The Firebird driver currently has `listTableDetails()` in its
`FirebirdSchemaManager`. Adding `introspectTable()` (which `listTableDetails` already delegates
to internally in the base class) ensures the driver works cleanly with DBAL 4.x.

### 4.3 Structured DSN configuration

DBAL's `DriverManager` supports DSN strings like
`firebird://user:pass@host/path/to/database.fdb`. The Firebird driver parses these via
`FirebirdConnectString`. However, DBAL 3.10.x introduced a formal `DsnParser` for all drivers.

**Improvement:** Add a `FirebirdDsnParser` or ensure the existing `FirebirdConnectString` passes
`DsnParserTest` for all DSN variants (TCP, service, embedded, events port).

### 4.4 Portability mode alignment

The DBAL `Portability\Connection` wraps results for cross-database compatibility. The Firebird
driver should be validated against the `Portability/ConverterTest` and `Portability/ResultTest`
patterns to ensure column casing and trim modes behave consistently.

### 4.5 Retry-on-lock middleware (already custom)

The driver has `RetryOnLockTest` which is ahead of DBAL. Document this as a feature advantage
over bundled DBAL drivers (`docs/RETRY_ON_LOCK.md`).

---

## 5. Recommended Implementation Order

```text
Sprint 1 — Quick Wins (1-2 days, no new dependencies)
  1. ForeignKeyConstraintViolationsTest  — verify ExceptionConverter end-to-end
  2. UniqueConstraintViolationsTest       — same
  3. BooleanBindingTest                   — critical for Firebird's bool handling
  4. NewPrimaryKeyWithNewAutoIncrementColumnTest
  5. LockMode/NoneTest
  6. Date/Time immutable type unit tests (Functional/Types/)

Sprint 2 — Core Schema Tests (2-3 days)
  7. TransactionTest                      — validates savepoint/nesting logic
  8. DefaultValueTest                     — schema introspection correctness
  9. ComparatorTest (Schema)
  10. SchemaManagerTest / SchemaTest

Sprint 3 — Architecture (3-5 days)
  11. FirebirdComparator class + unit test
  12. FirebirdSchemaManagerFactory
  13. Firebird4Keywords / Firebird5Keywords

Sprint 4 — Robustness (2-3 days)
  14. ConnectionLost exception detection + unit test
  15. Middleware awareness / FirebirdDriver middleware chain
  16. ConnectionLostTest (functional, may need CI env change)

Sprint 5 — Nice-to-Have (1-2 days)
  17. SQL/ParserTest
  18. PrimaryReadReplicaConnectionTest
  19. Ticket/ regression directory setup
```

---

## 6. DBAL 4.x Forward-Compatibility Notes

DBAL 4.x (currently in alpha on branch `4.4.x`) makes several breaking changes relevant to
this driver:

| DBAL 4.x Change | Current Status | Action Needed |
|----------------|----------------|---------------|
| `AbstractPlatform::createSchemaManager()` removed | `FirebirdPlatform` uses it | `FirebirdSchemaManagerFactory` (§2.2) |
| `AbstractSchemaManager::listTableDetails()` removed | Used in tests | Migrate to `introspectTable()` |
| `VersionAwarePlatformDriver::createDatabasePlatformForVersion()` signature change | `VersionAwarePlatformDriverTest` in driver | Review on DBAL 4 |
| `Deprecation::trigger()` calls removed | 10 shims in driver already intentional | Will clean up naturally |
| `ServerInfoAwareConnection` removed | `Connection` implements deprecated interface | Follow DBAL 4 replacement |

The deprecation strategy in `docs/archive/2026-01-02-deprecation-free-release-plan.md` already
documents the DBAL backward-compat shims. These 6 items above should be tracked as a future
`4.0.x` branch milestone.

---

## 7. Appendix — Test File Reference

### Files to create (relative to `tests/Test/`)

```text
Functional/
  ForeignKeyConstraintViolationsTest.php      # P1
  UniqueConstraintViolationsTest.php          # P1
  BooleanBindingTest.php                      # P1
  TransactionTest.php                         # P1
  Connection/
    ConnectionLostTest.php                    # P3
  Platform/
    NewPrimaryKeyWithNewAutoIncrementColumnTest.php  # P1
    LockMode/
      NoneTest.php                            # P2
  Schema/
    DefaultValueTest.php                      # P1
    ComparatorTest.php                        # P2
    SchemaManagerTest.php                     # P2
    SchemaTest.php                            # P2
  SQL/
    ParserTest.php                            # P3
  Types/
    DateImmutableTypeTest.php                 # P1
    DateTimeImmutableTypeTest.php             # P1
    TimeImmutableTypeTest.php                 # P1
    DateTimeTzImmutableTypeTest.php           # P2
    FloatTest.php                             # P2
    IntegerTest.php                           # P2
  Ticket/
    .gitkeep                                  # P3 (add as bugs found)

Unit/
  Platforms/
    Schema/
      FirebirdComparatorTest.php              # P1 (after Comparator implemented)
  Schema/
    FirebirdSchemaManagerFactoryTest.php      # P2
```

### Files to create (relative to `src/`)

```text
Platforms/
  SQL/
    FirebirdComparator.php                    # P1
  Keywords/
    Firebird4Keywords.php                     # P2
    Firebird5Keywords.php                     # P2
Schema/
  FirebirdSchemaManagerFactory.php            # P1
```
