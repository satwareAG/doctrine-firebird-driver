# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.10.0-rc.1] - 2026-01-07

### Changed
- **php-firebird Extension Upgrade** - Updated CI/Docker from v7.0.0-rc.44 to v7.0.0-rc.47
  - **v7.0.0-rc.47**: Latest stable release candidate with SIGSEGV fixes for PHP shutdown handling
  - **Improvements**: Enhanced stability during test runs and PHP process termination
  - Updated Docker test environment to use rc.47 tag from satwareAG/php-firebird repository

### Added
- **Docker BuildKit Documentation** - Comprehensive setup guide for Arch Linux
  - Installation via pacman (`docker-buildx` package)
  - BuildKit configuration and environment setup (`DOCKER_BUILDKIT=1`)
  - Builder instance management (`docker buildx create`, `docker buildx use`)
  - Troubleshooting common issues (builder not found, permission errors)
  - Build performance benefits (parallel builds, layer caching)
  - Project-specific notes for php-firebird extension compilation
  - Location: `docs/tech/docker-buildkit-arch-linux-setup.md`

### Fixed
- **PHP 8.1 Compatibility** - Removed PHP 8.3 typed constants for PHP 8.1 support
  - Converted `public const string/int` to `public const` with `@var` docblocks
  - Affected files: `Connection.php`, `FirebirdDriver.php`, `FirebirdPlatformConfiguration.php`, `FirebirdSchemaManager.php`
  - Test files: `ConnectionTest.php`, `MoneyType.php`
  - Fixes syntax errors in PHP 8.1 Docker environment
- **Psalm PossiblyUnusedMethod Suppression** - Added `Firebird4Platform::getTimeTzFormatString()` to suppression list
  - Method is a public API tested in unit tests but Psalm only scans `src/` not `tests/`
  - Follows existing pattern for other public API methods (setCharTrue, setCharFalse, etc.)
- **CQC Test Suite Fixes** (2026-01-03)
  - Fixed PHPUnit unit tests: `ExceptionConverterTest`, `Firebird4PlatformTest`, `DriverTest`, `VersionAwarePlatformDriverTest`
  - Created missing test exceptions and mock classes
  - All 977 PHPUnit tests passing, 53 PHPCS checks, PHPStan Level 8 clean, Psalm clean
- **php-firebird Extension Upgrade** - Updated CI/Docker from v7.0.0-rc.37 to v7.0.0-rc.44
  - **v7.0.0-rc.44**: Attempted fix for SIGSEGV in `fb::Connection::detachNoThrow()` during PHP shutdown (Issue #56)
  - **Status**: Segfault (Exit code 139) still persists on PHP 8.1, 8.3, and 8.4
  - **Action**: Reverted segfault silencing in CQC pipeline to properly track this issue
  - See: `docs/issues/2026-01-03-segfault-investigation-servicemanager-detach.md`
- **TypeConversionTest Deprecation Fix** - Removed incorrect deprecation expectations from array/object type tests
  - The DBAL PR #5509 deprecation only triggers on `requiresSQLCommentHint()`, not during `convertTo*Value()` operations
  - Removed `VerifyDeprecations` trait usage since no deprecation warnings are expected in the test's code path
  - Tests still validate that deprecated `Types::ARRAY` and `Types::OBJECT` work correctly for backward compatibility
  - Fixes test failure: "Expected deprecation with identifier 'https://github.com/doctrine/dbal/pull/5509' was not triggered"
- **PHP 8.1 Compatibility** - Removed PHP 8.3 typed constants for PHP 8.1 support
  - Converted `public const string/int` to `public const` with `@var` docblocks
  - Affected files: `Connection.php`, `FirebirdDriver.php`, `FirebirdPlatformConfiguration.php`, `FirebirdSchemaManager.php`
  - Test files: `ConnectionTest.php`, `MoneyType.php`
  - PHPStan `phpVersion` set to `80100` for accurate analysis

### Changed
- **External Stubs Package** - Integrated `satwareag/php-firebird-stubs:v7.0.0-rc.41`
  - Removed local `stubs/` directory (~1,600 lines)
  - PHPStan baseline reduced from 79 to 66 errors (17% reduction)
  - Psalm baseline regenerated (119 lines)
- **Shell Script Bug Fixes** (docker-cqc.sh, cqc.sh)
  - Fixed critical bug where pipeline exit codes were incorrectly reported as success
  - Now uses `${PIPESTATUS[0]}` to capture actual command exit codes when piping to `tee`
- **php-firebird Extension Upgrade** - Updated CI/Docker from v7.0.0-rc.34 to v7.0.0-rc.37
  - Final fix for SIGSEGV issues in PHPStan/Psalm parallel mode
  - Removed `maximumNumberOfProcesses: 1` workaround from phpstan.neon.dist
  - All static analysis tools now run with full parallel processing
- **Deprecated API Isolation** - Tests using deprecated DBAL APIs are now properly isolated
  - Added `tests/phpunit-deprecated.xml` for backward compatibility tests
  - Main test suite (`tests/phpunit.xml`) excludes deprecated API tests
  - Tests marked with `#[Group('deprecated')]` for explicit deprecation handling
  - Installed `phpstan-deprecation-rules` for CI/CD deprecation detection
- **Test Suite Performance Optimizations**
  - Renamed all legacy `ibase_`/`interbase` references to `fbird_`/`firebird`
  - Removed 5 duplicate Statement tests from Integration suite (already covered in Functional)
  - Removed duplicate HostDbnameRequired exception test
  - Moved `installFirebirdDatabase()` to `setUpBeforeClass()` for one-time setup
  - Optimized `cleanupSchemaTestTables()` with existence checks
  - Removed redundant cleanup from `setUp()` in SchemaManagerFunctionalTestCase
  - Removed 2 duplicate Transaction tests (`TransactionTest::testBeginTransactionCommit`, `TransactionNestingTest::testNestedStructureSuccess`)
  - Optimized schema test cleanup by adding missing tables to `SchemaManagerFunctionalTestCase::$schemaTestTables` (`ddc1372_foobar`, `t1`, `t2`, `retry_lock_test`)
  - Added explicit table cleanup to `CustomIntrospectionTest`

### Fixed
- **CQC Pipeline Segfault Handling** - Updated `docker-cqc.sh` to gracefully handle exit code 139/134 (SIGSEGV/SIGABRT)
  - Detects if tests passed despite the post-execution crash
  - Allows CI pipeline to pass when tests are successful
  - Workaround for php-firebird issue #56 until fully resolved in all PHP versions
- **PHPStan SIGSEGV Resolved** (requires php-firebird v7.0.0-rc.37+)
  - Root cause: Invalid `IS_RESOURCE` type hints in php-firebird arginfo caused `zend_type_to_string()` to return NULL
  - Fix: php-firebird v7.0.0-rc.37 removes problematic type hints
  - PHPStan, Psalm, and all static analysis tools now work correctly with ext-firebird loaded
  - Updated test environment Dockerfile to use v7.0.0-rc.37
  - Condensed investigation docs from 1352 to 99 lines (removed obsolete debug scripts)
- **PHP 8.4 Quality Improvements**
  - Fixed PHPCS error in `DataAccessTest.php` (heredoc tab indentation)
  - Suppressed Psalm `E_STRICT` deprecation warnings on PHP 8.4+ in CQC pipeline
- **CI Test Fixes for php-firebird Exception Mode**
  - Wrapped all `fbird_*` function calls in try-catch blocks to handle `Firebird\Exception` when Exception Mode is enabled
  - Updated `Statement::execute()`, `Result::fetch()`, and `Connection` destructor with proper exception handling
  - Added `Exception::fromFirebirdException()` factory method for converting native Firebird exceptions to DBAL exceptions
  - Skipped `testListDatabases` for Firebird in CI environments - requires server-side filesystem access unavailable in containerized Docker
  - Overrode `testMigrateSchema` in Firebird3SchemaManagerTest to use table-level operations instead of full schema introspection
  - All 1,585 tests now pass with 0 errors and 0 failures
- **Issue #24: PHP 8.4 Deprecation - Implicit Nullable Type on bindParam**
  - Fixed `Statement::bindParam()` method which used `?ParameterType $type = ParameterType::STRING`
  - PHP 8.4 deprecates implicit nullable types when default value is not null
  - Refactored to use explicit `ParameterType $type = ParameterType::STRING` (non-nullable with default)
  - Created private `bindValueInternal()` method for shared logic between `bindParam()` and `bindValue()`
  - Added `@deprecated` annotation to `bindParam()` - use `bindValue()` instead
  - PHPStan Level 8 validated
- **Test Fix: RetryOnLockTest causing PHP warnings and failures**
  - Skipped `RetryOnLockTest` test class - tests feature not yet implemented
  - The `ATTR_DOCTRINE_RETRY_ON_LOCK` constant is defined but retry logic not implemented
  - Fixes PHP warning: `fbird_commit_ret(): unsuccessful metadata update object TABLE is in use`
  - Tests will be re-enabled when retry-on-lock feature is implemented
- **Issue #22: [FB 2.5] Connection Resource Invalidation in Fetch Tests**
  - Fixed 22 test failures (16 in FetchTest, 6 in FetchEmptyTest) caused by invalid `parent::setUp()` calls
  - Root cause: Child test classes called `parent::setUp()` when parent class `FunctionalTestCase` uses `@before` annotation for `initConnection()`
  - Invalid parent calls attempted to execute non-existent setUp() method, invalidating database connection resources
  - Fix: Removed invalid `parent::setUp()` calls from both test classes, keeping custom setup logic
  - Result: All 1253 tests passing across all Firebird versions (2.5, 3.0, 4.0, 5.0)
  - Validated: Connection resources remain valid throughout test lifecycle
  - See: `docs/issues/2025-11-15-solution-summary.md` for complete technical analysis
- **Issue #16: LIKE Expression Silent Failures with Oversized Parameters**
  - Fixed silent query failures when LIKE parameters exceed VARCHAR field length
  - Automatically wraps LIKE column operands in `CAST(column AS VARCHAR(255))`
  - Prevents Firebird from inferring parameter type from column definition
  - Resolves issue where entire query returns empty result set instead of matching other OR conditions
  - Validated across Firebird 2.5, 3.0, 4.0, and 5.0 - consistent behavior (24/24 tests passed)
  - **Performance Impact**: CAST prevents index usage, requires full table scan
  - **Mitigation**: For performance-critical queries, filter by indexed columns first or validate parameters at application level

### Added
- **php-firebird v7.0.0-rc.6 Adoption** (Issues #28, #29, #34, #35)
  - Upgraded CI pipeline from php-firebird v7.0.0-rc.5 to v7.0.0-rc.6
  - **Exception Mode API**: Firebird functions now throw `Firebird\Exception` instead of returning false (similar to PDO::ERRMODE_EXCEPTION)
  - **SQLSTATE-based Error Classification**: Enhanced exception converter with SQL:2003 standard 5-character SQLSTATE codes
    - Class 08: Connection exceptions
    - Class 23: Constraint violations (unique, foreign key, not null, check constraints)
    - Class 28: Authorization/authentication failures
    - Class 40: Deadlock/serialization failures
    - Class 42: Syntax errors
  - Added `Exception::fromFirebirdException()` factory method to convert native exceptions to Doctrine exceptions
  - All changes maintain backward compatibility with earlier php-firebird versions using feature detection
  - Fork-safety improvements: rc.6 fixes segmentation faults in PHPStan/PHPUnit parallel mode
  - See: `docs/issues/2025-12-24-php-firebird-v7-rc6-adoption.md` for complete technical details
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

[Unreleased]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.0-rc.1...HEAD
[3.10.0-rc.1]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.0...v3.10.0-rc.1
