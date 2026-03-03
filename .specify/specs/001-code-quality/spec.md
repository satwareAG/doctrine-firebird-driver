# Feature Specification: code-quality

**Feature Branch**: `001-code-quality`
**Created**: 2026-03-03
**Status**: Draft
**Author**: Michael Wegener (mw@satware.com)

---

## Overview

Eliminate all entries from `phpstan-baseline.neon` and resolve all deprecated API usages
across the `src/` codebase, achieving a clean PHPStan Level 8 pass with zero baseline
suppressions. This improves long-term maintainability and prepares the driver for
Doctrine DBAL 4.x compatibility.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Clean PHPStan Baseline (Priority: P1)

**As a** library maintainer  
**I want** PHPStan Level 8 to pass with an empty `phpstan-baseline.neon`  
**So that** new regressions are immediately visible without being hidden by baseline suppressions

**Why this priority**: The baseline currently suppresses 50+ errors across 7 files. Any new
type error or deprecated API call is invisible until the baseline is cleared.  
**Independent Test**: Run `vendor/bin/phpstan analyse src/ --level=8` — exit code 0, zero errors.

**Acceptance Scenarios**:

1. **Given** the current codebase with 50+ baseline suppressions, **When** PHPStan Level 8 is run
   after all fixes, **Then** it exits with code 0 and reports zero errors with an empty baseline
2. **Given** a new code change that introduces a type error, **When** PHPStan is run,
   **Then** it reports the error immediately (not suppressed by baseline)
3. **Given** the fixed codebase, **When** all existing tests are run,
   **Then** all tests continue to pass (zero regressions)

---

### User Story 2 - Resolve Deprecated DBAL API Usages (Priority: P1)

**As a** library maintainer  
**I want** all deprecated Doctrine DBAL method calls replaced with their modern equivalents  
**So that** the driver compiles cleanly against DBAL 3.x and is ready for DBAL 4.x migration

**Why this priority**: Deprecated APIs are removed in DBAL 4.x. Resolving them now prevents
a breaking upgrade path.  
**Independent Test**: Run `vendor/bin/phpstan analyse src/ --level=8` with `phpstan-deprecation-rules`
enabled — zero deprecation warnings.

**Acceptance Scenarios**:

1. **Given** `FirebirdPlatform.php` calls `getName()`, `getColumnComment()`, `getOldColumnName()`,
   **When** these are replaced with DBAL 3.x non-deprecated equivalents,
   **Then** PHPStan reports no deprecation warnings for those files
2. **Given** `ConnectionWrapper.php` calls `getIdentitySequenceName()`,
   **When** replaced with the current API,
   **Then** no deprecation warning is emitted
3. **Given** `FirebirdDriver.php` implements `VersionAwarePlatformDriver` (deprecated),
   **When** migrated to the `FirebirdConnection` wrapper pattern,
   **Then** the deprecation warning is eliminated

---

### User Story 3 - Fix ext-firebird Stubs Type Errors (Priority: P2)

**As a** library maintainer  
**I want** all `Firebird\*` class references in `Connection.php` to resolve correctly  
**So that** PHPStan can fully type-check the connection layer without unknown-class suppressions

**Why this priority**: The 22 baseline entries in `Connection.php` are caused by missing or
outdated stubs for `Firebird\Batch`, `Firebird\Transaction`, `Firebird\TBuilder`, etc.
Fixing stubs enables full type safety in the most critical file.  
**Independent Test**: Zero `Class Firebird\* not found` errors in PHPStan output.

**Acceptance Scenarios**:

1. **Given** `satwareag/php-firebird-stubs` is updated or stubs are corrected,
   **When** PHPStan analyses `Connection.php`,
   **Then** all `Firebird\Batch`, `Firebird\Transaction`, `Firebird\TBuilder`, `Firebird\Database`
   references resolve without "class not found" errors
2. **Given** `fbird_query_params_tx` is not in current stubs,
   **When** the stub is added or the call is replaced with an available function,
   **Then** PHPStan reports no "function not found" error
3. **Given** the `fbird_prepare` call with 3 parameters,
   **When** the stub signature is corrected to accept optional parameters,
   **Then** PHPStan reports no parameter count mismatch

---

### User Story 4 - Fix Result and SchemaManager Type Errors (Priority: P2)

**As a** library maintainer  
**I want** `Result.php` and `FirebirdSchemaManager.php` to have correct return types  
**So that** the type system accurately reflects the runtime behavior

**Why this priority**: `Result::columnCount()` returns `int|false` but declares `int`.
`FirebirdSchemaManager` accesses `password` and `user` keys that DBAL's type system
doesn't know about.  
**Independent Test**: Zero type errors in `Result.php` and `FirebirdSchemaManager.php`.

**Acceptance Scenarios**:

1. **Given** `Result::columnCount()` can return `false` from `fbird_num_fields()`,
   **When** the return type is corrected and the false case is handled,
   **Then** PHPStan reports no return type mismatch
2. **Given** `FirebirdSchemaManager` accesses `$params['user']` and `$params['password']`,
   **When** the connection params array is properly typed or accessed safely,
   **Then** PHPStan reports no "offset does not exist" errors
3. **Given** `Result::$statement` is written but never read,
   **When** the unused property is removed or used,
   **Then** PHPStan reports no "never read" warning

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST pass `vendor/bin/phpstan analyse src/ --level=8` with exit code 0
- **FR-002**: System MUST have an empty `phpstan-baseline.neon` (zero suppressed errors)
- **FR-003**: System MUST pass `vendor/bin/psalm` with zero errors
- **FR-004**: System MUST pass `vendor/bin/phpcs --standard=phpcs.xml.dist src/` with zero violations
- **FR-005**: System MUST NOT break any existing passing tests
- **FR-006**: System MUST replace all deprecated DBAL 3.x API calls with current equivalents
- **FR-007**: System MUST resolve all `Firebird\*` unknown-class errors via stub updates or code changes

### Non-Functional Requirements

- **NFR-001**: [Compatibility] All fixes MUST be compatible with `doctrine/dbal ^3.10`
- **NFR-002**: [Compatibility] Firebird versions 2.5, 3.0, 4.0, 5.0 must all pass tests
- **NFR-003**: [PHP] PHP 8.1+ compatibility maintained
- **NFR-004**: [Scope] Changes limited to `src/` — no test file modifications unless required for type correctness

### Key Entities

- **`phpstan-baseline.neon`**: Currently suppresses 50+ errors; target state: empty file
- **`Connection.php`**: 22 baseline entries — `Firebird\*` unknown classes, missing functions
- **`FirebirdPlatform.php`**: 12 baseline entries — deprecated DBAL API calls
- **`Firebird3Platform.php`**: 4 baseline entries — deprecated DBAL API calls
- **`ConnectionWrapper.php`**: 2 baseline entries — deprecated `getIdentitySequenceName()`
- **`FirebirdDriver.php`**: 1 baseline entry — deprecated `VersionAwarePlatformDriver`
- **`ExceptionConverter.php`**: 2 baseline entries — always-true comparisons
- **`Result.php`**: 2 baseline entries — return type mismatch, unused property
- **`FirebirdSchemaManager.php`**: 3 baseline entries — missing array keys

### Out of Scope

- Migration to `doctrine/dbal ^4.x` (separate feature `002-dbal4-migration`)
- Test performance optimization (separate feature)
- New driver features or capabilities
- Changes to `tests/` directory (unless stubs need updating)

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `phpstan-baseline.neon` contains zero entries (file is empty or contains only `parameters:`)
- **SC-002**: `vendor/bin/phpstan analyse src/ --level=8` exits with code 0
- **SC-003**: `vendor/bin/psalm` exits with code 0
- **SC-004**: `vendor/bin/phpcs --standard=phpcs.xml.dist src/` exits with code 0
- **SC-005**: All existing tests pass on Firebird 2.5, 3.0, 4.0, 5.0 (zero regressions)
- **SC-006**: Zero deprecated DBAL API calls remain in `src/`

### Definition of Done

- [ ] All acceptance scenarios pass
- [ ] `phpstan-baseline.neon` is empty
- [ ] PHPStan Level 8 passes with zero errors
- [ ] Psalm passes with zero errors
- [ ] PHP_CodeSniffer passes with zero violations
- [ ] All tests pass on all Firebird versions (Docker)
- [ ] `CHANGELOG.md` updated with quality improvement entry

---

## Constitution Check

- [x] **Article I** (DBAL Compatibility): All fixes target `doctrine/dbal ^3.10`; deprecated API replacements use DBAL 3.x non-deprecated equivalents
- [x] **Article II** (PHP Extension): `fbird_*` function usage preserved; stubs updated not replaced
- [x] **Article III** (Test-First): Regression tests run before and after each fix to verify no breakage
- [x] **Article IV** (Multi-Version): Full test suite run on FB 2.5, 3.0, 4.0, 5.0 after all fixes
- [x] **Article V** (Static Analysis): This feature IS the static analysis improvement
- [x] **Article VI** (Security): No security-sensitive changes; type fixes improve safety
- [x] **Article VII** (Simplicity): No new abstractions; fixes are surgical replacements
- [x] **Article VIII** (Performance): No performance impact expected; type fixes are compile-time only
- [x] **Article IX** (Documentation): `CHANGELOG.md` updated; PHPDoc improved where types are fixed
- [x] **Article X** (Docker Testing): Full Docker test suite run to verify zero regressions

---

## Clarifications

*[To be populated during Phase 3: Clarify if needed]*

| # | Question | Answer | Impact |
|---|----------|--------|--------|
| 1 | Should `VersionAwarePlatformDriver` be removed or kept with suppression? | Remove — use `FirebirdConnection` wrapper | Eliminates 1 baseline entry, improves DBAL 4.x readiness |
| 2 | Should `Firebird\*` stubs be updated in `satwareag/php-firebird-stubs` or worked around in code? | Update stubs — they are the authoritative source | Eliminates 22 baseline entries cleanly |

---

## Open Questions

*None — spec is complete and ready for planning.*
