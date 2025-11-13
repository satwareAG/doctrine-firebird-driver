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
  - Validated across Firebird 2.5, 3.0, 4.0, and 5.0 - consistent behavior (24/24 tests passed)
  - **Performance Impact**: CAST prevents index usage, requires full table scan
  - **Mitigation**: For performance-critical queries, filter by indexed columns first or validate parameters at application level
  - See: https://github.com/satwareAG/doctrine-firebird-driver/issues/16

### Added
- **Configurable LIKE CAST Length** (`firebird.like_cast_length` parameter)
  - Allows customization of VARCHAR length used in LIKE column CAST operations
  - Default: 255 (backward compatible, zero breaking changes)
  - Range: 1-8191 (Firebird VARCHAR maximum)
  - Configuration via connection parameters or Symfony YAML
  - Validation with actionable error messages for invalid values
  - **Performance Note**: Higher values enable longer parameter matching but maintain same index prevention as default
  - Components:
    - `FirebirdPlatformConfiguration`: Validation and storage class
    - `InvalidConfigurationException`: Type-safe error handling
    - Platform integration: `FirebirdPlatform::getLikeCastLength()`
    - Driver-based initialization for early availability (pre-SchemaManager)
  - See: `README.md` and `docs/firebird-like-best-practices.md` for configuration examples
- Comprehensive test suite for LIKE parameter length handling (`tests/Test/Functional/LikeParameterLengthTest.php`)
  - 6 tests covering basic LIKE, OR expressions, LIKE NOT, and CAST behavior
  - Validates fix across all supported Firebird versions (2.5, 4.0, 5.0)
- Functional tests for configurable CAST length (`tests/Test/Functional/ConfigurableLikeCastLengthTest.php`)
  - 6 tests validating different length configurations (100, 255, 500, 1000, 8191)
  - Tests invalid configurations with actionable error messages
- Unit tests for configuration infrastructure:
  - `FirebirdPlatformConfigurationTest`: Validation logic and defaults
  - `InvalidConfigurationExceptionTest`: Error message accuracy
  - `FirebirdPlatformIntegrationTest`: Platform method delegation
  - `FirebirdDriverConfigurationTest`: Driver initialization flow

[Unreleased]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.0...HEAD
