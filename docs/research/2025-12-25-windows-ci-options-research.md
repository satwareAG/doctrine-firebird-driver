# Windows CI Testing Options Research

**Date**: 2025-12-25
**Status**: Research Complete
**Author**: AI Assistant

## Executive Summary

This document evaluates Windows CI/CD options for doctrine-firebird-driver, comparing the current AppVeyor setup with GitHub Actions alternatives and examining how major PHP/Doctrine projects handle Windows testing.

**Key Finding**: Doctrine DBAL and most major PHP open source projects do **NOT** run Windows CI tests. They rely on Linux-based Docker containers for all database testing. The primary blocker for Windows CI for this project is the lack of pre-built php-firebird Windows DLLs for PHP 8.3+.

---

## Current State Analysis

### Current AppVeyor Configuration

The project uses AppVeyor for Windows CI (`.appveyor.yml`):

| Component | Version | Notes |
|-----------|---------|-------|
| **PHP** | 8.1 | Only single version tested |
| **Firebird** | 3.0.12 | Via Chocolatey |
| **Extension** | php-firebird v3.0.1 | Pre-built Windows DLL |
| **Runner** | Windows x64 | Hosted |

**AppVeyor Strengths**:
- Currently working configuration
- Free for open source projects
- Pre-built Windows DLLs available from php-firebird v3.0.1

**AppVeyor Limitations**:
- Only tests PHP 8.1 (outdated)
- Only tests Firebird 3.0.x (Firebird 5.x not tested)
- Separate CI system from GitHub Actions (fragmented)
- Limited visibility in GitHub PR checks

### Current GitHub Actions Configuration

The project runs comprehensive Linux CI (`.github/workflows/ci.yml`):

| Component | Versions | Notes |
|-----------|----------|-------|
| **PHP** | 8.1, 8.2, 8.3, 8.4 | Full matrix |
| **Firebird** | 3.0, 4.0, 5.0 | Docker containers |
| **Extension** | php-firebird v7.0.0-rc.6 | Built from source |
| **Runner** | ubuntu-latest | Linux only |

---

## Research Findings

### 1. Doctrine Projects' CI Strategy

**Doctrine DBAL** (`doctrine/dbal`):
- **Does NOT use Windows runners** at all
- Even SQL Server testing runs on **Linux** with Microsoft SQL Server container
- All testing is Docker-based on `ubuntu-22.04` / `ubuntu-24.04`

Relevant Doctrine DBAL workflows:
- `phpunit-sqlite.yml` - ubuntu-24.04
- `phpunit-mysql.yml` - ubuntu with MySQL container
- `phpunit-postgres.yml` - ubuntu with PostgreSQL container
- `phpunit-sqlserver.yml` - ubuntu with `mcr.microsoft.com/mssql/server:2022-latest`
- `phpunit-oracle.yml` - ubuntu with Oracle container

**Key Insight**: Doctrine validates Windows compatibility through code patterns and PHP version testing, not Windows-specific CI runs.

### 2. php-firebird Windows DLL Availability

| Version | Source | PHP Windows DLLs Available |
|---------|--------|---------------------------|
| **v3.0.1** | FirebirdSQL/php-firebird | PHP 8.0, 8.1, 8.2 (nts + ts) |
| **v7.0.0-rc.6** | satwareAG/php-firebird | **None** |
| **v7.0.0-rc.7** | satwareAG/php-firebird | **None** |

**Critical Issue**: The php-firebird v7.x releases from satwareAG do not include pre-built Windows DLLs. Building the extension from source on Windows requires:
- Visual Studio 2019/2022 with C++ tools
- PHP SDK
- Firebird client libraries
- Complex build process

### 3. Chocolatey Firebird Availability

| Version | Status | Notes |
|---------|--------|-------|
| 3.0.4 | ✅ Available | Older, but stable |
| 3.0.12 | ❌ Not available | Used in AppVeyor but not in Chocolatey |
| 4.0.6 | ✅ Available | Current 4.x release |
| 5.0.0 - 5.0.3 | ✅ Available | Current stable releases |

### 4. GitHub Actions Windows Runner Options

**Free Tier for Public Repositories**:
- `windows-latest` (Windows Server 2022)
- `windows-2022`
- `windows-2025` (preview)
- 2,000 free minutes/month (Windows minutes count as 2x Linux)

**Key Actions for Windows PHP CI**:
- `shivammathur/setup-php@v2` - Works on Windows, can install extensions
- `actions/cache@v4` - Composer caching
- `php-actions/phpunit` - PHPUnit execution

---

## Options Analysis

### Option A: Keep AppVeyor (Status Quo)

**Pros**:
- Currently working
- No migration effort
- Free for open source

**Cons**:
- Only PHP 8.1 tested (8.2 possible with v3.0.1 DLL)
- PHP 8.3/8.4 not possible without building extension
- Firebird version limited to 3.x
- Fragmented CI (two systems)
- No php-firebird v7.x features on Windows

**Recommendation**: ⚠️ Maintenance mode only

### Option B: Migrate to GitHub Actions Windows

**Pros**:
- Unified CI platform
- Better GitHub integration
- Same free tier benefits
- Could potentially test Firebird 4.x/5.x via Chocolatey

**Cons**:
- Same php-firebird DLL limitation (no PHP 8.3+)
- Would need to build extension from source or wait for Windows builds
- More complex setup than Linux
- Windows minutes count double

**Estimated Workflow**:
```yaml
windows-test:
  runs-on: windows-latest
  steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: pdo, mbstring
    - name: Install Firebird via Chocolatey
      run: choco install firebird --version=5.0.3
    - name: Download php-firebird DLL
      run: |
        Invoke-WebRequest -Uri "https://github.com/FirebirdSQL/php-firebird/releases/download/v3.0.1/php_8.2.0-interbase-3.0.1-win-x64-nts.dll" -OutFile "ext/php_firebird.dll"
    - name: Run Tests
      run: vendor/bin/phpunit
```

**Recommendation**: ⚠️ Possible but limited

### Option C: Drop Windows CI (Follow Doctrine Pattern)

**Pros**:
- Follows established PHP ecosystem patterns
- Simplifies CI maintenance
- Full PHP version matrix on Linux
- Full Firebird version matrix with Docker
- php-firebird v7.x features available
- No Windows DLL dependency

**Cons**:
- No Windows-specific testing
- Potential Windows compatibility regressions (unlikely for pure PHP)
- May concern Windows users contributing

**Rationale**:
- The project is pure PHP code (no native Windows dependencies)
- Firebird protocol is platform-agnostic
- PHP extension behavior differences are minimal
- Docker-based testing is more comprehensive and maintainable

**Recommendation**: ✅ **Recommended approach**

### Option D: Hybrid Approach

**Strategy**: Linux for comprehensive testing + minimal Windows smoke test

**Linux CI** (comprehensive):
- Full PHP matrix (8.1, 8.2, 8.3, 8.4)
- Full Firebird matrix (3.0, 4.0, 5.0)
- Full test suite with coverage

**Windows CI** (smoke test only):
- PHP 8.2 only (DLL available)
- Firebird 3.0 or 4.0
- Basic connectivity test (not full suite)
- Run weekly or on release branches only

**Pros**:
- Best of both worlds
- Validates Windows-specific issues
- Minimal maintenance overhead

**Cons**:
- Still requires maintaining Windows workflow
- Limited Windows coverage

**Recommendation**: ⚠️ Acceptable compromise

---

## Recommendations

### Primary Recommendation: Option C (Drop Windows CI)

Follow the Doctrine ecosystem pattern:

1. **Remove AppVeyor** - Consolidate on GitHub Actions
2. **Enhance Linux CI** - Already comprehensive with PHP 8.1-8.4 + Firebird 3/4/5
3. **Document Windows installation** - Provide clear instructions for Windows users
4. **Monitor community feedback** - Re-evaluate if Windows issues become frequent

### Alternative: Option D (Hybrid)

If Windows testing is deemed necessary:

1. **Migrate AppVeyor to GitHub Actions** - Unified platform
2. **Limit scope** - PHP 8.2, Firebird 4.0, weekly runs
3. **Use existing DLLs** - php-firebird v3.0.1 for basic validation
4. **Accept limitations** - Not full coverage, just compatibility check

---

## Free CI Options Summary for Open Source

| Platform | Windows Support | Free Tier | Notes |
|----------|-----------------|-----------|-------|
| **GitHub Actions** | ✅ windows-* runners | 2000 min/mo (2x rate) | Best GitHub integration |
| **AppVeyor** | ✅ Native Windows | Unlimited for OSS | Windows-focused, separate UI |
| **Azure Pipelines** | ✅ vmImage: windows-* | 1800 min/mo | Microsoft ecosystem |
| **CircleCI** | ✅ windows orb | 400 min/mo | Complex Windows setup |
| **Travis CI** | ⚠️ Limited | OSS tier available | Deprecated focus |
| **GitLab CI** | ⚠️ Self-hosted only | N/A for Windows | Linux-focused |

**For satwareAG/doctrine-firebird-driver**: GitHub Actions is the recommended platform if Windows testing is desired, but the php-firebird DLL limitation affects all platforms equally.

---

## Action Items

1. **Decision Required**: Choose Option C (drop Windows) or Option D (hybrid)
2. **If keeping Windows CI**:
   - Consider building php-firebird Windows DLLs for PHP 8.3/8.4
   - Update AppVeyor or create GitHub Actions Windows workflow
3. **If dropping Windows CI**:
   - Remove `.appveyor.yml`
   - Update README with Windows installation guidance
   - Add note in CHANGELOG about Windows testing changes

---

## References

- [GitHub Actions Windows Runners](https://docs.github.com/en/actions/using-github-hosted-runners/about-github-hosted-runners)
- [shivammathur/setup-php](https://github.com/shivammathur/setup-php)
- [Doctrine DBAL CI Workflows](https://github.com/doctrine/dbal/tree/4.2.x/.github/workflows)
- [Chocolatey Firebird Package](https://community.chocolatey.org/packages/firebird)
- [php-firebird Releases](https://github.com/FirebirdSQL/php-firebird/releases)
