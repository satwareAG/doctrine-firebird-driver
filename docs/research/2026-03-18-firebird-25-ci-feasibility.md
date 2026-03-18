# Research: Firebird 2.5 CI Feasibility

**Date**: 2026-03-18
**Status**: Completed (Not Feasible)

## Objective
Research the feasibility of adding a Firebird 2.5 container to the GitHub Actions matrix to achieve complete version coverage in CI.

## Findings

### 1. Extension Limitation
The primary blocker is the `php-firebird` extension. Starting with version **v7.2.0**, the extension officially dropped support for Firebird 2.5 server. Since the project now requires `php-firebird: ^7.3.0` for stability and PHP 8.4 support, it is not possible to connect to a Firebird 2.5 server using the required extension version.

### 2. Client Library Compatibility
While newer `libfbclient` versions can sometimes talk to older servers, the PHP extension's internal API usage has moved to features only available in Firebird 3.0+ (and the extension's own OO API).

### 3. CI Environment
Running an older version of the extension in CI just for Firebird 2.5 would require:
- A separate job with an older PHP version (e.g., PHP 8.1).
- Building an older version of the extension (v7.1.0 or earlier).
- Maintaining a different codebase path for these tests, as many new features (IBatch, etc.) would need to be guarded or disabled.

## Conclusion
Adding Firebird 2.5 to the CI matrix is **not feasible** while maintaining alignment with the latest `php-firebird` extension and PHP 8.4+ standards.

## Recommendation
Firebird 2.5 support remains available for **local testing only** using legacy environments and is documented as such in `docs/TESTING.md` and `CHANGELOG.md`. Any future legacy support would require a dedicated maintenance branch with downgraded dependencies.
