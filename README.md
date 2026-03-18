Doctrine Firebird driver
---------------------------

[![codecov](https://codecov.io/github/satwareAG/doctrine-firebird-driver/graph/badge.svg?token=O66YV6TGM1)](https://codecov.io/github/satwareAG/doctrine-firebird-driver)

[Firebird](https://firebirdsql.org/) driver for the [Doctrine DBAL](https://github.com/doctrine/dbal)

# Requirements

To utilize this library in your application code, the following is required:

- **Firebird Server**: **3.0+** (minimum; 4.0 and 5.0 also supported — 2.5 dropped in php-firebird v7.2.0)
- **PHP**: **>= 8.2** (**8.4 recommended** — primary optimization target for Amicron ERP integration)
- **php-firebird extension** v7.3.0+ (satwareAG fork with IBatch, Exception Mode, OO API, PHP 8.4 hardening)
- [doctrine/dbal ^3.10](https://packagist.org/packages/doctrine/dbal#3.10.0) (3.10.x branch)
- [doctrine/dbal ^4.4](https://packagist.org/packages/doctrine/dbal#4.4.0) (4.4.x branch)

## Version Compatibility Matrix

| Feature | FB 3.0 | FB 4.0 | FB 5.0 |

|---------|--------|--------|--------|
| Basic DBAL (queries, transactions) | ✅ | ✅ | ✅ |
| Schema introspection | ✅ | ✅ | ✅ |
| Named parameters | ✅ | ✅ | ✅ |
| BLOB support | ✅ | ✅ | ✅ |
| Exception Mode (`Firebird\Exception`) | ✅ | ✅ | ✅ |
| SQLSTATE Error Mapping | ✅ | ✅ | ✅ |
| `fbird_execute_auto()` auto-commit | ✅ | ✅ | ✅ |
| `fbird_connection_info()` / `DbInfo` | ✅ | ✅ | ✅ |
| Savepoints (nested transactions) | ✅ | ✅ | ✅ |
| IBatch API (bulk INSERT 10-12×) | ❌ | ✅ | ✅ |
| `RETURNING` multi-row | ❌ | ✅ | ✅ |
| `SCROLL` cursors | ❌ | ✅ | ✅ |
| `DECFLOAT` type | ❌ | ✅ | ✅ |
| `TIME ZONE` / `TIMESTAMP TZ` | ❌ | ✅ | ✅ |

> **Breaking change (php-firebird v7.2.0+):** Firebird 2.5 support dropped. Minimum server version is **3.0**.

> **Windows Support:** Starting with v7.3.0, pre-built Windows DLLs are available for PHP 8.2, 8.3, 8.4, and 8.5 (NTS and TS).

> **Note:** Firebird **3.0 is the primary production target** (Amicron ERP). Features marked ❌ for FB3
> are either absent on the server or require Firebird 4.0+. Feature detection is automatic —
> no manual configuration is needed.

# License & Disclaimer

See [LICENSE](LICENSE) file. Basically: Use this library at your own risk.

## Limitations of Schema Manager

This library does **_not_ fully support generation through the Schema Manager**, i.e.:

1. Generation of database tables, views, etc. from entities.
2. Generation of entities from database tables, views, etc.

Reasons for not investing time in schema generation include that Firebird does not allow renaming of tables, which in turn makes automated schema updates annoying and over-complicated. Better results are probably achieved by writing manual migrations.

# Installation

Via Composer ([`satag/doctrine-firebird-driver`](https://packagist.org/packages/satag/doctrine-firebird-driver)):

    composer install satag/doctrine-firebird-driver

Via Github:

    git clone https://github.com/satwareAG/doctrine-firebird-driver.git

## Error Handling with SQLSTATE

With php-firebird v7.0.0+, the driver provides standardized SQLSTATE error codes for better error classification and handling. When Exception Mode is enabled (default), the driver maps these to specific Doctrine exceptions.

### Doctrine Exception Mapping

| SQLSTATE Class | Doctrine Exception | Description |
|---------------|-------------------|-------------|
| 08xxx | ConnectionException | Connection errors |
| 23xxx | ConstraintViolation | Integrity constraints |
| 23502 | NotNullConstraintViolationException | NOT NULL violation |
| 23503 | ForeignKeyConstraintViolationException | FK violation |
| 23505 | UniqueConstraintViolationException | Unique violation |
| 40xxx | DeadlockException | Transaction rollback |
| 42xxx | SyntaxErrorException | SQL syntax errors |

### Custom Exception Handling

```php
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

try {
    $conn->insert('users', ['email' => 'duplicate@example.com']);
} catch (UniqueConstraintViolationException $e) {
    // SQLSTATE 23505 - handle duplicate
    $sqlState = $e->getSQLState(); // Returns '23505'
}
```

## Working with LIKE Expressions

Firebird has a known issue where `LIKE` parameters longer than a column’s `VARCHAR` length can cause silent query failures (zero rows returned instead of an error). This driver automatically wraps the left operand of `LIKE`/`NOT LIKE` with `CAST(column AS VARCHAR(255))` to prevent this behavior.

**Performance note:** Casting disables index usage for that predicate and can trigger full table scans on large tables. To mitigate:
- Filter by indexed columns first in the `WHERE` clause.
- Validate parameter length at the application layer when appropriate.
- See our [Best Practices Guide](docs/firebird-like-best-practices.md) for detailed strategies and examples.

**Validated across:** Firebird 3.0, 4.0, 5.0 (24/24 tests passing)

## Configuration

### Recommended: Using FirebirdConnection Wrapper (DBAL 4.x Compatible)

For forward compatibility with Doctrine DBAL 4.x, we recommend using the `FirebirdConnection` wrapper class. This approach properly handles Firebird-specific configuration options and is the **recommended way** to configure Firebird-specific settings.

**Why use FirebirdConnection?**
- Forward-compatible with DBAL 4.x (which removes `VersionAwarePlatformDriver`)
- Cleanly separates Firebird configuration from driver logic
- Proper access to both connection parameters and platform instance

#### PHP Configuration (Recommended)

```php
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\DBAL\FirebirdConnection;

$params = [
    'driver_class' => \Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver::class,
    'host'         => 'localhost',
    'dbname'       => '/path/to/database.fdb',
    'user'         => 'SYSDBA',
    'password'     => 'masterkey',
    'charset'      => 'UTF8',
    
    // Use the FirebirdConnection wrapper (DBAL 4.x compatible)
    'wrapperClass' => FirebirdConnection::class,
    
    // Firebird-specific configuration options
    'firebird'     => [
        'like_cast_length' => 500,  // Configure LIKE CAST length (default: 255)
    ],
];

$connection = DriverManager::getConnection($params);
```

#### Symfony Configuration (YAML)

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                driver_class:   Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver
                wrapper_class:  Satag\DoctrineFirebirdDriver\DBAL\FirebirdConnection
                host:           "%database_host%"
                port:           "%database_port%"
                dbname:         "%database_name%"
                user:           "%database_user%"
                password:       "%database_password%"
                charset:        "UTF-8"
                
                # Firebird-specific options (passed through wrapper)
                options:
                    firebird:
                        like_cast_length: 500  # Default: 255
```

### Configurable LIKE CAST Length

The driver wraps LIKE column operands in `CAST(column AS VARCHAR(length))` to prevent silent query failures when parameters exceed column lengths. The CAST length is configurable:

**Default:** `255` (backward compatible)  
**Range:** `1` to `8191` (Firebird VARCHAR limit)  
**Parameter:** `firebird.like_cast_length`

### Legacy Configuration (Deprecated in DBAL 3.x, Removed in DBAL 4.x)

> ⚠️ **Deprecation Warning:** The following configuration approach works in DBAL 3.x but relies on the deprecated `VersionAwarePlatformDriver` interface, which is **removed in DBAL 4.x**. Use the [FirebirdConnection wrapper](#recommended-using-firebirdconnection-wrapper-dbal-4x-compatible) above for forward compatibility.

#### Manual Configuration (Legacy)

```php
use Doctrine\DBAL\DriverManager;

$params = [
    'driver_class' => \Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver::class,
    'host'         => 'localhost',
    'dbname'       => '/path/to/database.fdb',
    'user'         => 'SYSDBA',
    'password'     => 'masterkey',
    'charset'      => 'UTF8',
    
    // Optional: Configure LIKE CAST length (default: 255)
    'firebird'     => [
        'like_cast_length' => 500,  // Increase for longer search parameters
    ],
];

$connection = DriverManager::getConnection($params);
```

#### Symfony Configuration (YAML)

This driver may be used like any other Doctrine DBAL driver in [Symfony](https://symfony.com/), e.g. with [doctrine/doctrine-bundle](https://packagist.org/packages/doctrine/doctrine-bundle). However, the `driver_class` option must be specified instead of simply `driver`. This is due to the driver not being part of the [core Doctrine DBAL library](https://github.com/doctrine/dbal).

Sample YAML configuration:

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                driver_class:   Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver
                host:           "%database_host%"
                port:           "%database_port%"
                dbname:         "%database_name%"
                user:           "%database_user%"
                password:       "%database_password%"
                charset:        "UTF-8"
                
                # Optional: Configure Firebird-specific options
                options:
                    firebird:
                        like_cast_length: 500  # Default: 255
```

**When to adjust `like_cast_length`:**
- **100-255**: Small text fields, optimized performance
- **500-1000**: Medium text fields, typical applications
- **1000-8191**: Large text fields, full-text search (slower)

**Invalid configurations throw `InvalidConfigurationException`:**
- Type mismatch (non-integer values)
- Out of range (<1 or >8191)

### CharsetMiddleware - Transparent Charset Conversion

Many Firebird databases use a non-UTF-8 wire charset (e.g. `ISO8859_1` / `WIN1252`). The `CharsetMiddleware` converts string values transparently between your PHP application encoding and the Firebird wire encoding at the DBAL driver level - no manual `iconv`/`mb_convert_encoding` calls needed in application code.

**Defaults:** `databaseEncoding: 'Windows-1252'` and `phpEncoding: 'UTF-8'` covers the most common case (Amicron ERP and other WIN1252 Firebird databases).

#### Symfony Configuration (DoctrineBundle service)

```xml
<!-- config/services.xml -->
<service id="Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware">
    <tag name="doctrine.middleware"/>
</service>
```

```yaml
# config/services.yaml
Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware:
    tags:
        - { name: doctrine.middleware }
```

#### Standalone PHP Configuration

```php
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware;

$config = new Configuration();
$config->setMiddlewares([
    new CharsetMiddleware(), // defaults: WIN1252 → UTF-8
]);

$connection = DriverManager::getConnection([
    'driver_class' => \Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver::class,
    'host'         => 'localhost',
    'dbname'       => '/path/to/database.fdb',
    'user'         => 'SYSDBA',
    'password'     => 'masterkey',
    'charset'      => 'WIN1252',
], $config);
```

#### Custom Encoding Pair

```php
// ISO-8859-1 database, UTF-8 application
$middleware = new CharsetMiddleware(databaseEncoding: 'ISO-8859-1', phpEncoding: 'UTF-8');

// UTF-8 database (no conversion needed - use identity pair)
$middleware = new CharsetMiddleware(databaseEncoding: 'UTF-8', phpEncoding: 'UTF-8');
```

**How it works:**
- `bindValue()` / execute params: PHP encoding → database encoding before sending to Firebird.
- `fetch*()` results: database encoding → PHP encoding after receiving from Firebird.
- **BLOB Transparency:** PHP resource streams are automatically extracted and transcoded.
- **`LARGE_OBJECT` support:** Parameters bound with `ParameterType::LARGE_OBJECT` are transcoded.
- Non-string values (int, float, null, bool, objects) pass through unchanged.

**Requires:** `ext-mbstring` (declared in `composer.json`)


# Testing

The project includes comprehensive test coverage across **Firebird 3.0, 4.0, and 5.0** with unit, functional, and integration tests.

## Quick Test Commands

```bash
# Run all tests for all Firebird versions
cd tests && ./phpunit-all.sh

# Run complete quality check (PHPUnit, PHPStan, Psalm, PHPCS)
cd tests && ./docker-cqc.sh

# Run tests for Firebird 3.0 only
cd tests && ./phpunit.sh

# Run specific test suite
vendor/bin/phpunit tests/Test/Unit/
vendor/bin/phpunit tests/Test/Functional/
```

## Test Coverage

- **1255+ tests** (unit, functional, integration)
- **100% pass rate** across all Firebird versions (3.0, 4.0, 5.0)
- **Windows CI** — Fully stabilized integration tests on GitHub Actions using dedicated permissive storage (`C:\firebird_tests`) to bypass `SYSTEM` account I/O restrictions.
- **CI/CD Audit** — Regular audits ensure parity between local (`docker-cqc.sh`, `act`) and remote GitHub Actions environments.
- PHPUnit 10.5, PHPStan Level 8, Psalm static analysis

## Documentation

- **[TESTING.md](docs/TESTING.md)** - Comprehensive testing guide
  - Project test structure
  - PHPUnit configuration files
  - Writing new tests
  - Namespace conventions
  - Troubleshooting guide
  - Multi-version testing
  - CI/CD integration

- **[CONTRIBUTING.md](docs/CONTRIBUTING.md)** - Contributor guidelines
  - Quick start for developers
  - Pull request requirements
  - Development workflow
  - Code style guidelines
  - Testing requirements

## Test Requirements

- **Docker & Docker Compose** - For running test environment
- **PHP 8.2+** with `ext-firebird` (php-firebird v7.3.0+)
- **Composer dependencies** - `composer install`

All tests run in Docker containers to ensure consistent environments across Firebird versions. See [TESTING.md](docs/TESTING.md) for detailed setup instructions.

# Credits

## Authors

- **Kasper Søfren**<br>
https://github.com/kafoso<br>
E-mail: soefritz@gmail.com
- **Uffe Pedersen**<br>
https://github.com/upmedia

## Acknowledgements

### https://github.com/doctrine/dbal

Fundamental Doctrine DBAL implementation. The driver and platform logic in this library is based on other implementations in the core library, largely [`\Doctrine\DBAL\Driver\PDOOracle\Driver`](https://github.com/doctrine/dbal/blob/v2.9.3/lib/Doctrine/DBAL/Driver/PDOOracle/Driver.php) and [`\Doctrine\DBAL\Platforms\OraclePlatform`](https://github.com/doctrine/dbal/blob/v2.9.3/lib/Doctrine/DBAL/Platforms/OraclePlatform.php), and their respective parent classes.

### https://github.com/helicon-os/doctrine-dbal

Whilst a great inspiration for this library - and we very much appreciate the work done by the authors - the library has a few flaws and limitations regarding the Interbase Firebird driver logic:

- It contains bugs. E.g. incorrect/insufficient handling of nested transactions and save points.
- It is lacking with respect to test coverage.
- It appears to no longer be maintained. Possibly entirely discontinued.
- It is intermingled with the core Doctrine DBAL code, making version management and code adaptation unnecessarily complicated; a nightmare, really. It is forked from https://github.com/doctrine/dbal, although, this is not specifically stated.
- It is not a Composer package (not on [https://packagist.org](https://packagist.org)).

### https://github.com/ISTDK/doctrine-dbal

A fork of https://github.com/helicon-os/doctrine-dbal with a few improvements and fixes.

### https://firebirdsql.org/

The main resource for Firebird documentation, syntax, downloads, etc.

### AI Context-Setting Statement

As an AI specialized in coding, your task is to support me, Michael Wegener, to improve the PHP Doctrine DBAL driver for the Firebird SQL Server
for which I am the current maintainer.
The Driver is not based on Firebird PDO, it is based on the PHP Firebird Extension (fbird_* functions; ibase_* aliases removed in php-firebird v7.2.0). 
You can reference the following resources for guidance:

- satag/doctrine-firebird-driver Source Code Branches  
  - https://github.com/satwareAG/doctrine-firebird-driver/tree/3.10.x supports DBAL ^3.10 (Maintenance)
  - https://github.com/satwareAG/doctrine-firebird-driver/tree/4.4.x supports DBAL ^4.4 (Active)
- Doctrine DBAL Driver documentation: [Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/4.1/reference/supporting-other-databases.html)
- Reference manuals of Firebird’s implementation of the SQL relational database language for  
  [Firebird 2.5](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref25/firebird-25-language-reference.html), 
  [Firebird 3.0](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref30/firebird-30-language-reference.html) 
  and [Firebird 4.0](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref30/firebird-30-language-reference.html) 
- PHP Firebird Extension Source: [PHP Firebird extension](https://github.com/FirebirdSQL/php-firebird)
- [German Firebird Forum](https://www.firebirdforum.de/)
-  You can download all given resources for reference.

The PHP Driver is implemented for PHP 8.2+ and should be covered with PHP Unit and Integration Tests against all supported Firebird Server Versions (3.0+).
Have an eye on modern development principles, performance and security.
