# PHP Version Management in CI

## Issue Summary

**Date**: 2025-12-24
**Issue**: CI failures due to adding unreleased PHP version to test matrix
**Resolution**: Verification procedure established, PHP 8.5 now available

## Background

On December 2024, PHP 8.5 was prematurely added to the CI matrix before Docker Hub images were available. This caused CI failures because `php:8.5-cli-bookworm` did not exist on Docker Hub at that time.

### Root Cause

- PHP 8.5 was added to CI matrix without verifying Docker Hub image availability
- Docker Hub PHP images are published AFTER General Availability (GA) release
- The CI workflow uses `php:X.Y-cli-bookworm` container images

### Resolution

As of December 24, 2025:
- PHP 8.5 was officially released November 20, 2025
- Docker Hub image `php:8.5-cli-bookworm` is now available
- PHP 8.5 has been added back to the CI matrix

## PHP Version Verification Procedure

**Before adding ANY new PHP version to the CI matrix, ALWAYS verify:**

### Step 1: Check Official Release Status

```bash
# Visit php.net/supported-versions.php
# Verify the version shows as "Released" (not alpha/beta/RC)
```

### Step 2: Verify Docker Image Availability

```bash
# Fast check - inspect manifest (doesn't download full image)
docker manifest inspect php:X.Y-cli-bookworm

# Alternative - attempt pull (downloads image)
docker pull php:X.Y-cli-bookworm
```

### Step 3: Local Testing (Optional but Recommended)

```bash
# Test GitHub Actions locally with act
cd /path/to/doctrine-firebird-driver
act -j test --matrix php-version:8.5 --matrix firebird-version:3.0
```

## PHP Release Schedule Reference

PHP follows an annual release cycle with November releases:

| Version | Release Date | Active Support Until | Security Support Until |
|---------|--------------|---------------------|----------------------|
| PHP 8.1 | Nov 2021 | Nov 2023 | **Dec 2025 (EOL)** |
| PHP 8.2 | Dec 2022 | Dec 2024 | Dec 2026 |
| PHP 8.3 | Nov 2023 | Dec 2025 | Dec 2027 |
| PHP 8.4 | Nov 2024 | Dec 2026 | Dec 2028 |
| PHP 8.5 | Nov 2025 | Dec 2027 | Dec 2029 |
| PHP 8.6 | Expected Nov 2026 | - | - |

**Source**: https://www.php.net/supported-versions.php

## CI Matrix Configuration

The CI workflow at `.github/workflows/ci.yml` has comprehensive documentation. Key sections:

```yaml
# PHP VERSION MANAGEMENT - READ BEFORE MODIFYING MATRIX
# Before adding a PHP version to the matrix, VERIFY availability:
#
# 1. Check php.net/supported-versions.php for release status
# 2. Verify Docker image exists: docker manifest inspect php:X.Y-cli-bookworm
# 3. Test locally with: docker pull php:X.Y-cli-bookworm
```

## End-of-Life Planning

### PHP 8.1 EOL: December 31, 2025

PHP 8.1 reaches end-of-life on December 31, 2025. Plan to:

1. Remove PHP 8.1 from CI matrix in early January 2026
2. Update `composer.json` minimum PHP requirement to 8.2
3. Document migration notes for users still on PHP 8.1

## Lessons Learned

1. **Always verify Docker image availability** before adding PHP versions
2. **Docker Hub images lag GA releases** - may take days/weeks after PHP release
3. **Add documentation comments** in CI config explaining version status
4. **Use `docker manifest inspect`** for fast verification without downloading

## Related Files

- `.github/workflows/ci.yml` - CI workflow with PHP matrix
- `composer.json` - PHP version requirements
- `docs/TESTING.md` - Testing documentation

## References

- [PHP Supported Versions](https://www.php.net/supported-versions.php)
- [PHP End of Life Versions](https://www.php.net/eol.php)
- [Docker Hub PHP Images](https://hub.docker.com/_/php)
- [GitHub Actions: act](https://github.com/nektos/act)
