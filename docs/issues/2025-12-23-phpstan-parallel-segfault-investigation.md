# PHPStan Parallel Worker Segfault Investigation

**Date**: 2025-12-23
**Status**: Investigation Complete - GitHub Issue Created
**Priority**: Medium-High (affects flagship OSS project)
**Related Repository**: https://github.com/satwareAG/php-firebird
**GitHub Issue**: https://github.com/satwareAG/php-firebird/issues/22

---

## Summary

Investigation into whether the PHPStan segmentation fault (exit code 139) during Docker CQC testing indicates a hidden bug in the php-firebird extension (satwareAG/php-firebird).

**Conclusion**: **Yes, this likely indicates a fork-safety issue in php-firebird that should be reported.**

---

## Original Error

```
Error                                                                  
Child process error (exit code 139): Segmentation fault (core dumped)  
while running parallel worker                                         
```

**Workaround Applied**: `parallel: maximumNumberOfProcesses: 1` in `phpstan.neon.dist`

---

## Investigation Findings

### 1. PHPStan Parallel Workers and pcntl_fork

PHPStan uses `pcntl_fork()` to create child processes for parallel analysis. Common causes for exit code 139 include:

- **Infinite type recursion** (standard PHPStan issue)
- **Memory exhaustion** 
- **OPcache JIT issues** (PHP 8.1+)
- **Extension fork-safety issues** ← **Most likely in our case**

### 2. PHP Extension Fork Safety Issue

When `pcntl_fork()` is called:
1. Child process inherits all parent's memory, including loaded extension state
2. Extensions with global resources (database connections, handles) become **duplicated but invalid**
3. During child process shutdown (RSHUTDOWN/MSHUTDOWN), extensions attempt to cleanup **invalid copied resources**
4. This causes **use-after-free** or **double-free** patterns → SIGSEGV

**Critical Point**: The php-firebird extension is **loaded at PHP startup** (MINIT), even if no actual Firebird connections are made during PHPStan analysis. The extension's global state initialization can cause issues during fork.

### 3. php-firebird Known Segfault Issues

The php-firebird repository already has **multiple documented segfault issues**:

| Issue | Description | Status |
|-------|-------------|--------|
| #13 | Segfault in transaction cleanup after DDL commit | CLOSED (XFAIL) |
| #12 | Segfault in large BLOB fetch after commit | CLOSED (XFAIL) |
| #10 | XFAIL test - blob_stream_chunked_write segfault | CLOSED |
| #9 | XFAIL test - migration_001 segfault | CLOSED |

**Common root cause**: Use-after-free patterns in resource lifecycle management.

### 4. php-firebird Thread Safety Implementation

From DeepWiki analysis of satwareAG/php-firebird:

- Extension supports ZTS (Zend Thread Safety) builds
- Uses TSRM for per-thread globals (`ZEND_TSRMLS_CACHE_DEFINE()`)
- `ibase_globals` structure holds per-thread state (error messages, connection counters)
- **Known issues with resource cleanup when object destruction order is not controlled**
- Race conditions possible in complex object graphs

**Specific concern for fork()**: 
- `le_link`, `le_plink`, `le_trans` are "true globals" (resource type identifiers)
- After fork, child process may have stale references to parent's Firebird client handles
- libfbclient state may be invalid in forked process

### 5. Firebird Client Library (libfbclient) Fork Safety

Firebird client library has known limitations with fork():
- Connection handles become invalid after fork
- Must re-establish connections in child processes
- Cleanup of inherited handles can cause crashes

---

## Differentiation from Existing Issues

| Aspect | Existing Issues (#9, #10, #12, #13) | PHPStan Parallel Issue |
|--------|-------------------------------------|------------------------|
| **Trigger** | Active database operations | Extension loaded, no DB ops |
| **Context** | Transaction commit/cleanup | Process fork (pcntl_fork) |
| **Reproduction** | Specific BLOB/DDL operations | PHPStan parallel workers |
| **Root Cause** | Use-after-free in active resources | Fork-safety of global state |

**This is a NEW class of bug** - not duplicate of existing issues.

---

## Recommended GitHub Issue Content

### Title
```
Bug: Segmentation fault when extension is loaded in forked process (pcntl_fork / PHPStan parallel)
```

### Description
```markdown
## Summary
Segmentation fault (exit code 139) occurs when php-firebird extension is loaded
in a PHP process that uses pcntl_fork() for parallel processing, even when no
actual Firebird database operations are performed.

## Environment
- PHP: 8.1-cli (Docker)
- php-firebird: v7.0.0-rc.3
- Trigger: PHPStan with parallel workers enabled
- OS: Debian Bookworm (Docker container)

## Reproduction Steps
1. Install php-firebird extension
2. Run PHPStan with default parallel processing (maximumNumberOfProcesses > 1)
3. Observe child process segfault

## Error Message
```
Child process error (exit code 139): Segmentation fault (core dumped)
while running parallel worker
```

## Workaround
Disable parallel processing in phpstan.neon:
```neon
parameters:
    parallel:
        maximumNumberOfProcesses: 1
```

## Technical Analysis
PHPStan uses pcntl_fork() to create parallel worker processes. After fork:
1. Child processes inherit php-firebird's initialized global state
2. During child RSHUTDOWN/MSHUTDOWN, extension attempts cleanup
3. Cleanup involves invalid/stale handles from parent process
4. Results in SIGSEGV (use-after-free or double-free)

## Suggested Investigation Areas
- `PHP_MINIT_FUNCTION(ibase)` global initialization
- `PHP_MSHUTDOWN_FUNCTION(ibase)` cleanup of global resources
- `PHP_RSHUTDOWN_FUNCTION(ibase)` request shutdown handling
- libfbclient handle validity after fork
- Resource type destructors for `le_link`, `le_plink`, `le_trans`

## Related Issues
- #13: Transaction cleanup segfault (different trigger, similar root cause class)
- #12: BLOB fetch segfault (different trigger, similar root cause class)

## Impact
- Affects any PHP tool using pcntl_fork with php-firebird loaded
- Includes: PHPStan, PHPUnit (parallel), Infection, custom worker pools
- Workaround available but reduces performance

## Priority
Medium-High: Affects usability in modern PHP development workflows
```

---

## Reproduction Test Plan

To reliably reproduce and debug:

### Step 1: Enable Core Dumps
```bash
# In Docker container
ulimit -c unlimited
echo "/tmp/core.%e.%p" > /proc/sys/kernel/core_pattern
```

### Step 2: Re-enable Parallel Processing
```neon
# phpstan.neon.dist
parameters:
    parallel:
        maximumNumberOfProcesses: 4  # Re-enable
```

### Step 3: Run PHPStan with Debug
```bash
docker compose -f tests/docker-compose.yml run --rm app \
    vendor/bin/phpstan analyse --debug -vvv
```

### Step 4: Analyze Core Dump
```bash
# Install gdb
apt-get install gdb

# Analyze
gdb /usr/local/bin/php /tmp/core.php.*
(gdb) bt full
```

### Expected GDB Output
Will likely show crash in one of:
- `php_ibase_link_rsrc_dtor`
- `php_ibase_trans_rsrc_dtor`
- `isc_detach_database`
- `_efree` / `_zend_mm_alloc_*` (memory corruption)

---

## Recommended Actions

### Immediate (doctrine-firebird-driver)
- [x] Keep workaround in phpstan.neon.dist
- [x] Document the issue (this file)
- [ ] Add comment explaining workaround reason

### Short-term (php-firebird)
- [x] Create GitHub issue with reproduction steps (#22)
- [ ] Add fork-safety tests to php-firebird test suite
- [ ] Investigate MINIT/MSHUTDOWN global state handling

### Long-term (php-firebird)
- [ ] Implement fork-safe initialization pattern
- [ ] Consider `pcntl_atfork()` handler for cleanup coordination
- [ ] Add documentation about fork-safety limitations
- [ ] Test with common PHP tools (PHPStan, PHPUnit parallel, etc.)

---

## Files Modified

| File | Change |
|------|--------|
| `phpstan.neon.dist` | Added `parallel: maximumNumberOfProcesses: 1` workaround |
| `docs/issues/2025-12-23-phpstan-parallel-segfault-investigation.md` | This investigation |

---

## Update: 2025-12-27 - Segfault Persists in Single Process Mode

**Status Update**: The segfault issue has escalated. It now occurs even with `maximumNumberOfProcesses: 1` (single process mode), indicating that the issue is not strictly limited to `pcntl_fork()` but involves a deeper Use-After-Free (UAF) bug in the php-firebird extension's persistent connection handling.

**Symptoms**:
- PHPStan reports "PASSED" in CQC pipeline but exits prematurely or with hidden errors.
- Segfaults observed in logs even without parallel workers.
- Root cause identified as UAF in `php_ibase_pconnect` resource management.

**Fix in Progress**:
- A fix is being implemented in the `php-firebird` repository (v7.0.0-rc.12 target).
- Strategy: Fix UAF in persistent connection list traversal and cleanup.
- Reference: `/home/mw/CLionProjects/php-firebird/docs/planning/UAF_FIX_NEXT_STEPS.md`

**Impact on CQC**:
- The "PASSED" status for PHPStan in `tests/docker-cqc.sh` may be misleading until the extension is updated.
- We will continue to run PHPStan but acknowledge the potential for incomplete analysis until the underlying extension bug is resolved.

---

## References

1. [PHPStan Parallel Processing](https://phpstan.org/config-reference#parallel-processing)
2. [PHP pcntl_fork Documentation](https://www.php.net/manual/en/function.pcntl-fork.php)
3. [Zend Thread Safety (ZTS)](https://www.php.net/manual/en/internals2.ze1.zendapi.php)
4. [satwareAG/php-firebird Issues](https://github.com/satwareAG/php-firebird/issues)
5. [Firebird Client Library Thread Safety](https://firebirdsql.org/file/documentation/chunk/en/refdocs/fblangref50/fblangref50-appx01-sqlstates.html)
