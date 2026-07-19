# AGENTS.md

## About doctrine-firebird-driver

**doctrine-firebird-driver** is the official Doctrine DBAL driver for Firebird. It bridges the modern `satware/php-firebird` native extension with the Doctrine ecosystem, supporting Firebird 3.0, 4.0, and 5.0 features.

---

## Integration Standards

### SDD/TDD Workflow
1. **Spec**: Update `specs/` before changing code.
2. **Test**: Use `phpunit` with a live Firebird container (see `docker-compose.yml`).
3. **Pillars**: Compatibility with DBAL 3.x and 4.x.

### IPADP Conformance
This project follows **L3 IPADP conformance**.
- **Metadata**: `specs/metadata.json`
- **Linkages**: Consumes `php-firebird` (upstream). Provided to `satag-amicron-entity-bundle` (downstream).

---

## Technical Context for Agents

- **Primary Source**: `lib/Doctrine/DBAL/Driver/Firebird/`.
- **Driver**: `Driver.php`.
- **Platform**: `Platform.php` (SQL generation logic).
- **Schema Manager**: `SchemaManager.php`.
