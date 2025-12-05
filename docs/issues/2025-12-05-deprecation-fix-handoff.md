# Task Handoff: Fix Deprecations in doctrine-firebird-driver

**Created**: 2025-12-05
**Purpose**: Context handoff for implementing deprecation fixes

---

## Task Summary

Fix deprecated `bindParam()` usage that causes deprecation warnings on every `bindValue()` call in the Firebird DBAL driver.

## Compatibility Targets

- **PHP**: ^8.1
- **Doctrine DBAL**: 3.10.x

## Problem Statement

**GitHub Issue**: [#24 - Replace deprecated `bindParam()` with `bindValue()` for DBAL 3.x compatibility](https://github.com/satwareAG/doctrine-firebird-driver/issues/24)

**Root Cause**: In `src/Driver/Firebird/Statement.php`, the `bindValue()` method calls `bindParam()` internally (line 131), which triggers a deprecation warning because `bindParam()` is deprecated in DBAL 3.x per [PR #5563](https://github.com/doctrine/dbal/pull/5563).

**Impact**: Every parameter binding operation produces a deprecation notice in production.

## Files to Modify

### Primary: `src/Driver/Firebird/Statement.php`

**Current problematic code** (lines ~127-166):

```php
public function bindValue($param, $value, $type = ParameterType::STRING): bool
{
    if (func_num_args() < 3) {
        Deprecation::trigger(/* missing type warning */);
    }
    return $this->bindParam($param, $value, $type);  // ← PROBLEM: Calls deprecated method
}

public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
{
    Deprecation::trigger(/* deprecated warning */);  // ← Always triggers!
    // ... core binding logic ...
}
```

## Implementation Steps

### Step 1: Refactor `bindValue()` to contain core logic

Move all the binding logic from `bindParam()` into `bindValue()`:

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

    $this->boundValues[$param] = $value;
    
    // Parameter mapping logic
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

### Step 2: Update `bindParam()` to delegate to `bindValue()`

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

    // Store reference for backward compatibility
    $this->boundValues[$param] = &$variable;
    
    // Delegate to bindValue - don't trigger deprecation again
    return $this->bindValueInternal($param, $variable, $type);
}
```

**Note**: May need a private `bindValueInternal()` method to avoid double deprecation warnings when `bindParam()` calls `bindValue()`.

### Step 3: Run tests

```bash
cd tests && ./phpunit.sh
```

### Step 4: Update CHANGELOG.md

Add entry for the fix.

## Testing Checklist

- [ ] All existing tests pass
- [ ] `bindValue()` does NOT trigger deprecation when called correctly
- [ ] `bindParam()` DOES trigger deprecation when called
- [ ] BLOB/LARGE_OBJECT parameter binding works
- [ ] Named and positional parameters work
- [ ] Integration tests with Doctrine ORM pass

## Related Documentation

- **Fix Plan**: `docs/issues/2025-12-05-deprecation-fixes-plan.md`
- **Doctrine DBAL PR #5563**: https://github.com/doctrine/dbal/pull/5563
- **Doctrine DBAL PR #5558**: https://github.com/doctrine/dbal/pull/5558

## Next Task Prompt

```
Implement deprecation fixes for doctrine-firebird-driver per the handoff document at:
docs/issues/2025-12-05-deprecation-fix-handoff.md

Target compatibility:
- PHP: ^8.1
- Doctrine DBAL: 3.10.x

Main task: Refactor Statement.php so bindValue() contains core logic and bindParam() 
delegates to it (inverted dependency pattern). This eliminates the deprecation warning 
that currently triggers on every parameter binding.
```
