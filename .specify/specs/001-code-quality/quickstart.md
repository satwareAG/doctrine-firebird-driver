# Quickstart / Validation Scenarios: code-quality

**Feature**: `001-code-quality`
**Created**: 2026-03-03

## Validation Scenario 1: [Title]

```php
// Minimal code to validate the feature works
```

**Expected**: [What should happen]

## Running Tests

```bash
# Unit tests only
php vendor/bin/phpunit tests/Test/Unit/ --filter [TestClass]

# Functional tests (requires Docker)
cd tests && docker compose up -d
php vendor/bin/phpunit tests/Test/Functional/ --filter [TestClass]
```
