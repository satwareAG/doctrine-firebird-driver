# Deprecation Fixes Plan

**Date**: 2025-12-05
**Related Issue**: [#24 - Replace deprecated `bindParam()` with `bindValue()` for DBAL 3.x compatibility](https://github.com/satwareAG/doctrine-firebird-driver/issues/24)
**Status**: Planning

## Summary

This document outlines all deprecation notices found in the doctrine-firebird-driver codebase and provides a plan to address them for DBAL 3.x/4.x compatibility.

## Environment

- PHP: ^8.1 (supports up to 8.4)
- Doctrine DBAL: ^3.10
- Doctrine ORM: ^3.5
- doctrine/deprecations: ^0.5.3|^1

## Deprecation Issues Found

### 1. **HIGH PRIORITY** - Statement::bindParam() Deprecation

**Location**: `src/Driver/Firebird/Statement.php:137-166`

**Issue**: The `bindParam()` method is deprecated in DBAL 3.x per [doctrine/dbal#5563](https://github.com/doctrine/dbal/pull/5563). When using the driver, this deprecation warning appears:

```
Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement::bindParam is deprecated. 
Use bindValue() instead.
```

**Current Code**:
```php
public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
{
    Deprecation::trigger(
        'doctrine/dbal',
        'https://github.com/doctrine/dbal/pull/5563',
        '%s is deprecated. Use bindValue() instead.',
        __METHOD__,
    );
    // ... implementation
}
```

**Problem Analysis**:
- `bindValue()` currently calls `bindParam()` internally (line 131)
- This causes a deprecation warning on every `bindValue()` call
- Need to refactor so `bindValue()` contains the core binding logic

**Fix Plan**:
1. Move binding logic from `bindParam()` to `bindValue()` 
2. Make `bindParam()` call `bindValue()` (inverted dependency)
3. Keep `bindParam()` for backward compatibility but mark as truly deprecated

### 2. **MEDIUM** - Statement::bindValue() Missing Type Warning

**Location**: `src/Driver/Firebird/Statement.php:116-127`

**Issue**: Not passing `$type` to `bindValue()` is deprecated per [doctrine/dbal#5558](https://github.com/doctrine/dbal/pull/5558).

**Status**: Already implemented correctly - triggers deprecation when type not provided.

**Action**: No change needed.

### 3. **MEDIUM** - Statement::execute() with $params Warning

**Location**: `src/Driver/Firebird/Statement.php:218-228`

**Issue**: Passing `$params` to `execute()` is deprecated per [doctrine/dbal#5556](https://github.com/doctrine/dbal/pull/5556).

**Status**: Already implemented correctly - triggers deprecation when params passed.

**Action**: No change needed.

### 4. **LOW** - FirebirdDriver::getSchemaManager() Deprecation

**Location**: `src/Driver/FirebirdDriver.php:106-118`

**Issue**: Method is deprecated in favor of `FirebirdPlatform::createSchemaManager()`.

**Status**: Already marked as deprecated with proper trigger.

**Action**: Consider removal in next major version.

### 5. **LOW** - FirebirdSchemaManager::listTableDetails() Deprecation

**Location**: `src/Schema/FirebirdSchemaManager.php:147-152`

**Issue**: Method is deprecated in favor of `introspectTable()`.

**Status**: Already marked as deprecated.

**Action**: Consider removal in next major version.

### 6. **LOW** - Platform getBinaryMaxLength() Deprecation

**Location**: `src/Platforms/FirebirdPlatform.php:598`

**Issue**: Platform method deprecation from DBAL.

**Status**: Triggers deprecation as expected.

**Action**: Monitor DBAL 4.x requirements.

### 7. **LOW** - Platform getAlterTableSQL() Deprecation

**Location**: `src/Platforms/FirebirdPlatform.php:1194`

**Issue**: Platform method deprecation from DBAL.

**Status**: Triggers deprecation as expected.

**Action**: Monitor DBAL 4.x requirements.

## Implementation Plan

### Phase 1: Fix bindParam/bindValue (Issue #24)

**Priority**: HIGH
**Estimated Effort**: 2-4 hours
**Risk**: Medium (core functionality change)

#### Step 1: Refactor bindValue() to contain core logic

```php
public function bindValue($param, $value, $type = ParameterType::STRING): bool
{
    if (func_num_args() < 3) {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5558',
            'Not passing $type to Statement::bindValue() is deprecated.'
            . ' Pass the type corresponding to the parameter being bound.',
        );
    }

    // Store the value for later reference
    $this->boundValues[$param] = $value;
    
    // Core binding logic (moved from bindParam)
    if (is_int($param)) {
        if (! isset($this->parameterMap[$param])) {
            throw new Exception(sprintf('Positional Parameter %d not found in the parameter map', $param));
        }
    } else {
        $params = array_flip($this->parameterMap);
        if (! isset($params[$param])) {
            throw new Exception(sprintf('Named Parameter %s not found in the parameter map', $param));
        }
        $param = $params[$param];
    }

    // Handle LARGE_OBJECT type
    if ($type === ParameterType::LARGE_OBJECT) {
        if ($value !== null && is_resource($value)) {
            $content = stream_get_contents($value);
            if (is_resource($value)) {
                fclose($value);
            }
            $value = $content;
            $type = ParameterType::STRING;
        }
    }

    assert(is_int($param));
    $this->queryParamBindings[$param] = $value;
    $this->queryParamTypes[$param] = $type;

    return true;
}
```

#### Step 2: Make bindParam() call bindValue()

```php
/**
 * @deprecated Use bindValue() instead.
 */
public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
{
    Deprecation::trigger(
        'doctrine/dbal',
        'https://github.com/doctrine/dbal/pull/5563',
        '%s is deprecated. Use bindValue() instead.',
        __METHOD__,
    );

    if (func_num_args() < 3) {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5558',
            'Not passing $type to Statement::bindParam() is deprecated.'
            . ' Pass the type corresponding to the parameter being bound.',
        );
    }

    // Store reference for compatibility
    $this->boundValues[$param] = &$variable;
    
    // Delegate to bindValue with current value
    return $this->bindValue($param, $variable, $type);
}
```

#### Step 3: Update tests

- Ensure all tests using `bindParam()` still pass
- Add tests for `bindValue()` direct usage
- Verify no regression in parameter binding behavior

### Phase 2: DBAL 4.x Compatibility Analysis

**Priority**: LOW (future planning)
**Timeline**: When DBAL 4.0 is released

DBAL 4.x will likely:
- Remove `bindParam()` entirely
- Remove `$params` parameter from `execute()`
- Remove deprecated platform methods

**Action Items**:
1. Monitor DBAL 4.x development
2. Plan major version bump for DBAL 4.x support
3. Remove deprecated methods in next major version

## Testing Strategy

### Unit Tests
- [ ] Test `bindValue()` with all parameter types
- [ ] Test `bindValue()` with positional parameters
- [ ] Test `bindValue()` with named parameters
- [ ] Test `bindParam()` triggers deprecation
- [ ] Test BLOB/LARGE_OBJECT handling in `bindValue()`

### Integration Tests
- [ ] Run full test suite after changes
- [ ] Verify INSERT/UPDATE/DELETE with bound parameters
- [ ] Verify SELECT with bound parameters
- [ ] Test with Doctrine ORM entity operations

### Deprecation Testing
- [ ] Enable deprecation error handler in PHPUnit
- [ ] Verify `bindValue()` does NOT trigger deprecation (when used correctly)
- [ ] Verify `bindParam()` DOES trigger deprecation

## Rollback Plan

If issues are discovered:
1. Revert to previous `bindParam()`-based implementation
2. Document specific failure cases
3. Re-analyze and adjust fix approach

## References

- [Doctrine DBAL PR #5563 - Deprecate Statement::bindParam()](https://github.com/doctrine/dbal/pull/5563)
- [Doctrine DBAL PR #5558 - Deprecate not passing type to bind*](https://github.com/doctrine/dbal/pull/5558)
- [Doctrine DBAL PR #5556 - Deprecate passing params to execute()](https://github.com/doctrine/dbal/pull/5556)
- [GitHub Issue #24](https://github.com/satwareAG/doctrine-firebird-driver/issues/24)

## Checklist

- [ ] Implement Phase 1 refactoring
- [ ] Update unit tests
- [ ] Run full test suite
- [ ] Update CHANGELOG.md
- [ ] Create PR for review
- [ ] Merge and release patch version
