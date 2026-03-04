---
description: >-
  TDD and issue-driven implementation plan for the DBAL 3.10.x gap analysis.
  Maps 20 GitHub issues across 5 sprints to test files, source files, and
  TDD cycles. Derived from docs/research/2026-03-03-dbal3-gap-analysis.md.
tags:
  - dbal
  - implementation-plan
  - tdd
  - sprints
  - firebird
last_updated: '2026-03-03'
---

# DBAL 3.10.x Gap Implementation Plan

**Source:** `docs/research/2026-03-03-dbal3-gap-analysis.md`
**Branch:** `3.0.x` (commit `c6bde89`)
**Date:** 2026-03-03
**Issues created:** #59–#79 on GitHub (21 issues)

---

## Issue Map by Sprint

### Sprint 1 — Quick Wins (due 2026-03-05)

| Issue | Title | Test File | Source File | Status |
|-------|-------|-----------|-------------|--------|
| [#59](https://github.com/satwareAG/doctrine-firebird-driver/issues/59) | ForeignKeyConstraintViolationsTest | `tests/Test/Functional/ForeignKeyConstraintViolationsTest.php` | — (verify ExceptionConverter) | `open` |
| [#60](https://github.com/satwareAG/doctrine-firebird-driver/issues/60) | UniqueConstraintViolationsTest | `tests/Test/Functional/UniqueConstraintViolationsTest.php` | — (verify ExceptionConverter) | `open` |
| [#61](https://github.com/satwareAG/doctrine-firebird-driver/issues/61) | BooleanBindingTest | `tests/Test/Functional/BooleanBindingTest.php` | `src/DBAL/FirebirdBooleanType.php` | `open` |
| [#62](https://github.com/satwareAG/doctrine-firebird-driver/issues/62) | NewPrimaryKeyWithNewAutoIncrementColumnTest | `tests/Test/Functional/Platform/NewPrimaryKeyWithNewAutoIncrementColumnTest.php` | `src/Platforms/FirebirdPlatform.php` | `open` |
| [#63](https://github.com/satwareAG/doctrine-firebird-driver/issues/63) | LockMode/NoneTest | `tests/Test/Functional/Platform/LockMode/NoneTest.php` | `src/Platforms/FirebirdPlatform.php` | `open` |
| [#64](https://github.com/satwareAG/doctrine-firebird-driver/issues/64) | DateImmutableTypeTest + DateTime + Time | `tests/Test/Functional/Types/Date*ImmutableTypeTest.php` | — | `open` |

### Sprint 2 — Core Schema Tests (due 2026-03-08)

| Issue | Title | Test File | Source File | Status |
|-------|-------|-----------|-------------|--------|
| [#65](https://github.com/satwareAG/doctrine-firebird-driver/issues/65) | TransactionTest | `tests/Test/Functional/TransactionTest.php` | `src/Driver/Firebird/Connection.php` | `open` |
| [#66](https://github.com/satwareAG/doctrine-firebird-driver/issues/66) | DefaultValueTest | `tests/Test/Functional/Schema/DefaultValueTest.php` | `src/Schema/FirebirdSchemaManager.php` | `open` |
| [#67](https://github.com/satwareAG/doctrine-firebird-driver/issues/67) | ComparatorTest | `tests/Test/Functional/Schema/ComparatorTest.php` | — (expose false positives for #69) | `open` |
| [#68](https://github.com/satwareAG/doctrine-firebird-driver/issues/68) | SchemaManagerTest + SchemaTest | `tests/Test/Functional/Schema/SchemaManagerTest.php` + `SchemaTest.php` | `src/Schema/FirebirdSchemaManager.php` | `open` |

### Sprint 3 — Architecture (due 2026-03-13)

| Issue | Title | Test File | Source File | Status |
|-------|-------|-----------|-------------|--------|
| [#69](https://github.com/satwareAG/doctrine-firebird-driver/issues/69) | FirebirdComparator | `tests/Test/Unit/Platforms/Schema/FirebirdComparatorTest.php` | `src/Platforms/SQL/FirebirdComparator.php` | `open` |
| [#70](https://github.com/satwareAG/doctrine-firebird-driver/issues/70) | FirebirdSchemaManagerFactory | `tests/Test/Unit/Schema/FirebirdSchemaManagerFactoryTest.php` | `src/Schema/FirebirdSchemaManagerFactory.php` | `open` |
| [#71](https://github.com/satwareAG/doctrine-firebird-driver/issues/71) | Firebird4Keywords + Firebird5Keywords | (unit tests in platform test files) | `src/Platforms/Keywords/Firebird4Keywords.php` + `Firebird5Keywords.php` | `open` |

### Sprint 4 — Robustness (due 2026-03-16)

| Issue | Title | Test File | Source File | Status |
|-------|-------|-----------|-------------|--------|
| [#72](https://github.com/satwareAG/doctrine-firebird-driver/issues/72) | ConnectionLost detection | `tests/Test/Unit/Driver/ExceptionConverterTest.php` (extend) | `src/Driver/Firebird/ExceptionConverter.php` | `open` |
| [#73](https://github.com/satwareAG/doctrine-firebird-driver/issues/73) | Driver Middleware support | (unit test) | `src/Driver/FirebirdDriver.php`, `src/Driver/Firebird/Driver.php` | `open` |
| [#74](https://github.com/satwareAG/doctrine-firebird-driver/issues/74) | ConnectionLostTest (functional) | `tests/Test/Functional/Connection/ConnectionLostTest.php` | — (depends on #72) | `open` |

### Sprint 5 — Nice-to-Have (due 2026-03-18)

| Issue | Title | Test File | Source File | Status |
|-------|-------|-----------|-------------|--------|
| [#75](https://github.com/satwareAG/doctrine-firebird-driver/issues/75) | SQL/ParserTest | `tests/Test/Functional/SQL/ParserTest.php` | — | `open` |
| [#76](https://github.com/satwareAG/doctrine-firebird-driver/issues/76) | PrimaryReadReplicaConnectionTest | `tests/Test/Functional/PrimaryReadReplicaConnectionTest.php` | — | `open` |
| [#77](https://github.com/satwareAG/doctrine-firebird-driver/issues/77) | Ticket/ regression directory | `tests/Test/Functional/Ticket/*.php` | — | `open` |
| [#78](https://github.com/satwareAG/doctrine-firebird-driver/issues/78) | DBAL 4.x forward-compat tracking | — | Multiple (see issue) | `open` |
| [#79](https://github.com/satwareAG/doctrine-firebird-driver/issues/79) | Ease-of-use docs (introspectTable, DSN, RetryOnLock) | `tests/Test/Tools/DsnParserTest.php` (extend) | `README.md`, `docs/DSN.md`, `docs/RETRY_ON_LOCK.md` | `open` |

---

## Files to Create Summary

### Test Files

```text
tests/Test/Functional/
  ForeignKeyConstraintViolationsTest.php      # #59
  UniqueConstraintViolationsTest.php          # #60
  BooleanBindingTest.php                      # #61
  TransactionTest.php                         # #65
  PrimaryReadReplicaConnectionTest.php        # #76
  Connection/
    ConnectionLostTest.php                    # #74
  Platform/
    NewPrimaryKeyWithNewAutoIncrementColumnTest.php  # #62
    LockMode/
      NoneTest.php                            # #63
  Schema/
    DefaultValueTest.php                      # #66
    ComparatorTest.php                        # #67
    SchemaManagerTest.php                     # #68
    SchemaTest.php                            # #68
  SQL/
    ParserTest.php                            # #75
  Types/
    DateImmutableTypeTest.php                 # #64
    DateTimeImmutableTypeTest.php             # #64
    TimeImmutableTypeTest.php                 # #64
  Ticket/
    .gitkeep                                  # #77
    GH22Test.php                              # #77
    GH23Test.php                              # #77
    GH50Test.php                              # #77

tests/Test/Unit/
  Platforms/
    Schema/
      FirebirdComparatorTest.php              # #69
  Schema/
    FirebirdSchemaManagerFactoryTest.php      # #70
```

### Source Files

```text
src/
  Platforms/
    SQL/
      FirebirdComparator.php                  # #69
    Keywords/
      Firebird4Keywords.php                   # #71
      Firebird5Keywords.php                   # #71
  Schema/
    FirebirdSchemaManagerFactory.php          # #70
```

### Source Files to Modify

```text
src/Driver/Firebird/ExceptionConverter.php   # #72 — ConnectionLost detection
src/Driver/FirebirdDriver.php                # #73 — Middleware support
src/Driver/Firebird/Driver.php               # #73 — Middleware support
src/Platforms/Firebird4Platform.php          # #71 — getReservedKeywordsClass()
src/Platforms/Firebird5Platform.php          # #71 — getReservedKeywordsClass()
src/Schema/FirebirdSchemaManager.php         # #69 — createComparator() integration
src/Platforms/FirebirdPlatform.php           # #70 — SchemaManagerFactory delegation
```

---

## TDD Workflow per Issue

```text
1. Create feature branch: git checkout -b feature/issue-<N>-<short-name>
2. RED:    Write test file first — run phpunit → FAIL
3. GREEN:  Write minimal source code → run phpunit → PASS
4. REFACTOR: Clean up — run phpstan, phpunit, coverage check
5. Commit: ≤200 LOC, conventional commit message
6. PR: Link to issue, auto-closes on merge
```

### Quality Gates (per commit)

```bash
vendor/bin/phpunit --filter=<TestClass>       # must PASS
vendor/bin/phpstan analyse src/ tests/ --level=8  # zero errors
```

### Coverage Gate

```bash
# Target: ≥80% (currently ~82.84%)
vendor/bin/phpunit --coverage-text | grep -E "Lines|Classes|Methods"
```

---

## Dependency Graph

```text
Sprint 1 (no deps)
  #59 → standalone
  #60 → standalone
  #61 → standalone
  #62 → standalone
  #63 → standalone
  #64 → standalone

Sprint 2
  #65 → standalone
  #66 → standalone
  #67 → exposes issues fixed by #69
  #68 → standalone

Sprint 3
  #69 → unblocks #67 (ComparatorTest false-positive fixes)
  #70 → unblocks #78 DBAL 4.x item
  #71 → standalone

Sprint 4
  #72 → unblocks #74
  #73 → standalone
  #74 → depends on #72

Sprint 5
  #75 → standalone
  #76 → standalone
  #77 → standalone
  #78 → depends on #70
```

---

## Label Reference

| Label | Issues |
|-------|--------|
| `dbal-gap` | All 21 (#59–#79) |
| `sprint-1` | #59, #60, #61, #62, #63, #64 |
| `sprint-2` | #65, #66, #67, #68 |
| `sprint-3` | #69, #70, #71 |
| `sprint-4` | #72, #73, #74 |
| `sprint-5` | #75, #76, #77, #78, #79 |
| `tdd` | #59–#68, #70–#72, #74–#75 |
| `high-priority` | #61, #65, #69, #72 |
| `dbal4-forward-compat` | #70, #78 |

---

## Progress Tracking

View all gap issues: https://github.com/satwareAG/doctrine-firebird-driver/issues?q=label%3Adbal-gap

View by sprint:
- Sprint 1: https://github.com/satwareAG/doctrine-firebird-driver/issues?q=label%3Asprint-1
- Sprint 2: https://github.com/satwareAG/doctrine-firebird-driver/issues?q=label%3Asprint-2
- Sprint 3: https://github.com/satwareAG/doctrine-firebird-driver/issues?q=label%3Asprint-3
- Sprint 4: https://github.com/satwareAG/doctrine-firebird-driver/issues?q=label%3Asprint-4
- Sprint 5: https://github.com/satwareAG/doctrine-firebird-driver/issues?q=label%3Asprint-5

---

## Related Documents

- **Gap Analysis:** `docs/research/2026-03-03-dbal3-gap-analysis.md`
- **Deprecation Strategy:** `docs/archive/2026-01-02-deprecation-free-release-plan.md`
- **Test Infrastructure:** `docs/TESTING.md`
