# Windows CI Stabilization

**Date**: 2026-03-18
**Status**: Implemented
**Supersedes**: `2026-03-05-windows-ci-removal-decision.md`

## Problem Statement

Windows CI on GitHub Actions was failing with persistent "I/O error" and "Database file not found" errors during the `initializeDatabase` phase of tests.

### Root Causes

1.  **Firebird Service Permissions**: The Firebird service runs as the `SYSTEM` account. On GitHub Actions Windows runners, the `SYSTEM` account has restricted access to the `D:\` drive (where the workspace is located). Any attempt to create a database file in `D:\a\repo\repo\tests\var\test.fdb` resulted in permission denied errors.
2.  **Path Resolution Bypass**: While `TestUtil.php` had a workaround to move database files to `C:\firebird_tests` on Windows CI, it only triggered if the database name contained no slashes or backslashes. The CI configuration was providing an absolute path with backslashes, bypassing this logic.

## Solution

### 1. Dedicated Permissive Directory

A dedicated directory `C:\firebird_tests` is created during the CI setup. It is explicitly granted full permissions for `Everyone`:

```powershell
if (-not (Test-Path "C:\firebird_tests")) { New-Item -Path "C:\firebird_tests" -ItemType Directory }
icacls "C:\firebird_tests" /grant "Everyone:(OI)(CI)F" /T /C
```

### 2. Resilient Path Logic in `TestUtil.php`

The path resolution in `Satag\DoctrineFirebirdDriver\Test\TestUtil` was refined to force the use of `C:\firebird_tests` on Windows CI, regardless of what is provided in the `DB_DBNAME` environment variable.

```php
if (PHP_OS_FAMILY === 'Windows' && getenv('CI')) {
    $tempDir = 'C:\\firebird_tests';
    if (! str_starts_with($baseName, $tempDir)) {
        // Strip any path and force into tempDir
        $baseName = basename(str_replace('\\', '/', $baseName));
        $baseName = $tempDir . '\\' . $baseName;
    }
    // ...
}
```

### 3. Simplified CI Configuration

The `.github/workflows/windows.yml` was updated to set `DB_DBNAME` to a simple filename (`test.fdb`), which is both cleaner and easier for `TestUtil.php` to handle.

## Benefits

-   **Reliable I/O**: Firebird `SYSTEM` account can reliably read/write to `C:\firebird_tests`.
-   **No Permissions Drama**: Avoids complex `D:\` drive icacls manipulation.
-   **Improved Portability**: The logic handles both absolute and relative paths gracefully.

## Verification

The Windows CI pipeline now successfully creates databases and runs the integration test suite.

## References

-   Issue: Persistent I/O errors on Windows CI
-   PR: Windows CI Stabilization (#98)
-   Workaround logic: `tests/Test/TestUtil.php`
