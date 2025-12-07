# Testing Guide

Comprehensive testing guide for the Doctrine Firebird Driver project.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Code Coverage](#code-coverage)
3. [Project Test Structure](#project-test-structure)
4. [PHPUnit Configuration Files](#phpunit-configuration-files)
5. [Test Execution](#test-execution)
6. [Writing Tests](#writing-tests)
7. [Environment Setup](#environment-setup)
8. [Troubleshooting](#troubleshooting)
9. [Multi-Version Testing](#multi-version-testing)
10. [CI/CD Integration](#cicd-integration)

## Quick Start

### Prerequisites

- Docker and Docker Compose installed
- PHP 8.1+ with ext-interbase
- Composer dependencies installed (`composer install`)

### Running Tests

The project includes optimized test runner scripts with multiple modes:

```bash
# Basic test run (Firebird 3 default)
cd tests && ./phpunit.sh

# Run with code coverage (PCOV - fast)
cd tests && ./phpunit.sh -c

# Run with HTML coverage report
cd tests && ./phpunit.sh -c -f html

# Run all Firebird versions (2.5, 3, 4, 5)
cd tests && ./phpunit.sh -v all

# Run specific version
cd tests && ./phpunit.sh -v 4

# Run specific test suite
cd tests && ./phpunit.sh -s unit

# Show help for all options
cd tests && ./phpunit.sh --help
```

### Code Quality Checks

```bash
# Full code quality pipeline (static analysis + tests + coverage)
cd tests && ./docker-cqc.sh

# Quick mode: static analysis only (no tests)
cd tests && ./docker-cqc.sh --quick

# Coverage mode: tests with coverage only
cd tests && ./docker-cqc.sh --coverage

# Rebuild containers (after Dockerfile changes)
cd tests && ./docker-cqc.sh --rebuild
```

## Code Coverage

### Overview

Code coverage is measured using **PCOV** (2-5x faster than Xdebug) to track test effectiveness.

**Target: ≥80% code coverage** (First Citizen Excellence Project goal)

### Coverage Tools

| Tool | Purpose | Speed | Use Case |
|------|---------|-------|----------|
| **PCOV** | Line coverage (recommended) | Fast (2-5x faster) | CI/CD, daily development |
| **Xdebug** | Line + path coverage | Slow | Debugging, detailed analysis |

### Running Coverage

```bash
# Quick coverage (text output)
cd tests && ./phpunit.sh -c

# HTML report (browse tests/var/coverage/html/index.html)
cd tests && ./phpunit.sh -c -f html

# Clover format (for CI/CD integration)
cd tests && ./phpunit.sh -c -f clover

# All formats (text + HTML + Clover)
cd tests && ./phpunit.sh -c -f all
```

### Coverage Reports Location

| Format | Location | Purpose |
|--------|----------|---------|
| HTML | `tests/var/coverage/html/index.html` | Interactive browser viewing |
| Clover | `tests/var/coverage/clover.xml` | CI/CD integration, badges |
| Text | `tests/var/coverage/coverage.txt` | Terminal viewing, logs |
| JUnit | `tests/var/logs/junit.xml` | CI/CD test results |

### PHPUnit Coverage Configuration

The `tests/phpunit.xml` includes comprehensive coverage configuration:

```xml
<coverage
  includeUncoveredFiles="true"
  ignoreDeprecatedCodeUnits="true"
  pathCoverage="false"
>
  <report>
    <clover outputFile="var/coverage/clover.xml"/>
    <html outputDirectory="var/coverage/html" lowUpperBound="50" highLowerBound="80"/>
    <text outputFile="var/coverage/coverage.txt" showUncoveredFiles="true"/>
  </report>
</coverage>
```

### Docker Container Setup

The Docker test container includes PCOV pre-installed and configured:

```dockerfile
# Install PCOV for fast code coverage
RUN pecl install pcov && docker-php-ext-enable pcov

# Configure PCOV
RUN echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini \
    && echo "pcov.directory=/app/src" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
```

### Coverage Thresholds

| Metric | Threshold | Description |
|--------|-----------|-------------|
| Line coverage | ≥80% | Overall code coverage target |
| Class coverage | ≥75% | Minimum for critical classes |
| Method coverage | ≥70% | Methods with test coverage |

### Viewing Coverage Reports

After running tests with coverage, open the HTML report:

```bash
# Linux
xdg-open tests/var/coverage/html/index.html

# macOS
open tests/var/coverage/html/index.html

# Windows
start tests/var/coverage/html/index.html
```

## Project Test Structure

### Directory Layout

```
tests/
├── phpunit.xml                 # Default config (Firebird 3)
├── phpunit-firebird25.xml      # Firebird 2.5 configuration
├── phpunit-firebird4.xml       # Firebird 4 configuration
├── phpunit-firebird5.xml       # Firebird 5 configuration
├── phpunit.sh                  # Single version runner (FB 2.5)
├── phpunit-all.sh              # Multi-version runner (all versions)
├── docker-compose.yml          # Test environment services
├── Test/
│   ├── FunctionalTestCase.php # Base class for functional tests
│   ├── TestUtil.php           # Test utilities
│   ├── Unit/                  # Unit tests (isolated, no DB)
│   ├── Functional/            # Functional tests (with DB)
│   ├── Integration/           # Integration tests (full stack)
│   ├── Platforms/             # Platform-specific tests
│   ├── Driver/                # Driver tests
│   ├── Tools/                 # Tool tests
│   └── Resource/              # Test resources (fixtures, entities)
```

### Test Categories

| Category | Purpose | Database | Execution Speed | Count |
|----------|---------|----------|-----------------|-------|
| **Unit** | Isolated component testing | No | Fast (<100ms) | 24 |
| **Functional** | Feature testing with DB | Yes | Medium (100ms-1s) | 12 |
| **Integration** | Full stack integration | Yes | Slow (1s+) | Variable |
| **Platforms** | Platform-specific SQL/DDL | Mixed | Mixed | Variable |

## PHPUnit Configuration Files

### Configuration Selection Guide

The project supports multiple Firebird versions via different PHPUnit configuration files:

| Config File | Firebird Version | Docker Service | Use Case |
|-------------|------------------|----------------|----------|
| `phpunit.xml` | 3.0 (default) | `firebird3` | Development, default testing |
| `phpunit-firebird25.xml` | 2.5 | `firebird25` | Legacy compatibility |
| `phpunit-firebird4.xml` | 4.0 | `firebird4` | Modern features |
| `phpunit-firebird5.xml` | 5.0 | `firebird5` | Latest version |

### Key Configuration Differences

The primary difference between configurations is the `db_host` variable:

```xml
<!-- phpunit.xml (Firebird 3) -->
<var name="db_host" value="firebird3"/>

<!-- phpunit-firebird25.xml (Firebird 2.5) -->
<var name="db_host" value="firebird25"/>

<!-- phpunit-firebird4.xml (Firebird 4) -->
<var name="db_host" value="firebird4"/>

<!-- phpunit-firebird5.xml (Firebird 5) -->
<var name="db_host" value="firebird5"/>
```

### Common Configuration Elements

All configurations share:

```xml
<var name="db_driver_class" value="Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver"/>
<var name="db_user" value="sysdba"/>
<var name="db_password" value="masterkey"/>
<var name="db_dbname" value="/firebird/data/phpunit-integration-tests.fdb"/>
<var name="db_charset" value="UTF8" />
<ini name="memory_limit" value="4G"/>
```

## Test Execution

### Local Development (via Docker)

**Step 1: Start Docker services**

```bash
cd tests
docker compose up -d
```

**Step 2: Run tests**

```bash
# Inside Docker container
docker exec --user=application -w /app/tests app-doctrine-firebird-driver \
    php ../vendor/bin/phpunit -c phpunit.xml

# With specific options
docker exec --user=application -w /app/tests app-doctrine-firebird-driver \
    php ../vendor/bin/phpunit -c phpunit.xml --stop-on-failure --testdox
```

**Step 3: Stop services**

```bash
docker compose down
```

### Using Test Runner Scripts

**Single version (Firebird 2.5):**

```bash
cd tests
./phpunit.sh
```

**All versions (2.5, 3, 4, 5):**

```bash
cd tests
./phpunit-all.sh
```

### Running Specific Test Suites

```bash
# Unit tests only
php vendor/bin/phpunit tests/Test/Unit/

# Functional tests only
php vendor/bin/phpunit tests/Test/Functional/

# Integration tests only
php vendor/bin/phpunit tests/Test/Integration/

# Specific test class
php vendor/bin/phpunit tests/Test/Unit/Platforms/FirebirdPlatformConfigurationTest.php

# Specific test method
php vendor/bin/phpunit --filter testConstructorWithEmptyArrayUsesDefaults \
    tests/Test/Unit/Platforms/FirebirdPlatformConfigurationTest.php
```

### PHPUnit Options Reference

```bash
# Stop on first failure
php vendor/bin/phpunit --stop-on-failure

# Show test names as they run
php vendor/bin/phpunit --testdox

# Show code coverage (requires Xdebug)
php vendor/bin/phpunit --coverage-html coverage/

# Filter tests by pattern
php vendor/bin/phpunit --filter "Configuration"

# Run tests in random order
php vendor/bin/phpunit --order-by=random
```

## Writing Tests

### Namespace and Directory Mapping

**CRITICAL:** Test namespaces must exactly match directory structure.

**Pattern:**
```
Directory: tests/Test/{Category}/{SubPath}/
Namespace: Satag\DoctrineFirebirdDriver\Test\{Category}\{SubPath}\
```

**Examples:**

| Test File Location | Namespace |
|-------------------|-----------|
| `tests/Test/Unit/Platforms/FirebirdPlatformConfigurationTest.php` | `Satag\DoctrineFirebirdDriver\Test\Unit\Platforms\` |
| `tests/Test/Functional/ConfigurableLikeCastLengthTest.php` | `Satag\DoctrineFirebirdDriver\Test\Functional\` |
| `tests/Test/Unit/Driver/FirebirdDriverConfigurationTest.php` | `Satag\DoctrineFirebirdDriver\Test\Unit\Driver\` |
| `tests/Test/Integration/Doctrine/DBAL/SchemaManager/TableTest.php` | `Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\SchemaManager\` |

### Base Test Classes

#### Unit Tests: Extend `PHPUnit\Framework\TestCase`

```php
<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;

final class FirebirdPlatformConfigurationTest extends TestCase
{
    public function testConstructorWithEmptyArrayUsesDefaults(): void
    {
        $config = new FirebirdPlatformConfiguration([]);

        self::assertSame(255, $config->getLikeCastLength());
    }
}
```

#### Functional Tests: Extend `FunctionalTestCase`

```php
<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

final class MyFunctionalTest extends FunctionalTestCase
{
    public function testDatabaseOperation(): void
    {
        // $this->connection is automatically available
        $result = $this->connection->executeQuery('SELECT 1 FROM RDB$DATABASE');
        
        self::assertNotNull($result);
    }
}
```

### Test Naming Conventions

**File naming:**
- Unit test: `{ClassName}Test.php` (e.g., `FirebirdPlatformConfigurationTest.php`)
- Functional test: `{FeatureName}Test.php` (e.g., `ConfigurableLikeCastLengthTest.php`)
- Test class must be `final`

**Method naming:**
- Pattern: `test{Behavior}{Condition}` (e.g., `testConstructorWithEmptyArrayUsesDefaults`)
- Use descriptive names explaining what is tested
- Methods must be `public` and return `void`

**Data providers:**
- Pattern: `provide{DataType}` (e.g., `provideValidLikeCastLengths`)
- Return type: `Iterator` or `array`
- Use `yield` with named keys for clarity

```php
public function provideValidLikeCastLengths(): Iterator
{
    yield 'minimum value (1)' => [1];
    yield 'default value (255)' => [255];
    yield 'maximum value (8191)' => [8191];
}
```

### Creating New Tests - Step by Step

**1. Determine test category:**
- Testing single class method without DB? → Unit test
- Testing feature with database? → Functional test
- Testing full system integration? → Integration test

**2. Create file in correct directory:**

```bash
# Unit test example
touch tests/Test/Unit/Platforms/MyNewClassTest.php

# Functional test example
touch tests/Test/Functional/MyNewFeatureTest.php
```

**3. Set correct namespace:**

```php
<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;
// Namespace MUST match directory structure
```

**4. Extend appropriate base class:**

```php
use PHPUnit\Framework\TestCase; // For unit tests

final class MyNewClassTest extends TestCase
{
    // Tests here
}
```

**5. Write test methods:**

```php
public function testMethodDoesExpectedBehavior(): void
{
    // Arrange
    $instance = new MyClass();
    
    // Act
    $result = $instance->method();
    
    // Assert
    self::assertSame('expected', $result);
}
```

**6. Run the test:**

```bash
php vendor/bin/phpunit tests/Test/Unit/Platforms/MyNewClassTest.php
```

## Environment Setup

### Docker Services

The test environment uses Docker Compose with multiple Firebird versions:

```yaml
services:
  firebird25:
    image: jacobalberty/firebird:2.5-sc
    
  firebird3:
    image: jacobalberty/firebird:3.0
    
  firebird4:
    image: jacobalberty/firebird:4.0
    
  firebird5:
    image: jacobalberty/firebird:5.0
```

### Environment Variables

Tests use PHPUnit configuration variables (not OS environment variables):

```xml
<php>
    <var name="db_driver_class" value="..."/>
    <var name="db_host" value="firebird3"/>
    <var name="db_user" value="sysdba"/>
    <var name="db_password" value="masterkey"/>
    <var name="db_dbname" value="/firebird/data/phpunit-integration-tests.fdb"/>
    <var name="db_charset" value="UTF8"/>
</php>
```

Access in tests via `TestUtil::getConnection()` which reads these variables.

### Autoloading Configuration

The `composer.json` defines PSR-4 autoloading for tests:

```json
{
    "autoload-dev": {
        "psr-4": {
            "Satag\\DoctrineFirebirdDriver\\Test\\": "tests/Test/"
        }
    }
}
```

**After adding new test directories, run:**

```bash
composer dump-autoload
```

## Troubleshooting

### Common Issues and Solutions

#### Issue: "Class not found" Error

**Symptom:**
```
Error: Class 'Satag\DoctrineFirebirdDriver\Test\Unit\Platforms\FirebirdPlatformConfigurationTest' not found
```

**Causes & Solutions:**

1. **Namespace mismatch:**
   - **Check:** Namespace in file matches directory structure
   - **Fix:** Update namespace to match exact directory path
   ```php
   // WRONG
   namespace Satag\DoctrineFirebirdDriver\Platforms;
   
   // CORRECT (for tests/Test/Unit/Platforms/)
   namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;
   ```

2. **Missing base class:**
   - **Check:** Test class extends `TestCase` or `FunctionalTestCase`
   - **Fix:** Add `extends TestCase` to class declaration
   ```php
   use PHPUnit\Framework\TestCase;
   
   final class MyTest extends TestCase { }
   ```

3. **Autoload not updated:**
   - **Check:** New directory added to test structure
   - **Fix:** Regenerate autoload files
   ```bash
   composer dump-autoload
   ```

#### Issue: PHPUnit Can't Discover Tests

**Symptom:**
```
No tests executed!
```

**Causes & Solutions:**

1. **Wrong working directory:**
   - **Check:** Running PHPUnit from project root, not tests/
   - **Fix:** Either cd to tests/ or use relative path
   ```bash
   # Option 1: From project root
   php vendor/bin/phpunit -c tests/phpunit.xml tests/Test/
   
   # Option 2: From tests/
   cd tests && php ../vendor/bin/phpunit -c phpunit.xml
   ```

2. **Wrong PHPUnit config file:**
   - **Check:** Using correct config for desired Firebird version
   - **Fix:** Specify config explicitly
   ```bash
   php vendor/bin/phpunit -c tests/phpunit-firebird4.xml
   ```

3. **Test methods not public:**
   - **Check:** Test methods are `public`, not `private` or `protected`
   - **Fix:** Change method visibility
   ```php
   // WRONG
   private function testSomething(): void { }
   
   // CORRECT
   public function testSomething(): void { }
   ```

#### Issue: Database Connection Failures

**Symptom:**
```
SQLSTATE[08006] [335544721] Unable to complete network request to host "firebird3"
```

**Causes & Solutions:**

1. **Docker services not running:**
   - **Check:** `docker compose ps` shows no services
   - **Fix:** Start services
   ```bash
   cd tests && docker compose up -d
   ```

2. **Wrong db_host in config:**
   - **Check:** PHPUnit config `db_host` matches docker-compose service name
   - **Fix:** Use correct config file
   ```bash
   # For Firebird 3
   php vendor/bin/phpunit -c phpunit.xml  # Uses db_host="firebird3"
   
   # For Firebird 2.5
   php vendor/bin/phpunit -c phpunit-firebird25.xml  # Uses db_host="firebird25"
   ```

3. **Services not fully started:**
   - **Check:** Services just started, Firebird not ready
   - **Fix:** Wait 5-10 seconds or check service logs
   ```bash
   docker compose logs firebird3
   ```

#### Issue: Memory Limit Errors

**Symptom:**
```
Fatal error: Allowed memory size of X bytes exhausted
```

**Solution:**

PHPUnit configs already set 4G limit. If still occurring:

```bash
# Increase memory limit
php -d memory_limit=8G vendor/bin/phpunit -c tests/phpunit.xml
```

#### Issue: Permission Denied in Docker

**Symptom:**
```
Permission denied: /app/tests/...
```

**Solution:**

Use correct user when executing in Docker:

```bash
# WRONG
docker exec app-doctrine-firebird-driver php vendor/bin/phpunit

# CORRECT
docker exec --user=application app-doctrine-firebird-driver php vendor/bin/phpunit
```

### Diagnostic Commands

```bash
# Check PHPUnit installation
php vendor/bin/phpunit --version

# Verify Docker services
docker compose ps
docker compose logs firebird3

# Check autoloading
composer dump-autoload --optimize

# Verbose test output
php vendor/bin/phpunit --verbose

# Debug configuration
php vendor/bin/phpunit --configuration tests/phpunit.xml --debug
```

## Multi-Version Testing

### Testing Across Firebird Versions

The project supports Firebird 2.5, 3.0, 4.0, and 5.0. Some features and SQL syntax differ between versions.

### Version-Specific Considerations

| Version | Key Differences | Test Focus |
|---------|----------------|------------|
| **2.5** | Legacy syntax, older DDL | Backward compatibility |
| **3.0** | BOOLEAN type introduced | Current baseline |
| **4.0** | Improved performance features | Modern features |
| **5.0** | Latest syntax enhancements | Forward compatibility |

### Running Multi-Version Tests

```bash
# All versions sequentially
cd tests && ./phpunit-all.sh

# Individual versions
docker exec --user=application -w /app/tests app-doctrine-firebird-driver \
    php ../vendor/bin/phpunit -c phpunit-firebird25.xml

docker exec --user=application -w /app/tests app-doctrine-firebird-driver \
    php ../vendor/bin/phpunit -c phpunit.xml  # Firebird 3

docker exec --user=application -w /app/tests app-doctrine-firebird-driver \
    php ../vendor/bin/phpunit -c phpunit-firebird4.xml

docker exec --user=application -w /app/tests app-doctrine-firebird-driver \
    php ../vendor/bin/phpunit -c phpunit-firebird5.xml
```

### Version-Specific Tests

Some tests should only run on specific versions:

```php
use PHPUnit\Framework\TestCase;

final class VersionSpecificTest extends TestCase
{
    public function testFirebird3Feature(): void
    {
        $connection = TestUtil::getConnection();
        
        // Skip if not Firebird 3+
        if (version_compare($connection->getServerVersion(), '3.0', '<')) {
            self::markTestSkipped('Requires Firebird 3.0+');
        }
        
        // Test Firebird 3+ specific feature
    }
}
```

## CI/CD Integration

### GitLab CI Example

```yaml
stages:
  - test

test:firebird25:
  stage: test
  script:
    - cd tests
    - docker compose up -d
    - sleep 10
    - docker exec --user=application -w /app/tests app-doctrine-firebird-driver
        php ../vendor/bin/phpunit -c phpunit-firebird25.xml
    - docker compose down

test:firebird3:
  stage: test
  script:
    - cd tests
    - docker compose up -d
    - sleep 10
    - docker exec --user=application -w /app/tests app-doctrine-firebird-driver
        php ../vendor/bin/phpunit -c phpunit.xml
    - docker compose down

test:firebird4:
  stage: test
  script:
    - cd tests
    - docker compose up -d
    - sleep 10
    - docker exec --user=application -w /app/tests app-doctrine-firebird-driver
        php ../vendor/bin/phpunit -c phpunit-firebird4.xml
    - docker compose down

test:firebird5:
  stage: test
  script:
    - cd tests
    - docker compose up -d
    - sleep 10
    - docker exec --user=application -w /app/tests app-doctrine-firebird-driver
        php ../vendor/bin/phpunit -c phpunit-firebird5.xml
    - docker compose down
```

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        firebird: ['2.5', '3.0', '4.0', '5.0']
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: interbase, pdo
      
      - name: Install dependencies
        run: composer install
      
      - name: Start Docker services
        run: |
          cd tests
          docker compose up -d
          sleep 10
      
      - name: Run tests
        run: |
          cd tests
          CONFIG="phpunit.xml"
          if [ "${{ matrix.firebird }}" = "2.5" ]; then CONFIG="phpunit-firebird25.xml"; fi
          if [ "${{ matrix.firebird }}" = "4.0" ]; then CONFIG="phpunit-firebird4.xml"; fi
          if [ "${{ matrix.firebird }}" = "5.0" ]; then CONFIG="phpunit-firebird5.xml"; fi
          docker exec --user=application -w /app/tests app-doctrine-firebird-driver \
            php ../vendor/bin/phpunit -c $CONFIG
      
      - name: Stop Docker services
        run: cd tests && docker compose down
```

## Additional Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Firebird Documentation](https://firebirdsql.org/en/documentation/)
- [Doctrine DBAL Documentation](https://www.doctrine-project.org/projects/dbal.html)
- [Project Repository](https://github.com/satwareAG/doctrine-firebird-driver)

## Getting Help

- **Issues:** [GitHub Issues](https://github.com/satwareAG/doctrine-firebird-driver/issues)
- **Discussions:** Check existing issues for similar problems
- **Debugging:** Enable `--verbose` and `--debug` flags in PHPUnit

---

**Last Updated:** 2025-12-07  
**PHPUnit Version:** 10.5  
**Doctrine DBAL:** ^3.10  
**Supported Firebird Versions:** 2.5, 3.0, 4.0, 5.0
