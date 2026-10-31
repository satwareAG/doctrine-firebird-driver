# AGENTS.md

## About doctrine-firebird-driver

**doctrine-firebird-driver** is the official Doctrine DBAL driver for Firebird. It bridges the modern `satware/php-firebird` native extension with the Doctrine ecosystem, supporting Firebird 3.0, 4.0, and 5.0 features.

**Current release:** v4.7.6 (2026-10-26) on `4.5.x` branch. <!-- x-releaser-pleaser-version -->
**Maintenance line:** v3.19.2 (2026-08-08) on `3.10.x` branch (DBAL 3.x, critical fixes only).

---

## Integration Standards

### SDD/TDD Workflow
1. **Spec**: Update `specs/` before changing code.
2. **Test**: Use `phpunit` with a live Firebird container (see `docker-compose.yml`).
3. **Pillars**: DBAL 4.4/4.5 (`^4.4 || ^4.5`, floor leg pinned in CI) on `4.5.x` (default; branch name tracks the DBAL line per spec 005 - package releases v4.7.x are orthogonal; upstream live sync surfaces 4.5.x/4.6.x/5.0.x); DBAL 3.10.x on `3.10.x` (maintenance, upstream line still active).

### IPADP Conformance
This project follows **L3 IPADP conformance**.
- **Metadata**: `specs/metadata.json`
- **Linkages**: Consumes `php-firebird` (upstream). Provided to a private downstream consumer (name redacted per `specs/metadata.json` IPADP privacy rule).

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

### Pitfalls

- **Branch switching (3.10.x / 3.18.x <-> 4.5.x)**: `composer.lock` is gitignored (library discipline, #195). A local lock from the previous branch survives the switch and poisons resolution. After every branch switch run `composer update --ignore-platform-req=ext-firebird`, never `composer install`. CI always runs `composer update`.
- **Release PR date literal**: releaser-pleaser rewrites the version marker in AGENTS.md but keeps the date literal from the previous section (mechanism documented in #229; recurred on #233). Before merging a release PR, correct the date to the actual release date.
- **Dependabot CI-action bumps**: agent-mergeable only after verifying the pinned SHA dereferences from the tagged release (annotated tags: the ref endpoint is a tag object, dereference one hop). Record the check in the merge note; precedent alone is not provenance (council 2026-10-25).

### Quality Gates

| Gate | Command | Status |
|------|---------|--------|
| PHPStan 8 | `./vendor/bin/phpstan analyse` | 0 new errors (86 baselined; ratchet: #253) |
| Psalm | `./vendor/bin/psalm --no-cache` | 0 new errors (146 baselined; ratchet: #253) |
| PHP_CodeSniffer | `./vendor/bin/phpcs -q` | 0 errors |
| PHPUnit | `./vendor/bin/phpunit` | 1621 unit + 577 functional per FB version |
