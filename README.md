Doctrine Firebird driver
---------------------------

[![codecov](https://codecov.io/github/satwareAG/doctrine-firebird-driver/graph/badge.svg?token=O66YV6TGM1)](https://codecov.io/github/satwareAG/doctrine-firebird-driver)

[Firebird](https://firebirdsql.org/) driver for the [Doctrine DBAL](https://github.com/doctrine/dbal)

# Requirements

To utilize this library in your application code, the following is required:

- Firebird Client for Server version 2.5, 3, 4 or 5
- PHP >= 8.1
- [fbird/ibase interbase](http://php.net/manual/en/book.ibase.php) firebird driver 
- [doctrine/dbal ^3.8](https://packagist.org/packages/doctrine/dbal#3.8.0)

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

## Working with LIKE Expressions

Firebird has a known issue where `LIKE` parameters longer than a column’s `VARCHAR` length can cause silent query failures (zero rows returned instead of an error). This driver automatically wraps the left operand of `LIKE`/`NOT LIKE` with `CAST(column AS VARCHAR(255))` to prevent this behavior.

**Performance note:** Casting disables index usage for that predicate and can trigger full table scans on large tables. To mitigate:
- Filter by indexed columns first in the `WHERE` clause.
- Validate parameter length at the application layer when appropriate.
- See our [Best Practices Guide](docs/firebird-like-best-practices.md) for detailed strategies and examples.

**Validated across:** Firebird 2.5, 3.0, 4.0, 5.0 (24/24 tests passing)

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

# Testing

The project includes comprehensive test coverage across **Firebird 2.5, 3.0, 4.0, and 5.0** with unit, functional, and integration tests.

## Quick Test Commands

```bash
# Run all tests for all Firebird versions
cd tests && ./phpunit-all.sh

# Run tests for Firebird 2.5 only
cd tests && ./phpunit.sh

# Run specific test suite
php vendor/bin/phpunit tests/Test/Unit/
php vendor/bin/phpunit tests/Test/Functional/
```

## Test Coverage

- **36 tests total** (24 unit, 12 functional)
- **100% pass rate** across all Firebird versions
- Multi-version compatibility validation

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
- **PHP 8.1+** with `ext-interbase`
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
The Driver is not based on Firebird PDO, it is based on PHP Firebird Extension interbase.so (using fbird_* function aliases for ibase_* functions). 
You can reference the following resources for guidance:

- satag/doctrine-firebird-driver Source Code Branches  
  - https://github.com/satwareAG/doctrine-firebird-driver/tree/3.0.x supports DBAL ^3.8
  - https://github.com/satwareAG/doctrine-firebird-driver/tree/4.0.x supports DBAL ^4.1
- Doctrine DBAL Driver documentation: [Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/4.1/reference/supporting-other-databases.html)
- Reference manuals of Firebird’s implementation of the SQL relational database language for  
  [Firebird 2.5](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref25/firebird-25-language-reference.html), 
  [Firebird 3.0](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref30/firebird-30-language-reference.html) 
  and [Firebird 4.0](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref30/firebird-30-language-reference.html) 
- PHP Firebird Extension Source: [PHP Firebird extension](https://github.com/FirebirdSQL/php-firebird)
- [German Firebird Forum](https://www.firebirdforum.de/)
-  You can download all given resources for reference.

The PHP Driver is implemented for PHP 8.1+ and should be covered with PHP Unit and Integration Tests against all Firebird Server Versions.
Have an eye on modern development principles, performance and security.
