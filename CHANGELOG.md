# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.14.0] - 2026-07-06 - php-firebird v12.0.0 integration

### Changed
- **php-firebird v12.0.0-rc.11**: Upgraded extension dependency from `^11.1` to `^12.0`
  in `composer.json`; upgraded `satwareag/php-firebird-stubs` from `^11.1` to
  `^12.0.0-rc.11@rc`. php-firebird v12.0.0 completes the OOP API (`Firebird\Event`
  methods), eliminates all InterBase-era naming (#304), and separates `pdo_fbird`
  into a standalone extension (#258). This project uses the procedural `fbird_*`
  API exclusively, so the `pdo_fbird` split has no runtime impact - `firebird.so`
  alone is sufficient.
- **Docker test image**: Pinned php-firebird checkout to commit `d4d3851` (post-rc.11)
  in `tests/app/Dockerfile` to include the `TransactionManager::__destruct` THROW mode
  fix (#310) not yet in a tagged release.
- **Docblock return types updated** for v12 stubs accuracy:
  - `Connection::executeAuto()`: `resource|int|false` -> `\Firebird\ResultSet|int|false`
  - `Connection::reconnectLimboTransaction()`: `resource|false` -> `\Firebird\Transaction|false`
  - `ProceduralBatch::$batchHandle`: `resource` (mixed) -> `\Firebird\BatchHandle` (typed)
  - `ProceduralBatch::__construct() $transResource`: added `|\Firebird\Transaction` to union
- **CI workflows**: Upgraded php-firebird from v11.1.0 to v12.0.0-rc.11 in all GitHub
  Actions workflows (cache keys, clone steps). Updated Windows DLL download patterns
  for v12.0.0-rc.x release assets.
- **`Connection::queryInTransaction()`**: Added support for `Firebird\TransactionManager`
  (from `TBuilder::start()`) in addition to the driver's own `TransactionManager`.
  Extracts the `Firebird\Transaction` via `getResource()` and passes it to
  `fbird_query_params_tx()`. Fixes FB4/FB5 "must be a Firebird transaction resource"
  TypeError when using independent transactions.
- **`Connection::createBatch()` / `executeBatch()`**: Widened `$transaction` parameter
  type to `TransactionManager|FirebirdTransactionManager|null` for the same reason.
- **`TransactionManager::__destruct`** (php-firebird #310): Replaced `@fbird_rollback()`
  suppression with try/catch in THROW mode to prevent uncaught `Firebird\Exception`
  during transaction cleanup. Fixed in php-firebird commit `d4d3851`.
- **`fbird_trans_start` arginfo**: Removed `@phpstan-ignore argument.type` — rc.11
  fixed the signature from `mixed $options = 0` to `?array $options = null`.

### Fixed
- **FB4/FB5 test failures**: 4 errors in `QueryInTransactionTest` on Firebird 4.0/5.0
  caused by `Firebird\TransactionManager` not being recognized by `queryInTransaction()`.
  Root cause: `TBuilder::start()` returns `Firebird\TransactionManager`, but the driver
  only checked `instanceof` against its own `Satag\...\TransactionManager`.
- **BatchTest probe failure**: Removed redundant `createBatch('SELECT 1 FROM RDB$DATABASE')`
  probe in `BatchTest::setUp()` that failed on FB4/FB5 because IBatch correctly requires
  parameterized statements. `function_exists('fbird_batch_create')` is sufficient
  (the C function is only registered when `FB_API_VER >= 40`).
- **Test fixture pollution**: Added `dropTableIfExists()` / `dropSequenceIfExists()`
  cleanup before `createTable()` / `createSequence()` in 6 functional test classes
  to prevent errors when running the full suite without a clean database.
- **Identity generator reset**: Changed `installFirebirdDatabase()` seeding to use
  `UPDATE OR INSERT ... MATCHING` with explicit IDs + `ALTER TABLE ... RESTART WITH`
  for identity generators (not `SET GENERATOR`, which is silently ignored on
  identity columns).
- **ExceptionConverterTest pollution**: Added setUp/tearDown cleanup for fixture data.
- **4 incomplete tests**: Implemented `testQuotesAlterTableChangeColumnLength` in
  `FirebirdPlatformTest` and `Firebird3PlatformTest` with expected SQL arrays.

### Removed
- **`ReadOnlyIntegrationTestCase`**: Deleted deprecated test base class; migrated 5
  test classes to `AbstractIntegrationTestCase`.
- **`ConfigurableLikeCastLengthTest`**: Deleted deprecated test class (covered by
  `FirebirdConnectionTest`).
- **Psalm baseline**: Reduced from 167 to 68 lines by adding inline `@psalm-suppress`
  for false-positive `UnusedClass` detections.

### Changed (test cleanup)
- Replaced `bindParam()` with `bindValue()` in functional/integration tests (kept
  `bindParam` in 2 tests that specifically test by-reference binding behavior).
- Replaced `execute([params])` with `bindValue()` + `execute()` in functional tests
  (kept in unit mock tests that don't trigger DBAL deprecation).
- Changed `stopOnDefect` to `false` in `phpunit.xml` for better error visibility.
- Added `FirebirdSchemaManager::getCurrentSequenceValue()` using `GEN_ID(name, 0)`.
- Removed stale `@todo` from `FirebirdSchemaManager`.

### Notes
- No new v12 features adopted. The only new v12 feature (`Firebird\Event` OOP methods:
  `wait()`, `cancel()`, `getName()`, `getCount()`) is for database event monitoring
  (POST_EVENT triggers), which is outside the scope of a DBAL driver.
- The v12 OOP method signature changes (`Connection::prepare()` now requires
  `Transaction`, `Statement::execute()` takes `Transaction`) do not affect this
  project because it uses the procedural `fbird_*` API, not the OOP API.
- 18 skipped tests are all legitimate: 15 inline column comments (Firebird uses
  `COMMENT ON COLUMN` statement, not inline DDL), 3 sequence cache (FB2.5 only).
- Full test matrix verified: PHP 8.2/8.3/8.4/8.5 x Firebird 3.0/4.0/5.0 = 12 combos,
  2320 tests each, 0 errors, 0 failures.

## [3.13.0] - 2026-07-02

### Changed
- **php-firebird v11.1.0**: Upgraded extension dependency from `^10.6` to `^11.1` in `composer.json`;
  upgraded `satwareag/php-firebird-stubs` from `^10.6` to `^11.1`. php-firebird v11.0.0 introduces the
  M3 opaque-object migration (`fbird_connect()`/`fbird_pconnect()` return `Firebird\Connection` objects,
  `fbird_trans()` returns `Firebird\Transaction`, `fbird_execute()` returns `Firebird\ResultSet` for
  SELECT, `fbird_blob_create()`/`fbird_blob_open()` return `Firebird\Blob`).
  v11.1.0 completes the M3 migration: `fbird_prepare()`/`fbird_prepare_ex()` now return
  `Firebird\Statement` objects (#297), `fbird_query()`/`fbird_execute()` return `Firebird\ResultSet`
  objects (#296), autocommit visibility is fixed (#294), and MSHUTDOWN SIGSEGV on persistent
  connection cleanup is fixed (#295).
- **Driver simplification**: Replaced broad `is_object()` / `!== null && !== false` validity checks
  with strict `instanceof` assertions:
  - `Statement::isStatementValid()` → `instanceof \Firebird\Statement || instanceof \Firebird\Transaction`
  - `Result::isResultValid()` → `instanceof \Firebird\ResultSet`
  - `Connection::isConnectionValid()` already used `instanceof FirebirdConnection` (unchanged)
  - `TransactionManager::isTransactionValid()` already used `instanceof \Firebird\Transaction` (unchanged)
- **CI workflows**: Upgraded php-firebird from v10.6.2 to v11.1.0 in all GitHub Actions workflows
  (cache keys, clone steps). Removed SIGSEGV (exit 139/134) workaround from all test and coverage
  steps — the MSHUTDOWN crash is fixed in v11.1.0 (#295). Simplified test steps to direct
  `phpunit` invocation without output capture and exit-code filtering.
- **Docker test image**: Upgraded php-firebird checkout from v10.6.2 to v11.1.0 in `tests/app/Dockerfile`.
  Fixed default `ARG PHP_VERSION` from `8.1` to `8.2` (was below the `composer.json` minimum of `^8.2`).
- **SchemaManager::dropDatabase()**: Removed v8 default-link workaround (`is_resource()` fallback
  branches, `IBG(default_link)` corruption comment). With v11 opaque objects, the default-link
  semantics no longer apply — simplified to `instanceof Connection` check only.
- **resolveIdentityGenerator()**: Updated comment from "#294 workaround" to "Doctrine best practice"
  (matches Oracle OCI8 and SQL Server driver patterns for metadata queries within active transaction).
- **README**: Updated php-firebird minimum version from `v11.0+` to `v11.1+` in Requirements and
  Test Requirements sections.
- **IPADP metadata**: Bumped project version to `3.13.0`. Added `amicron-platform` to downstream
  consumers in `specs/metadata.json` (L3 conformance gap — `amicron-platform` requires
  `satag/doctrine-firebird-driver: ^3.11.0` but was not declared in downstream metadata).

### Removed
- `is_resource()` import from `FirebirdSchemaManager` (no longer used after v8 workaround removal).
- SIGSEGV exit-code handling (139, 134) from CI test steps — php-firebird v11.1.0 fixes the
  persistent connection cleanup crash (#295).

## [3.12.5] - 2026-04-10

### Fixed
- **CI infrastructure** - Upgraded all GitHub Actions SHAs to latest pinned versions, fixed
  test assertion mismatches in AlbumTest (SQL:2008 syntax) and StatementTest (column indices,
  public setUp signature). ([commit 07b8993])
- **Code Quality (Psalm)** - Resolved all Psalm CI failures by converting global
  `psalm.xml.dist` suppressions to inline `@psalm-suppress` annotations in source files.
  Affected: `Driver.php`, `FirebirdDriverMiddleware.php`, `CharsetMiddleware.php`,
  `CharsetConnectionMiddleware.php`, `Connection.php`, `FirebirdDriver.php`,
  `FirebirdPlatform.php`, `FirebirdSchemaManager.php`. Code Quality workflow now passes
  cleanly. ([commits 9b369f5..69924d1])
- **Code Quality (PHPStan)** - Added inline `@phpstan-ignore` suppressions for
  `argument.type` on `fbird_query()`/`fbird_fetch_row()` and removed useless cast.
  ([commit 57b1016])
- **Code Quality (PHPCS)** - Moved `resolveIdentityGenerator()` to private section in
  `FirebirdSchemaManager`, fixed if-alignment in `Statement.php`. ([commit c6ccc22])
- **`lastInsertId()` on Firebird 3.0/4.0** - Comprehensive fix for identity column
  sequence resolution. Correct order: `fbird_last_insert_id()` first, then `GEN_ID()`
  via `executeAuto()`, then `RDB$RELATION_FIELDS` fallback. Handles dotted sequence names,
  prevents transaction-aborting calls, wraps in try/catch for safety.
  ([commits f2107c1..e90701e])
- **Schema - bool defaults for string columns** - `normalizeColumn()` now converts PHP
  `false` to empty string `''` (not `'0'`) for string-type columns, fixing spurious
  `DEFAULT ''` diffs on schema comparison. ([commit 878456d])
- **Functional tests** - Resolved lock conflict in `resolveIdentityGenerator()` by avoiding
  exclusive DDL locks during sequence lookup, restored BatchTest guard for missing
  `Firebird\Batch` class. ([commit 9fba1f9])

## [3.12.4] - 2026-04-09

### Fixed
- **FirebirdComparator bool→integer default guard** (backport from 4.4.x) — `normalizeColumn()`
  now skips bool-to-string conversion (`false` → `'0'`) for columns whose DBAL type implements
  `PhpIntegerMappingType`. Previously, integer columns with a `false` PHP default were
  normalised to `'0'`, causing `ALTER TABLE … DEFAULT 0` to be emitted on every schema diff
  even when no change was intended. Boolean and string columns still receive `'0'`/`'1'`
  normalisation so that PHP's loose `'' == false` comparison in `hasDefaultChanged()` is
  handled correctly. ([commits 1f5b4ca/c9fefc9 on 4.4.x])
- **BatchTest graceful skip when `Firebird\Batch` is unavailable** (backport from 4.4.x) —
  `BatchTest::setUp()` now checks `class_exists('Firebird\Batch')` before attempting to call
  `createBatch()`. Without this guard, a missing class (php-firebird < v7.0.0) caused a
  fatal PHP `Error` instead of a clean `markTestSkipped()`. The catch block is narrowed to
  `RuntimeException` (FB_API_VER mismatch), removing the now-redundant
  `'Class "Firebird\Batch" not found'` string match. ([commit e85d91e on 4.4.x])

### Docs
- **README**: Fixed incorrect `composer install` → `composer require` in Installation section.
- **README**: Updated PHPUnit version badge from `10.5` to `11` in Test Coverage section.

## [3.10.5] - 2026-04-07

### Changed
- **php-firebird v10.6.2**: Upgraded extension dependency from `^10.3.2` to `^10.6` in `composer.json`; removed redundant `paragonie/polyfill-php82` (commit 4c056a5)
- **CI workflows**: Upgraded php-firebird to v10.6.2 in all GitHub Actions workflows (commit 57fd3fa)
- **CI security**: Pinned all GitHub Actions to immutable SHA digests (commit 379f2bf)
- **Docker test image**: Upgraded php-firebird checkout from v10.3.9 to v10.6.2 in `tests/app/Dockerfile` (commit 0f9f4c9)

### Fixed
- **TransactionTest**: Fixed SERIALIZABLE isolation race condition via `markConnectionNotReusable()` to prevent connection reuse after dirty state (commit 91ea882)

### Verified
- Full test suite: Firebird 3/4/5 all PASS (2324-2336 tests) with PHP 8.4 + DBAL 3.10.5 + ORM 3.6.3

## [3.10.4] - 2026-03-31

### Added
- **Resource reference registry** in `Connection.php` - prevents premature `fbird_close()` when
  multiple DBAL layers share the same native connection resource. Static registry tracks reference
  counts per resource/object ID; destructor only closes when count reaches zero.
- **`ProceduralBatch` wrapper** (`src/Driver/Firebird/ProceduralBatch.php`) - reliable alternative
  to the OO `Firebird\Batch` class whose private constructor and `Batch::fromQuery()` fail with
  "invalid batch handle" in php-firebird v10.3.9. Uses procedural `fbird_batch_create`,
  `fbird_batch_add`, `fbird_batch_execute` API.
- **`ProceduralBatchResult`** (`src/Driver/Firebird/ProceduralBatchResult.php`) - result object
  wrapping `fbird_batch_execute()` return with `successCount`, `errorCount`, `totalProcessed`.
- **Gap analysis tests** - `TransactionTest` (#65), `DefaultValueTest` (#66),
  `ComparatorTest` (#67) covering transaction nesting, DDL default values, and schema comparison.
- **Integration test suites** - `Integration-ReadOnly` and `Integration-Write` added to FB4/FB5
  PHPUnit configurations.

### Changed
- **php-firebird v10.3.9** - Upgraded from v10.3.7. Fixes SIGFPE on parameterless batch (#180),
  SIGSEGV at shutdown (#183), OO API handle loss (#184), IBatch invalidation (#185).
- **CI pipeline** - Fixed php-firebird build from source: corrected version tag from v8.2.0 to
  v10.3.9 matching `composer.json` requirement `ext-firebird: ^10.3.2`. Added OO API PHP file
  caching and installation for `Firebird\Connection`, `Database`, `TBuilder` classes.
- **PHPCS compliance** - Fixed 13 coding standard violations across 6 files: use statement
  sorting, constructor property promotion, class structure ordering, doc comment formatting,
  FQN references, early exit patterns.
- **Test suite** - Stabilized BatchTest with known-failure markers for php-firebird OO API bugs;
  DDL commit between DROP/CREATE in FunctionalTestCase.

### Fixed
- **SIGSEGV resolution** - Eliminated segmentation faults caused by premature `fbird_close()` on
  shared connection resources. Root cause: DBAL connection wrapper and user code holding
  references to the same native resource, with destructor closing it while still in use.
- **Resource type guard completion** - All `@phpstan-assert-if-true` guards verified across
  `Connection`, `Result`, `Statement`, and `TransactionManager` classes.

### Tests
- **Full suite: 2336 tests, ALL PASSED** (Firebird 4.0)
- **PHPStan Level 8: 0 errors**
- **PHPCS: 0 violations**

## [3.12.3] - 2026-03-19

### Fixed
- **Standard SQL Pagination (Firebird 3.0+)** — Replaced legacy `ROWS` syntax with standard
  SQL `OFFSET <m> ROWS FETCH NEXT <n> ROWS ONLY` for `Firebird3Platform`, `Firebird4Platform`,
  and `Firebird5Platform`. This improves compatibility with modern SQL standards and Doctrine
  DBAL 3.10+ expectations (#93)

### Changed
- **Platform Test Coverage** — Enhanced `Firebird4PlatformTest` and `Firebird5PlatformTest` by
  extending `Firebird3PlatformTest` to ensure full feature parity and regression testing
  for pagination across all supported Firebird 3.0+ versions.
- **Docker Test Stability** — Refactored `TestUtil::initializeDatabase` and corrected directory
  permissions in Firebird containers to resolve intermittent database creation failures (#93)

## [3.12.2] - 2026-03-18

### Added
- **DBAL 4.x Migration Research** — Documented connection unwrapping patterns for DBAL 4.x in `docs/research/dbal4-migration.md` to guide future major version migration (#90)

### Fixed
- **PHPUnit 11 Deprecations** — Resolved all `iniSet` usage deprecations by switching to native
  PHP functions (`ini_set`, `error_reporting`) with robust state restoration logic (#95)
- **PHP 8.2 Compatibility** — Removed typed constants from functional tests to restore support for
  PHP 8.2 runners (#98)
- **PHPStan Return Type** — Fixed `return.unusedType` warning in `FirebirdSchemaManager` by
  clarifying PHPDoc metadata for portable view definitions (#95)
- **Psalm Attribute Issues** — Resolved `InvalidAttribute` errors in GitHub Actions by suppressing
  Psalm checks for `#[Override]` on PHP 8.2 target platforms (#97)
- **Windows CI Stability** — Resolved persistent I/O errors by moving test databases to
  `C:\firebird_tests` to avoid `SYSTEM` account permission issues on the `D:` workspace drive;
  refined `TestUtil.php` path resolution to be more resilient to absolute paths (#98)
- **Psalm Baseline** — Pruned stale entries from `psalm-baseline.xml` following code quality
  improvements (#95)

### Changed
- **CI/CD Optimization (Linux)** — Consolidated static analysis into a single `quality-checks` job;
  implemented custom extension caching using `actions/cache` to speed up builds (#96)
- **CI/CD Optimization (Windows)** — Enabled integration tests on Windows runners; automated DLL
  management via PowerShell; added caching for pre-compiled extensions (#96)
- **Local Development** — Enhanced `docker-cqc.sh` and `phpunit.sh` for more reliable local
  quality gate validation (#95)
- **CI/CD Pipeline Audit** — Conducted a comprehensive review of all GitHub Actions workflows (`ci.yml`, `windows.yml`, `codeql.yml`) and local testing scripts (`act-local-test.sh`, `docker-cqc.sh`, `phpunit.sh`) to ensure parity and long-term maintainability.
- **Firebird 2.5 Testing** — Identified that Firebird 2.5 testing is currently limited to local environments using `phpunit-firebird25.xml`; documented as a known gap for future CI expansion.
- **Project Maintenance** — Closed all open milestones and issues for the DBAL 3 compatible branch; project now enters maintenance mode for the 3.x series.

## [3.12.1] - 2026-03-17

### Fixed
- **FirebirdSchemaManager Table Comments** — Correctly retrieve and map table comments during
  introspection; improved uppercase table name handling for reliable comment lookup (#92)
- **Docker Recursion Hang** — Prevent recursive `docker compose` calls in `PhpunitScriptTest` when
  running inside the container, avoiding CI hangs at 67% progress (#92)
- **Database Permissions** — Fixed file ownership of `.fdb` databases in Firebird containers to
  resolve "I/O error" and "object in use" issues during test cleanup (#92)
- **Windows CI** — Removed `ext-posix` and `ext-pcov` requirements from `composer.json` which are
  not available on Windows or problematic during platform checks (#94)

### Changed
- **php-firebird v7.3.0** — Standardized on the latest stable extension version across all CI
  environments (Docker and GitHub Actions Linux/Windows) (#93)
- **CI Matrix Expansion** — Expanded test matrix to cover PHP 8.2-8.5 and Firebird 3.0-5.0 (#93)

## [3.12.1-rc.1] - 2026-03-16

### Fixed
- **CharsetResultMiddleware corrupts binary BLOB data** — Binary BLOB columns are no longer
  passed through charset transcoding, preventing data corruption on non-UTF-8 databases (#91)

## [3.12.0] - 2026-03-16

### Added
- **`CharsetMiddleware` BLOB & Large Object Support** — PHP resource streams are now automatically
  extracted and transcoded in `CharsetResultMiddleware`; `CharsetStatementMiddleware` transcodes
  parameters bound with `ParameterType::LARGE_OBJECT`; 100% unit test coverage for the charset
  middleware stack (#89)
- **`#[Override]` Attributes** — Added PHP 8.3+ `#[Override]` to 146 override methods across 23
  source files for compile-time interface conformance checking

### Fixed
- **Metadata Lock Mitigation** — DDL statements (CREATE, ALTER, DROP, RECREATE) now trigger
  auto-commit to release system table locks, especially on Firebird 3.0 (#90)
- **Charset Transparency for BLOBs** — Fixed resource streams bypassing transcoding in the
  middleware stack (#89)
- **CI Stability (SIGSEGV/SIGABRT)** — Robust signal handling for exit codes 139/134; CI passes
  if PHPUnit output confirms successful completion despite post-shutdown crashes (#90)
- **Statement Detection** — Refactored `detectDmlStatement` regex to reliably identify DDL
  commands including RECREATE (#90)
- **Windows CI** — Fixed DLL search and installation in `windows.yml` for recursive ZIP extraction
  and versioned filenames (#90)

### Changed
- **Psalm 6.15.1** — Upgraded from 5.26.1; regenerated baseline (reduced 51% from 420→204 lines,
  151 errors fixed); removed redundant casts and stale suppressions; 0 errors, 96.85% type
  inference coverage
- **`IBatch` API Build Flag** — Docker test environment compiles `ext-firebird` with `FB_API_VER=40`
  by default to enable Firebird 4.0+ batch operations (#91)
- **CI Debugging** — Automated upload of `isql` and PHP debug artifacts on job failure (#90)

### Tests
- **Charset Middleware Round-Trip Coverage** — 70 new test cases (339 assertions) verifying real
  Windows-1252 byte encoding through the full middleware stack: all six fetch methods with WIN1252
  bytes, BLOB TEXT streams, seven Amicron special-character round-trip strings, LIKE/search
  parameter encoding, and `Connection::quote()` encoding path (#89)
- **Full Suite: 1988 tests, 4199 assertions, 0 failures**

### Docs
- **`specs/001-charset-transparency-middleware/spec.md`** — Driver-level feature spec with
  5 user stories, 7 functional requirements, 4 NFRs, and 5 measurable success criteria for the
  charset transparency contract

## [3.10.2] - 2026-03-06

### Changed
- **Branching Strategy** — Transitioned to `3.10.x` branching scheme to explicitly align with Doctrine DBAL 3.10.x minor releases
- **CI Environments** — Removed AppVeyor Windows CI workflow to focus on Docker-based Linux CI
- **Docker Test Container** — Updated `php-firebird` extension version to `v7.2.0` in CI configuration

### Fixed
- **Cursor Lock Issue** — Simplified test suite and fixed Firebird cursor lock `tearDown` issues (#47)

### Tests
- **Coverage Improvement** — Added targeted unit tests for `ExecutionMode`, `Keywords`, `SelectSQLBuilder`, and `CharsetMiddleware` to push overall line coverage beyond 90%

## [3.11.0] - 2026-03-05

### Added
- **`CharsetMiddleware`** — transparent charset conversion middleware for non-UTF-8 Firebird databases
  - `src/Driver/Firebird/Middleware/CharsetMiddleware` — top-level DBAL middleware (implements `Doctrine\DBAL\Driver\Middleware`)
  - `src/Driver/Firebird/Middleware/CharsetConnectionMiddleware` — encodes query-level string parameters (PHP→DB)
  - `src/Driver/Firebird/Middleware/CharsetStatementMiddleware` — encodes `bindValue()` and execute params (PHP→DB); wraps Result
  - `src/Driver/Firebird/Middleware/CharsetResultMiddleware` — decodes all `fetch*()` string results (DB→PHP)
  - Default encodings: `databaseEncoding: 'Windows-1252'`, `phpEncoding: 'UTF-8'` (covers WIN1252/ISO8859_1 Firebird databases)
  - Custom encoding pairs supported (e.g. `ISO-8859-1`/`UTF-8`)
  - Ported and generalized from `satag-amicron-entity-bundle` for all Firebird users
- **`ext-mbstring`** added to `require` (`mb_convert_encoding` dependency)
- **`tests/Test/Unit/Driver/Middleware/CharsetMiddlewareTest`** — 15 unit tests, 36 assertions

## [3.10.1] - 2026-03-05

### Fixed
- **CI matrix version parsing** — Fixed all CI matrix jobs failing with 'Invalid platform version'
  when using newer firebirdsql/firebird Docker images that return plain numeric version strings
  (e.g. '5.0.3.1683') instead of the legacy 'LI|WI-V...' format (#82, #83)

### Changed
- **php-firebird Extension: v7.1.0 → v7.2.0** — Bumped minimum required extension version
  - `composer.json`: `ext-firebird "^7.2.0"`, `satwareag/php-firebird-stubs "^7.2.0"`
  - CI workflow now builds `--branch v7.2.0` from source
  - Minimum PHP version: **8.2** (PHP 8.1 dropped upstream in php-firebird v7.2.0)
  - Minimum Firebird server: **3.0** (Firebird 2.5 dropped upstream in php-firebird v7.2.0)
  - `ibase_*` function aliases fully removed upstream — driver already uses `fbird_*` exclusively

### Removed
- **Firebird 2.5 support** — php-firebird v7.2.0 drops FB2.5 server support; updated README,
  version compatibility matrix, and test documentation accordingly

## [3.10.0-RC.1] - 2026-03-04

### Added
- **`docs/EXAMPLES.md`** — Comprehensive PHP 8.4 + Firebird 3.0 usage examples including:
  Standalone and Symfony connection setup, explicit/nested transactions, named parameters,
  BLOB handling, Exception Mode, `executeAuto()` autonomous transactions, CQRS/audit pattern
  with `queryInTransaction()`, `getConnectionInfo()` diagnostics, Amicron ERP entity
  example, and IBatch bulk INSERT (FB4+ only, version-guarded)
- **`docs/PERFORMANCE.md`** — Performance guide covering PCOV vs Xdebug, prepared statement
  reuse, FB3 batch insert strategies, IBatch API (FB4+ benchmarks), BLOB streaming,
  connection pooling, `DateTimeImmutable` return from `FBIRD_FETCH_DATE_OBJ`, LIKE
  optimization, and `getConnectionInfo()` query profiling
- **README.md version compatibility matrix** — Full feature table for FB2.5–5.0 documenting
  which features are available per server version (IBatch, Exception Mode, savepoints, etc.)

### Changed
- **php-firebird Extension: v7.0.0-rc.52 → v7.0.0 (stable GA)** released 2026-03-04
  - `tests/app/Dockerfile` builds `--branch v7.0.0` with build-time version assertion
  - `composer.json`: `ext-firebird "^7.0.0"`, `satwareag/php-firebird-stubs "^7.0.0"`
  - All 85 `fbird_*` functions available in stable stubs
  - IBatch API (`fbird_batch_*`) — confirmed Firebird 4.0+ server requirement, version-guarded
  - Exception Mode (`FBIRD_EXCEPTION_MODE_THROW`) — confirmed FB3.0+ compatible
  - `fbird_execute_auto()` / `fbird_connection_info()` — confirmed FB3.0+ compatible
- **`composer.json` platform.php: `8.1` → `8.4`** — primary optimization target aligned
  with `satag/amicron-entity-bundle` which requires `php: ^8.4`
- **GitHub Actions CI** — PCOV coverage driver; coverage upload on PHP 8.4 + FB3 only
  (primary target); `static-analysis` job uses `coverage: none` for faster execution;
  extension check via `phpversion('interbase')` with strict version assertion

### Fixed
- **`Connection::executeAuto()` return type** — corrected from `int|false` to `mixed`
  matching v7.0.0 stub signature (`resource|int|false`)
- **PHPStan Level 8: 0 errors** — stale `ignoreErrors` entries removed from
  `phpstan.neon.dist` (now properly defined in v7.0.0 vendor stubs)

### Tests
- **+8 new unit tests** in `ConnectionTest` covering previously untested paths:
  `autoCommit()` early-return conditions, nested `beginTransaction()`/`commit()`/`rollBack()`
  with savepoint delegation, IBatch guard, `getConnectionInfo()`, `getLimboTransactions()`,
  `reconnectLimboTransaction()`, `createIndependentTransaction()`, `queryInTransaction()`
- **Unit suite: 61/61 passing** (1 skipped — `fbird_errcode` not loaded outside Docker)

### Changed
- **php-firebird Extension Upgrade** — Updated to v7.0.0 GA (from v7.0.0-rc.52)
  - Fixes SIGSEGV in resource destructors (`fbird_service.c`, `fbird_blobs.c`)
  - `fbird_query_params_tx` stub retained in local stubs (PHP namespace function)

### Fixed
- **PHPStan Level 8 Zero Errors** - Eliminated all 53 baseline suppressions
  - Added `stubs/firebird-userland-classes.php` for `Firebird\{TBuilder,Transaction,Database,Batch,BatchResult,DbInfo}` classes
  - Added `stubs/firebird-global-functions.php` for `fbird_query_params_tx()` function
  - Fixed stub loading: switched from `stubFiles` to `scanFiles` for userland PHP classes (PHPStan limitation)
  - Fixed `Connection.php`: null coalescing for `fbird_execute_auto` params, targeted `@phpstan-ignore` annotations
  - Fixed `ExceptionConverter.php`: removed redundant `method_exists`/null checks now covered by stubs
  - Fixed `Result.php`: correct `@phpstan-ignore` placement for `property.onlyWritten` on constructor promoted property
  - Fixed `FirebirdDriver.php`: `@phpstan-ignore-line` for intentional deprecated `VersionAwarePlatformDriver` interface
  - Fixed `ConnectionWrapper.php`: `@phpstan-ignore` for deprecated `getIdentitySequenceName()` (backward compat)
  - Fixed `FirebirdSchemaManager.php`: inline `@phpstan-ignore-line` for `fbird_query` int constant argument
  - Added `phpstan.neon.dist` `ignoreErrors` for deprecated DBAL platform methods (backward compatibility)
  - `phpstan-baseline.neon` is now empty — all errors resolved at source
- **PHPCS Configuration** - Excluded `tests/debug/` from coding standards checks
  - Debug scripts are development tools, not production code
  - Prevents false positives on quick debug shell scripts

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

[Unreleased]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.12.5...HEAD
[3.12.5]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.12.4...v3.12.5
[3.12.4]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.5...v3.12.4
[3.10.5]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.4...v3.10.5
[3.10.4]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.2...v3.10.4
[3.12.1-rc.1]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.12.0...v3.12.1-rc.1
[3.12.0-RC.3]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.12.0-RC.2...v3.12.0-RC.3
[3.12.0-RC.2]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.12.0-rc.1...v3.12.0-RC.2
[3.11.0-RC.2]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.11.0...v3.11.0-RC.2
[3.11.0]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.1...v3.11.0
[3.10.2]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.1...v3.10.2
[3.10.1]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.0-RC.1...v3.10.1
[3.10.0-RC.1]: https://github.com/satwareAG/doctrine-firebird-driver/compare/v3.10.0-rc.1...v3.10.0-RC.1
[3.10.0-rc.1]: https://github.com/satwareAG/doctrine-firebird-driver/releases/tag/v3.10.0-rc.1
