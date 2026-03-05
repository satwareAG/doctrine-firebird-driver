# SIGSEGV Isolation Test Findings

**Date:** 2026-01-02  
**Issue:** [satwareAG/php-firebird#54](https://github.com/satwareAG/php-firebird/issues/54)  
**php-firebird Version:** v7.0.0-rc.30 (built from Docker, labeled as rc.29 in script)

## Summary

Minimal test scripts successfully isolated the SIGSEGV root cause to persistent connections (`fbird_pconnect`) during PHP shutdown (MSHUTDOWN phase).

## Test Results

| Test Case | Exit Code | Result |
|-----------|-----------|--------|
| Regular Connection (Control) | 0 | ✓ Clean shutdown |
| Persistent Connection (Single) | 139 | ✗ **SIGSEGV** |
| Persistent Connection (Multiple) | 0 | ✓ Clean shutdown (unexpected!) |
| Persistent Connection (Transaction) | 139 | ✗ **SIGSEGV** |

## Key Observations

### 1. SIGSEGV Confirmed with Persistent Connections
- Non-persistent connections (`fbird_connect`) do NOT crash
- Single persistent connection (`fbird_pconnect`) DOES crash
- Persistent connection with explicit transaction DOES crash

### 2. Surprising: Multiple Persistent Connections Do NOT Crash
When calling `fbird_pconnect` multiple times with the same DSN, PHP returns the same pooled persistent connection. Interestingly, this scenario does NOT crash. This suggests:

- The crash may be timing/ordering related in MSHUTDOWN cleanup
- Multiple pconnect calls may trigger different resource tracking behavior
- The issue may be specific to how the first/single persistent connection is handled

### 3. Transaction Involvement
The "Persistent + Transaction" test also crashes, even though the transaction is properly committed before shutdown. This suggests the crash is purely related to persistent connection cleanup, not transaction state.

## Root Cause Hypothesis (from Issue #51)

```c
// In _php_fbird_close_plink() during MSHUTDOWN:
zend_hash_str_del(&EG(regular_list), ...);
zend_hash_str_del(&EG(persistent_list), ...);
```

These calls access executor globals (`EG(...)`) that may already be destroyed during the module shutdown phase. The Zend Engine destroys executor globals early in the shutdown sequence, but the Firebird extension's MSHUTDOWN handler still tries to access them.

## Files Created

Test scripts in `tests/debug/`:
- `run-sigsegv-tests.sh` - Test runner script
- `setup-test-database.php` - Database creation helper
- `test-connect-regular.php` - Control test (non-persistent)
- `test-pconnect-single.php` - Single persistent connection test
- `test-pconnect-multiple.php` - Multiple persistent connections test
- `test-pconnect-transaction.php` - Persistent connection with transaction test

## How to Reproduce

```bash
# From project root
./tests/debug/run-sigsegv-tests.sh
```

## Recommended Fix (for php-firebird)

The MSHUTDOWN handler should NOT attempt to access `EG(regular_list)` or `EG(persistent_list)`. Instead:

1. **Option A**: Clean up persistent connections in RSHUTDOWN (request shutdown) instead of MSHUTDOWN (module shutdown)
2. **Option B**: Check if executor globals are still valid before accessing them
3. **Option C**: Use a different cleanup mechanism that doesn't rely on executor globals

## Impact on Production

This SIGSEGV occurs **only during PHP process shutdown**. In production:

- **PHP-FPM**: Each request runs in a separate process; SIGSEGV at shutdown is logged but doesn't affect request completion
- **CLI scripts**: Script completes successfully; SIGSEGV occurs after output is sent
- **Long-running processes**: Only affects final cleanup; does not impact runtime

### CI/CD Workaround

The `tests/phpunit.sh` script already handles exit 139 gracefully by checking if PHPUnit reported success before the SIGSEGV occurred.

## References

- [satwareAG/php-firebird#50](https://github.com/satwareAG/php-firebird/issues/50) - Initial SIGSEGV report
- [satwareAG/php-firebird#51](https://github.com/satwareAG/php-firebird/issues/51) - Root cause analysis
- [satwareAG/php-firebird#54](https://github.com/satwareAG/php-firebird/issues/54) - v7.0.0-rc.29 persistence report
