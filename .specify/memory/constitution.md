# Project Constitution: doctrine-firebird-driver

**Version**: 1.0  
**Created**: 2026-03-03  
**Status**: Active  
**Maintainer**: Michael Wegener (mw@satware.com)

---

## Preamble

This constitution defines the NON-NEGOTIABLE principles governing all development on
`satag/doctrine-firebird-driver` — the Doctrine DBAL driver for Firebird SQL 2.5–5.0.
All specifications, plans, tasks, and implementations MUST comply with these articles.

---

## Article I: Doctrine DBAL Compatibility First

**MUST** maintain compatibility with `doctrine/dbal ^3.10` on the `3.0.x` branch.  
**MUST** target `doctrine/dbal ^4.1` on the `4.0.x` branch.  
**MUST NOT** introduce breaking changes to the public API without a major version bump.  
**MUST** use `FirebirdConnection` wrapper pattern for DBAL 4.x forward compatibility.

**Rationale**: This is a library consumed by downstream applications. API stability is paramount.

---

## Article II: PHP Extension Dependency

**MUST** target `ext-firebird ^7.0.0-rc.47` (satwareAG/php-firebird fork).  
**MUST** use `fbird_*` function aliases (not deprecated `ibase_*`).  
**MUST NOT** use PDO-based Firebird drivers.  
**MUST** support PHP 8.1+ (minimum), with PHP 8.3+ as the recommended target.

**Rationale**: The driver is built on the native PHP Firebird extension, not PDO.

---

## Article III: Test-First Imperative (NON-NEGOTIABLE)

**MUST** write tests BEFORE implementation code.  
**MUST** achieve ≥80% code coverage for all new features.  
**MUST** test against all supported Firebird versions: 2.5, 3.0, 4.0, 5.0.  
**MUST** include unit tests, functional tests, and integration tests as appropriate.  
**MUST NOT** merge code that breaks existing tests.

**Rationale**: The driver interacts with a database engine across 4 major versions. Regressions are unacceptable.

---

## Article IV: Multi-Version Firebird Support

**MUST** validate all changes against Firebird 2.5, 3.0, 4.0, and 5.0.  
**MUST** use version-specific platform classes (`Firebird3Platform`, `Firebird4Platform`, `Firebird5Platform`).  
**MUST** document version-specific behavior differences.  
**SHOULD** use Docker-based test environments for version isolation.

**Rationale**: The driver serves users on all active Firebird versions.

---

## Article V: Static Analysis Compliance

**MUST** pass PHPStan at Level 8 with `phpstan-strict-rules`.  
**MUST** pass Psalm static analysis.  
**MUST** comply with PSR-12 coding standards via PHP_CodeSniffer.  
**MUST NOT** add entries to `phpstan-baseline.neon` without documented justification.  
**SHOULD** use Rector for automated code quality improvements.

**Rationale**: High static analysis standards prevent runtime errors in production.

---

## Article VI: Security and Data Integrity

**MUST** prevent SQL injection through proper parameter binding.  
**MUST** handle Firebird-specific data types correctly (BLOB, TIMESTAMP, BOOLEAN, etc.).  
**MUST** validate all configuration inputs (e.g., `like_cast_length` range 1–8191).  
**MUST NOT** expose connection credentials in error messages or logs.  
**MUST** handle transaction lifecycle correctly (commit, rollback, savepoints, nesting).

**Rationale**: Database drivers are security-critical infrastructure.

---

## Article VII: Simplicity Over Abstraction

**MUST NOT** add abstraction layers that wrap Doctrine DBAL abstractions.  
**MUST** use framework-native patterns (Doctrine interfaces, PHP extension functions).  
**SHOULD** prefer direct implementation over complex inheritance hierarchies.  
**MUST** justify every new class with a clear single responsibility.

**Rationale**: Unnecessary abstraction increases maintenance burden and debugging complexity.

---

## Article VIII: Performance Awareness

**MUST** document performance implications of driver-level decisions (e.g., LIKE CAST).  
**SHOULD** prefer index-friendly SQL patterns.  
**MUST NOT** introduce N+1 query patterns in schema introspection.  
**SHOULD** benchmark performance-sensitive changes against baseline.

**Rationale**: Database drivers are on the critical path of every application query.

---

## Article IX: Documentation as Code

**MUST** update `README.md` for any user-facing configuration changes.  
**MUST** document breaking changes in `CHANGELOG.md`.  
**MUST** add inline PHPDoc for all public API methods.  
**SHOULD** create `docs/` entries for complex features or known limitations.

**Rationale**: This is a public library — documentation is part of the deliverable.

---

## Article X: Docker-First Testing Environment

**MUST** use Docker Compose for functional and integration test environments.  
**MUST** support running tests without local Firebird installation.  
**MUST** maintain `tests/docker-compose.yml` for all supported Firebird versions.  
**SHOULD** keep CI/CD pipelines (AppVeyor, GitHub Actions) green.

**Rationale**: Reproducible test environments prevent "works on my machine" failures.

---

## Enforcement

All specifications MUST include a **Constitution Check** section verifying compliance with
applicable articles. Any deviation requires explicit documentation and maintainer approval.
