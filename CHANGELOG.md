# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **Issue #16: LIKE Expression Silent Failures with Oversized Parameters**
  - Fixed silent query failures when LIKE parameters exceed VARCHAR field length
  - Automatically wraps LIKE column operands in `CAST(column AS VARCHAR(255))`
  - Prevents Firebird from inferring parameter type from column definition
  - Resolves issue where entire query returns empty result set instead of matching other OR conditions
  - Validated across Firebird 2.5, 4.0, and 5.0 - consistent behavior
  - **Performance Impact**: CAST prevents index usage, requires full table scan
  - **Mitigation**: For performance-critical queries, filter by indexed columns first or validate parameters at application level
  - See: https://github.com/satwareAG/doctrine-firebird-driver/issues/16

### Added
- Comprehensive test suite for LIKE parameter length handling (`tests/Test/Functional/LikeParameterLengthTest.php`)
  - 6 tests covering basic LIKE, OR expressions, LIKE NOT, and CAST behavior
  - Validates fix across all supported Firebird versions (2.5, 4.0, 5.0)

[Unreleased]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.0...HEAD
