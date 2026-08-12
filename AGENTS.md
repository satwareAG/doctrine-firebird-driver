# AGENTS.md

## About doctrine-firebird-driver

**doctrine-firebird-driver** is the official Doctrine DBAL driver for Firebird. It bridges the modern `satware/php-firebird` native extension with the Doctrine ecosystem, supporting Firebird 3.0, 4.0, and 5.0 features.

**Current release:** v4.7.0 (2026-08-10) on `4.4.x` branch.
**Maintenance line:** v3.19.2 (2026-08-08) on `3.10.x` branch (DBAL 3.x, critical fixes only).

---

## Integration Standards

### SDD/TDD Workflow
1. **Spec**: Update `specs/` before changing code.
2. **Test**: Use `phpunit` with a live Firebird container (see `docker-compose.yml`).
3. **Pillars**: DBAL 4.4.x on `4.4.x` (active); DBAL 3.10.x on `3.10.x` (maintenance).

### IPADP Conformance
This project follows **L3 IPADP conformance**.
- **Metadata**: `specs/metadata.json`
- **Linkages**: Consumes `php-firebird` (upstream). Provided to `satag-amicron-entity-bundle` (downstream).

---

## Technical Context for Agents

- **Primary Source**: `src/Driver/Firebird/`
- **Driver**: `src/Driver/Firebird/Driver.php`
- **Platform**: `src/Platforms/FirebirdPlatform.php` (SQL generation logic)
- **Schema Manager**: `src/Schema/FirebirdSchemaManager.php`
- **Comparator**: `src/Schema/FirebirdComparator.php`
- **Connection**: `src/Driver/Firebird/Connection.php`
- **Middleware**: `src/Driver/Firebird/Middleware/` (charset handling)

### Test Structure

| Suite | Location | Purpose |
|-------|----------|---------|
| Unit | `tests/Test/Unit/` | Pure logic, no DB connection |
| Platform | `tests/Test/Platforms/` | SQL generation per FB version |
| Functional | `tests/Test/Functional/` | Live FB3/4/5 containers |
| Integration | `tests/Test/Integration/` | ORM-level integration |
| Schema | `tests/Test/Schema/` | Adapted DBAL 4.x comparator tests |

### Quality Gates

| Gate | Command | Status |
|------|---------|--------|
| PHPStan 8 | `./vendor/bin/phpstan analyse` | 0 errors |
| Psalm | `./vendor/bin/psalm --no-cache` | 0 errors (baseline: 112 pre-existing) |
| PHP_CodeSniffer | `./vendor/bin/phpcs -q` | 0 errors |
| PHPUnit | `./vendor/bin/phpunit` | 1620 unit + 575 functional per FB version |
