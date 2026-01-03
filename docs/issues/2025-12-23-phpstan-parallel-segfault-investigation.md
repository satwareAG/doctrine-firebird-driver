# PHPStan SIGSEGV Investigation (RESOLVED)

**Date**: 2025-12-23 → 2026-01-03
**Status**: ✅ **RESOLVED** in php-firebird v7.0.0-rc.37
**GitHub Issue**: [#55](https://github.com/satwareAG/php-firebird/issues/55) (CLOSED)

---

## Summary

PHPStan crashed with `exit code 139` (SIGSEGV) when the php-firebird extension was loaded, even without any actual Firebird database connections.

**Root Cause**: Invalid `IS_RESOURCE` type hints in php-firebird's arginfo. PHP 8.x cannot represent resource types in reflection APIs, causing `zend_type_to_string()` to return NULL.

**Fix**: v7.0.0-rc.37 removed all `IS_RESOURCE` type hints from arginfo declarations.

---

## Error Signature

```
Child process error (exit code 139): Segmentation fault (core dumped)
while running parallel worker
```

**SIGSEGV Details**: `si_addr=0x4` (NULL pointer + 4 byte struct offset)

---

## Root Cause Analysis

### GDB Disassembly (Crash Point)

```asm
call   <zend_type_to_string>     ; Returns NULL for IS_RESOURCE
mov    %rax,(%rbx)               ; Store NULL in memory
mov    0x4(%rax),%eax            ; CRASH: rax=0, accesses 0x4!
```

### Technical Explanation

1. php-firebird used `ZEND_ARG_TYPE_INFO(0, param, IS_RESOURCE, ...)` in arginfo
2. `IS_RESOURCE` is not a valid type hint in PHP 8.x (resources aren't type-hintable)
3. When PHPStan used reflection APIs, `zend_type_to_string()` returned NULL
4. PHP code accessed `result->field` (at offset 4) without NULL check → SIGSEGV

### Isolation Proof

| Test Configuration | Result |
|-------------------|--------|
| PHP with ext-firebird | ❌ SIGSEGV |
| PHP without firebird (`-n`) | ✅ `[OK] No errors` |

---

## Fix Details

**Release**: v7.0.0-rc.37 (2026-01-03)
**Commit**: `589d24a` - `fix(arginfo): remove IS_RESOURCE type hints causing PHPStan SIGSEGV`

**Changed Functions** (IS_RESOURCE → untyped):
- `arginfo_fbird_close`
- `arginfo_fbird_connection_info`
- `arginfo_fbird_get_limbo_transactions`
- `arginfo_fbird_reconnect_transaction`
- `arginfo_fbird_batch_*` functions

---

## Verification Results

| Metric | Before (rc.36) | After (rc.37) |
|--------|---------------|---------------|
| PHPStan Exit Code | 139 (SIGSEGV) | **0 (success)** |
| PHPStan Analysis | N/A (crash) | 4 static analysis errors |
| Parallel Workers | ❌ Crash | ✅ Normal completion |

---

## Workaround (No Longer Needed)

The `maximumNumberOfProcesses: 1` in `phpstan.neon.dist` was a workaround that is **no longer required** with rc.37+, but can remain as a conservative default.

---

## Key Learnings

1. **Resource types are not type-hintable in PHP 8.x** - extensions must use untyped arginfo
2. **SIGSEGV at offset 0x4** typically indicates `NULL->field` access (struct field at byte 4)
3. **GDB batch mode** (`gdb -batch -ex 'run' -ex 'bt'`) is effective for debugging crashes
4. **strace** with `-e trace=signal` helps identify crash signatures

---

## References

- [PHPStan Parallel Processing](https://phpstan.org/config-reference#parallel-processing)
- [php-firebird Issue #55](https://github.com/satwareAG/php-firebird/issues/55)
- [Zend Type System](https://www.php.net/manual/en/internals2.ze1.zendapi.php)
