# CI Mitigation Strategies for PHP-Firebird

**Date**: 2026-03-09
**Status**: Implemented
**Context**: Fixing widespread CI failures in `doctrine-firebird-driver` after the v7.3.0 release.

## 1. IBatch API Build Discipline

**Issue**: The PHP `firebird` extension defaults to Firebird 3.0 compatibility if `FB_API_VER` is not specified during compilation.
Testing against Firebird 4.0/5.0 with `IBatch` enabled causes `RuntimeException` if the extension was not built with `FB_API_VER >= 40`.

**Mitigation**: Always specify `FB_API_VER` during the `phpize` build process.
```bash
export CPPFLAGS="-DFB_API_VER=40"
phpize
./configure --with-php-config=$(which php-config)
make
```
This ensures the extension supports advanced Firebird 4.0+ features like `IBatch`.

## 2. Handling Persistent Segmentation Faults

**Issue**: `php-firebird` (v7.x) has known issues with persistent connection cleanup during process shutdown, leading to `SIGSEGV` (exit 139) or `SIGABRT` (exit 134) after PHPUnit has already successfully completed its tests.

**Mitigation**: Use a wrapper script or CI logic that parses PHPUnit output. If the output confirms tests passed ("OK"), treat shutdown signals as non-failing.
```bash
# Example logic in ci.yml or phpunit.sh
if [ $EXIT_CODE -eq 139 ] || [ $EXIT_CODE -eq 134 ]; then
    if echo "$OUTPUT" | grep -qE '^OK|Tests: [0-9]+.*Assertions: [0-9]+' && \
       ! echo "$OUTPUT" | grep -qE '^FAILURES|^ERRORS'; then
        echo "✅ Recognized shutdown crash (Tests Passed)"
        exit 0
    fi
fi
```

## 3. Firebird 3.0 DDL Auto-Commit

**Issue**: Firebird 3.0 maintains strict locks on system tables during DDL operations. If DDL statements (like `CREATE TABLE`) are executed via a `Statement` object that doesn't trigger an immediate auto-commit, subsequent tests might hang or fail due to metadata locks.

**Mitigation**: The driver must recognize DDL keywords and treat them as DML for auto-commit purposes.
Updated Keywords: `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `DROP`, `RECREATE`.

## 4. Windows DLL Search in GHA

**Issue**: Pre-built Windows DLLs in GitHub Releases often have versioned names (e.g., `php_firebird-v7.3.0-8.4-nts-vs17-x86_64.dll`). Strict filters for `php_firebird.dll` fail to find them.

**Mitigation**: Use wildcard searches (`php_firebird*.dll`) and rename the result to the standard `php_firebird.dll` during installation.

## 5. CI Observability

**Mitigation**: Always upload debug artifacts (PHP info, module list, raw test logs) for failed runs to avoid "flying blind" when investigating environment-specific issues.
