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

## Configuration

### Manual configuration

### Symfony configuration (YAML)

This driver may be used like any other Doctrine DBAL driver in [Symfony](https://symfony.com/), e.g. with [doctrine/doctrine-bundle](https://packagist.org/packages/doctrine/doctrine-bundle). However, the `driver_class` option must be specified instead of simply `driver`. This is due to the driver not being part of the [core Doctrine DBAL library](https://github.com/doctrine/dbal).

Sample YAML configuration:

```
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
```

# Tests

## Test/development requirements

To run tests, fix bugs, provide features, etc. you can use the provided docker compose file in the /tests directory.

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

