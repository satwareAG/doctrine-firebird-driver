# Deprecation-Free Release Plan

## Document Control

- **Status**: ✅ Fully Implemented and Verified
- **Date**: 2026-01-02
- **Updated**: 2026-01-02

---

## Executive Summary

This plan establishes a comprehensive deprecation management strategy for the doctrine-firebird-driver project, ensuring:

1. **CI/CD deprecation detection** via PHPStan deprecation rules
2. **Proper isolation** of tests using deprecated APIs (backward compatibility testing)
3. **Clean main test suite** that runs without triggering deprecation warnings
4. **Documentation** of intentional DBAL API deprecations required for compatibility

---

## Background: How Doctrine Handles Deprecations

### Doctrine's Approach

Doctrine DBAL/ORM uses the `doctrine/deprecations` library for gradual API transitions:

1. **Deprecation Triggering**: `Deprecation::trigger()` logs deprecation notices
2. **PHPUnit Integration**: `VerifyDeprecations` trait allows explicit expectation
3. **Identifier-Based**: Each deprecation has a unique URL identifier for tracking

### Deprecation Identifiers Found in DBAL

| Identifier | API | Status |
|------------|-----|--------|
| `https://github.com/doctrine/dbal/pull/5509` | `Types::ARRAY`, `Types::OBJECT` | Deprecated in 3.x |
| `https://github.com/doctrine/dbal/pull/5403` | `Type::getName()` return value | Deprecated in 4.x |
| `https://github.com/doctrine/dbal/pull/5996` | `VersionAwarePlatformDriver` | Deprecated in 4.x |

---

## Implementation Status

### ✅ Completed

#### 1. PHPStan Deprecation Rules (composer.json)

```json
"require-dev": {
    "phpstan/phpstan-deprecation-rules": "^1.2"
}
```

**PHPStan configuration** (phpstan.neon.dist):

```neon
includes:
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon
```

#### 2. PHPUnit Configuration Split

**Main Suite** (`tests/phpunit.xml`):
- Excludes `#[Group('deprecated')]` tests
- Runs clean without deprecation warnings
- Used for CI/CD quality gates

**Deprecated Suite** (`tests/phpunit-deprecated.xml`):
- Only runs `#[Group('deprecated')]` tests
- Used for backward compatibility verification
- Allows explicit deprecation expectation

#### 3. Test Updates

| File | Change | Reason |
|------|--------|--------|
| `TypeConversionTest.php` | Added `#[Group('deprecated')]` + `expectDeprecationWithIdentifier()` | Uses `Type::getName()` |
| `SchemaManagerFunctionalTestCase.php` | Added `#[Group('deprecated')]` | Uses `Types::ARRAY`, `Types::OBJECT` |
| `PlatformTestCase.php` | Added `#[Group('deprecated')]` | Uses `Types::ARRAY` |

#### 4. Removed `@psalm-suppress DeprecatedConstant`

All test files now use `#[Group('deprecated')]` instead of inline suppressions.

---

## Intentional DBAL Deprecations (psalm.xml.dist)

These suppressions are **required** for DBAL compatibility and should remain:

### DeprecatedMethod

- **API**: `AbstractPlatform::getIdentitySequenceName()`
- **Files**: `FirebirdPlatform.php`, `Firebird3Platform.php`
- **Reason**: Required for Firebird sequence-based identity columns

### DeprecatedInterface

- **API**: `VersionAwarePlatformDriver`
- **Files**: `FirebirdDriver.php`, `Driver.php`, `Connection.php`
- **Reason**: Must implement for version-aware platform selection

### DeprecatedProperty

- **Files**: `FirebirdPlatform.php`
- **Reason**: Platform configuration properties

**Note**: These will be addressed in DBAL 5.x migration when the deprecated APIs are removed.

---

## Usage

### Running Tests

```bash
# Main suite - no deprecated API usage
vendor/bin/phpunit -c tests/phpunit.xml

# Deprecated compatibility tests only
vendor/bin/phpunit -c tests/phpunit-deprecated.xml

# All tests (both suites)
vendor/bin/phpunit -c tests/phpunit.xml && vendor/bin/phpunit -c tests/phpunit-deprecated.xml
```

### PHPStan Deprecation Check

```bash
vendor/bin/phpstan analyse src/ --level=8
```

PHPStan will report any deprecated API usage in source code (not suppressible via baseline).

---

## Verification Checklist

- [x] `phpstan-deprecation-rules` v1.2.1 installed
- [x] `tests/phpunit-deprecated.xml` created (5 deprecated tests)
- [x] `tests/phpunit.xml` excludes deprecated group
- [x] Tests using deprecated APIs marked with `#[Group('deprecated')]`
- [x] `@psalm-suppress DeprecatedConstant` removed from tests
- [x] Intentional DBAL deprecations documented
- [x] PHPStan baseline updated (107 errors including 28 deprecation warnings)
- [x] PHPStan passes with `[OK] No errors`

---

## Future Work

### DBAL 5.x Migration

When Doctrine DBAL 5.x is released:

1. Remove `VersionAwarePlatformDriver` implementation
2. Update platform sequence handling
3. Remove deprecated Types usage from tests
4. Update psalm.xml.dist suppressions

### Monitoring

- Watch Doctrine DBAL releases for deprecation removal
- Run deprecated test suite before major upgrades
- Keep deprecation identifiers up-to-date

---

## References

- [Doctrine Deprecations Library](https://github.com/doctrine/deprecations)
- [DBAL Deprecation PR #5509](https://github.com/doctrine/dbal/pull/5509)
- [PHPStan Deprecation Rules](https://github.com/phpstan/phpstan-deprecation-rules)
- [PHPUnit Groups](https://docs.phpunit.de/en/11.5/attributes.html#group)
