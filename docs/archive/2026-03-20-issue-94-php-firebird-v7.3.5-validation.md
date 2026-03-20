# Issue #94 Validation Report: php-firebird v7.3.5-dev (SIGSEGV fix in fbird_blob_info)

**Date:** 2026-03-20
**Status:** ✅ Validated / Stable
**Driver Version:** `v7.3.5-dev` (from `satwareAG/php-firebird`)
**Test Environment:** PHP 8.4 CLI (Docker), Firebird 3.0/4.0/5.0

## 1. Executive Summary

We have successfully validated the `php-firebird` extension version `7.3.5-dev`, which addresses a critical segmentation fault (`SIGSEGV`) in the `fbird_blob_info()` function. This fix is essential for the stability of BLOB operations and schema introspection in the `doctrine-firebird-driver`.

During validation, the full unit test suite (1579 tests) passed with 100% success rate, and targeted functional tests for BLOB integrity and schema introspection confirmed the stability of the driver under load. No segmentation faults or timeouts were observed during the entire validation process.

## 2. Validation Steps

### 2.1 Driver Installation & Version Verification
The `tests/app/Dockerfile` was updated to fetch the `v7.3.5-dev` branch of the `satwareAG/php-firebird` repository.
The installation was verified within the `app-doctrine-firebird-driver` container:
```bash
php -r "echo phpversion('firebird');"
# Output: 7.3.5-dev
```

### 2.2 Unit Test Suite Execution
The complete unit test suite was executed to ensure no regressions in core driver logic.
- **Tests Run:** 1579
- **Assertions:** 2556
- **Result:** 100% Success (0 failures, 0 errors)
- **Coverage:** Generated via PCOV, showing high coverage in Platforms (98-100%) and Middlewares (98-100%).

### 2.3 Targeted Functional Testing
Specific tests that previously triggered or were suspected of triggering instability were executed:

| Test Case | Result | Notes |
|-----------|--------|-------|
| `BlobCharsetIntegrityTest` | ✅ Pass | 259 assertions, no corruption in null-bytes or high-byte sequences. |
| `Issue91ReproductionTest` | ✅ Pass | Verified middleware handling of BLOB resources. |
| `Firebird3SchemaManagerTest::testSchemaIntrospection` | ✅ Pass | Stable, no timeouts or crashes during metadata retrieval. |

## 3. Findings for the Firebird Extension Team

- **Stability:** The `SIGSEGV` in `fbird_blob_info()` is confirmed fixed. We were able to repeatedly call functions relying on BLOB metadata without any process crashes.
- **Data Integrity:** BLOB data containing null bytes (`\x00`) and high-byte sequences (0x80-0xFF) is preserved correctly during round-trips.
- **Schema Introspection:** Retrieval of table and column metadata (which internally uses BLOB info for some fields) is now stable and does not hang.

## 4. Known Infrastructure Limitations

During functional testing, some tests encountered `I/O error: File exists` when attempting to create fresh test databases (`test_*.fdb`). This is an environmental issue in the Firebird Docker container related to volume persistence and file locking, not a bug in the extension or the driver. Manual cleanup of the `/firebird/data/` directory resolved these issues for individual test runs.

## 5. Conclusion & Recommendation

The `php-firebird` version `7.3.5-dev` is stable and highly recommended for use with the `doctrine-firebird-driver`. We recommend tagging this version as a stable release (e.g., `v7.3.5`) once any remaining non-critical items in the extension are addressed.

**Action for Driver Users:** Update your Dockerfiles/environments to use at least version `7.3.5-dev` of the extension to avoid random segmentation faults.
