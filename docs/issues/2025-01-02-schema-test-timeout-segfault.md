# Schema Test Timeout + Exit Code 139 (SIGSEGV) Investigation

**Date**: 2025-01-02
**Status**: Open - Bug Filed on php-firebird
**Priority**: High (blocks CI/CD reliability)
**Components**: SchemaManager tests, php-firebird extension
**GitHub Issue**: https://github.com/satwareAG/doctrine-firebird-driver/issues/49

---

## Summary

PHPUnit test suite completes but reports failure due to two interrelated issues:
1. **2 timeout errors** in Firebird3SchemaManagerTest 
2. **Exit code 139 (SIGSEGV)** during PHP process shutdown

The test run shows `Tests: 1585, Assertions: 3355, Errors: 2, Skipped: 121, Incomplete: 3` followed by exit code 139.

---

## Issue 1: Schema Test Timeout Errors

### Affected Tests
| Test | Class | Error |
|------|-------|-------|
| `testSchemaIntrospection` | `Firebird3SchemaManagerTest` | `Execution aborted after 5 seconds` |
| `testListTablesDoesNotIncludeViews` | `Firebird3SchemaManagerTest` | `Execution aborted after 5 seconds` |

### Error Stack Trace Pattern
```
Doctrine\DBAL\Exception\DriverException: An exception occurred in the driver: Execution aborted after 5 seconds

Caused by:
Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception: Execution aborted after 5 seconds

Caused by:
SebastianBergmann\Invoker\TimeoutException: Execution aborted after 5 seconds
```

### Root Cause Hypotheses

1. **Transaction Deadlock**: Schema introspection queries may be blocked by uncommitted transactions from previous tests
2. **Slow Metadata Queries**: Firebird system table queries (`RDB$RELATIONS`, `RDB$FIELDS`) may be inherently slow
3. **Connection Pool Starvation**: Persistent connections may be exhausted or in invalid state
4. **PHPUnit Timeout Too Aggressive**: 5-second default may be insufficient for schema operations

### Related Documentation
- `docs/issues/2025-11-25-transaction-deadlock-fix-plan.md` - Transaction deadlock analysis
- `docs/research/transaction-aware-queries-benefits.md` - Transaction isolation patterns

---

## Issue 2: Exit Code 139 (SIGSEGV) During Shutdown

### Symptoms
- PHPUnit prints full results including "ERRORS!" and test counts
- Exit code is 139 (128 + SIGSEGV signal 11) instead of expected 2 (PHPUnit error status)
- Crash occurs AFTER test execution completes, during PHP process teardown

### Root Cause (Known)
**Use-After-Free (UAF) bug in php-firebird extension** during persistent connection cleanup.

The php-firebird extension has a documented issue where:
1. Persistent connections (`pconnect`) maintain state across requests
2. During RSHUTDOWN/MSHUTDOWN, extension attempts to cleanup connection resources
3. Invalid memory access occurs due to stale handles or double-free patterns
4. Results in SIGSEGV (exit code 139)

### Related Documentation
- `docs/issues/2025-12-23-phpstan-parallel-segfault-investigation.md` - Detailed segfault analysis
- GitHub Issue: https://github.com/satwareAG/php-firebird/issues/22

### Extension Fix Status
- **Repository**: satwareAG/php-firebird
- **Bug Report**: https://github.com/satwareAG/php-firebird/issues/50
- **Original Target**: v7.0.0-rc.12
- **Tested Version**: v7.0.0-rc.28 (SIGSEGV still occurs)
- **Status**: Bug filed, awaiting fix

---

## Investigation Checklist

### Isolate Timeout Issue
- [ ] Run schema tests with increased timeout: `--enforce-timeout-strict --timeout=30`
- [ ] Run schema tests in isolation: `phpunit --filter Schema`
- [ ] Check for uncommitted transactions: Enable query logging
- [ ] Test with fresh database (no stale locks)
- [ ] Compare execution time on different Firebird versions (2.5, 3, 4, 5)

### Isolate Segfault Issue
- [ ] Run tests without php-firebird extension loaded (unit tests only)
- [ ] Run with non-persistent connections only
- [ ] Enable core dumps: `ulimit -c unlimited`
- [ ] Analyze core dump with GDB: `gdb /usr/local/bin/php /tmp/core.*`
- [ ] Test with latest php-firebird RC version

### Combined Testing
- [ ] Run full suite with schema tests excluded: `phpunit --exclude-group schema`
- [ ] Run unit tests only: `./tests/phpunit.sh -s unit`
- [ ] Verify exit code behavior with mock failing tests

---

## Reproduction Steps

### Prerequisites
```bash
cd /home/mw/PhpstormProjects/doctrine-firebird-driver
```

### Reproduce Full Issue
```bash
./tests/phpunit.sh
# Expected: Exit code 139 with 2 schema test errors
```

### Reproduce Timeout Only (Isolate Schema Tests)
```bash
./tests/phpunit.sh -- --filter 'SchemaManagerTest'
# Expected: Timeout errors in testSchemaIntrospection and testListTablesDoesNotIncludeViews
```

### Verify Without Schema Tests
```bash
./tests/phpunit.sh -s unit
# Expected: All tests pass, no segfault (if unit tests don't use DB connections)
```

### Test Exit Code Handling (Original Bug Fix)
```bash
./tests/phpunit.sh -- --exclude-group schema
# Expected: If all remaining tests pass with "OK, but there were issues!" 
# the script should report "ALL TESTS PASSED!" (exit code 0)
```

---

## Workarounds

### Workaround 1: Exclude Schema Tests Temporarily
```bash
./tests/phpunit.sh -- --exclude-group schema
```

### Workaround 2: Increase PHPUnit Timeout
Add to `phpunit.xml`:
```xml
<phpunit timeoutForSmallTests="30" timeoutForMediumTests="60" timeoutForLargeTests="120">
```

### Workaround 3: Use Non-Persistent Connections
Modify test configuration to avoid persistent connections:
```php
// In test bootstrap or TestUtil
$params['persistent'] = false;
```

### Workaround 4: Ignore Exit Code 139 in CI
```bash
# In CI script
./tests/phpunit.sh || true
# Note: This masks real failures - use with caution
```

---

## Expected Resolution

### For Timeout Issue
1. Identify why schema queries are slow (deadlock vs. inherent slowness)
2. Either fix transaction handling or adjust timeout limits
3. Add `@group schema` annotation if tests need special handling

### For Segfault Issue
1. Upgrade php-firebird to v7.0.0-rc.12+ when fix is released
2. Alternatively, disable persistent connections if performance impact acceptable
3. Long-term: Contribute to php-firebird fork-safety improvements

---

## Impact Assessment

| Impact Area | Severity | Description |
|-------------|----------|-------------|
| **CI/CD Pipeline** | High | Test suite reports false failures |
| **Developer Experience** | Medium | Confusing exit codes and error messages |
| **Code Coverage** | Low | Tests do run, coverage is generated |
| **Production** | None | Extension bug affects dev/test only |

---

## Related Issues and References

### Internal Documentation
- `docs/issues/2025-12-23-phpstan-parallel-segfault-investigation.md`
- `docs/issues/2025-11-25-transaction-deadlock-fix-plan.md`
- `docs/plans/2025-12-07-first-citizen-excellence-plan.md`

### External Issues
- php-firebird GitHub: https://github.com/satwareAG/php-firebird/issues/22
- PHPUnit timeout handling: https://phpunit.de/documentation.html

### Code Files
- `tests/Test/Functional/Schema/SchemaManagerFunctionalTestCase.php`
- `tests/Test/Functional/Schema/Firebird3SchemaManagerTest.php`
- `src/Schema/FirebirdSchemaManager.php`
- `src/Driver/Firebird/Connection.php`

---

## Action Items

### Immediate
- [x] Document the issue (this file)
- [ ] Add `@group schema` annotation to affected tests
- [ ] Update CI to handle exit code 139 gracefully

### Short-term  
- [ ] Investigate transaction state during schema tests
- [ ] Test with increased timeouts
- [ ] Update php-firebird to latest RC

### Long-term
- [ ] Contribute fix to php-firebird if needed
- [ ] Optimize schema introspection queries
- [ ] Add schema test isolation (fresh DB per test)
