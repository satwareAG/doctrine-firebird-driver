# Segfault Investigation: fb::ServiceWrapper::detach()

**Date**: 2026-01-03
**Status**: Reported upstream
**Issue**: https://github.com/satwareAG/php-firebird/issues/56

## Summary

A segmentation fault occurs during PHP request shutdown after running the doctrine-firebird-driver test suite. The crash happens in the resource destructor when PHP is cleaning up Firebird resources.

## Investigation Steps

### 1. Initial Observation

During Docker CQC runs, all tests pass but exit code is 139 (128 + 11 = SIGSEGV):

```
Tests: 1570, Assertions: 3327, OK
bash: line 1: 7 Segmentation fault (core dumped) vendor/bin/phpunit
```

### 2. GDB Analysis

```bash
docker compose exec -T app bash -c 'gdb -batch -ex "handle SIGSEGV stop" \
  -ex "run vendor/bin/phpunit -c tests/phpunit.xml --no-coverage" \
  -ex "bt 50" -ex "info registers" --args php'
```

**Stack trace:**
```
Thread 1 "php" received signal SIGSEGV, Segmentation fault.
0x00007f19e9667647 in ?? () from firebird.so
#0  0x00007f19e9667647 in ?? () from firebird.so
#3  0x...in zend_shutdown_executor_values ()
#5  0x...in zend_deactivate ()
#6  0x...in php_request_shutdown ()
```

### 3. Symbol Resolution

Using objdump to find the function at crash offset 0x37647:

```bash
objdump -d firebird.so | grep -B100 "37647:" | grep "^\w\{8,\}" | tail -1
```

**Result:**
```
0000000000037600 <_ZN2fb14ServiceWrapper6detachEPl@@Base>:
```

Demangled: `fb::ServiceWrapper::detach(long*)`

### 4. Disassembly at Crash Point

```asm
   37640:   4c 8b 2d 61 39 01 00    mov    0x13961(%rip),%r13
   37647:   49 89 c4                mov    %rax,%r12        # CRASH
   3764a:   41 0f b6 45 00          movzbl 0x0(%r13),%eax
```

### 5. Register Analysis

| Register | Value | Interpretation |
|----------|-------|----------------|
| RBX | `0x6946656e69727463` | ASCII "ctriFind" - corrupted memory |
| RDI | `0x559d4c0fea00` | Firebird resource pointer |
| RBP | `0x0` | NULL base pointer |

## Root Cause

The `fb::ServiceWrapper::detach()` function is called during Zend's resource cleanup phase (`zend_shutdown_executor_values`). The corrupted RBX register suggests either:

1. **Use-after-free**: ServiceWrapper object already freed
2. **Double-free**: Destructor called twice
3. **Resource ordering**: Dependencies destroyed before dependents

## Impact

- **Test execution**: Not affected (segfault occurs AFTER tests complete)
- **Exit code**: Returns 139 instead of 0, breaking CI pipeline success detection
- **Data integrity**: No risk (crash happens during cleanup)

## Workaround

The shell scripts `docker-cqc.sh` and `cqc.sh` have been updated to properly detect test success before the segfault by using `${PIPESTATUS[0]}` to capture exit codes.

## References

- GDB Manual: https://sourceware.org/gdb/current/onlinedocs/gdb/
- PHP Resource Destructors: https://www.php.net/manual/en/internals2.structure.php
- Firebird OO API: https://firebirdsql.org/file/documentation/html/isql-objects.html
