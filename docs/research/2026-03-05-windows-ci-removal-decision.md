# Windows CI Removal Decision (SUPERSEDED)

**Date**: 2026-03-05
**Status**: Superseded by `2026-03-18-windows-ci-stabilization.md`
**Supersedes**: `2025-12-25-windows-ci-options-research.md`

> [!CAUTION]
> This decision was superseded on March 18, 2026. Windows CI has been restored using GitHub Actions
> and stabilized with a permissive `C:\firebird_tests` directory workaround.

## Decision

**Remove AppVeyor Windows CI entirely.** No Windows CI replacement is added.

## Rationale

### 1. ext-firebird v7.2.0 Has No Windows DLLs

The project requires `satwareAG/php-firebird` v7.2.0 (the `ext-firebird` PHP extension). As of March 2026, no pre-built Windows DLLs exist for this version. Building from source on Windows requires:

- Visual Studio 2019/2022 with C++ tools
- PHP SDK build environment
- Firebird client SDK for Windows
- Complex, unmaintained build pipeline

The old AppVeyor config used `FirebirdSQL/php-firebird` v3.0.1 (DLL: `php_8.1.0-interbase-3.0.1-win-x64-nts.dll`), which is **incompatible** with the current codebase's v7.x API.

### 2. AppVeyor Config Was Already Broken

The existing `.appveyor.yml` was failing on every PR:

- Used PHP 8.1 only (project now requires PHP 8.3+)
- Used ext-firebird v3.0.1 (incompatible with current v7.x API)
- Chocolatey Firebird 3.0.12 not available in registry
- PRs were merged despite AppVeyor failing (treated as non-blocking)

### 3. Doctrine Ecosystem Pattern

Doctrine DBAL itself does **not** run Windows CI. All database testing (including SQL Server) runs on Linux with Docker containers. This is the standard pattern across the PHP ecosystem for database drivers.

### 4. Linux CI Is Comprehensive

GitHub Actions Linux CI fully covers the matrix:

| Dimension | Values |
|-----------|--------|
| PHP | 8.3, 8.4 |
| Firebird | 3.0, 4.0, 5.0 |
| Checks | Unit + Functional + Static Analysis + Coverage |

All 10 checks pass reliably. The driver code is pure PHP - platform-specific behavior comes from the Firebird server and PHP extension, both of which behave identically across platforms at the protocol level.

### 5. No Windows DLL From satwareAG on the Horizon

Perplexity research (March 2026) confirms no Windows builds exist or are planned for `satwareAG/php-firebird` v7.x. Building and distributing Windows DLLs would require:

- A dedicated Windows build pipeline (GitHub Actions Windows + MSVC toolchain)
- Ongoing maintenance for each PHP version × debug/non-debug variant
- Firebird SDK distribution for Windows
- PECL-style packaging

This is out of scope for the current project.

## Options Evaluated

| Option | Verdict | Reason |
|--------|---------|--------|
| **Keep AppVeyor** | ❌ Rejected | Broken, outdated, incompatible with v7.x |
| **GitHub Actions Windows** | ❌ Rejected | No Windows DLLs for ext-firebird v7.x |
| **Azure Pipelines** | ❌ Rejected | Same DLL blocker applies |
| **Drop Windows CI** | ✅ Chosen | Follows Doctrine pattern; Linux CI is comprehensive |

## Files Removed

- `.appveyor.yml` - AppVeyor CI configuration
- `tests/phpunit-appveyor.xml` - AppVeyor-specific PHPUnit config

## Future Reconsideration

Revisit Windows CI if any of these conditions are met:

1. `satwareAG/php-firebird` publishes pre-built Windows DLLs for PHP 8.3/8.4
2. A community contributor volunteers to maintain a Windows CI build pipeline
3. Windows-specific bugs are reported that cannot be reproduced on Linux

## References

- Prior research: `docs/research/2025-12-25-windows-ci-options-research.md`
- Doctrine DBAL CI: No Windows runners (confirmed March 2026)
- ext-firebird releases: https://github.com/satwareAG/php-firebird/releases
- GitHub Actions Windows runners: https://docs.github.com/en/actions/using-github-hosted-runners
