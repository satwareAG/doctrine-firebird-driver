Doctrine Firebird driver
---------------------------

Firebird driver for the [Doctrine DBAL](https://github.com/doctrine/dbal) ([v2.13.*](https://packagist.org/packages/doctrine/orm#v2.13.0)).

This library is completely separate (i.e. abstracted away) from the core Doctrine DBAL library. I.e. it is fully a **plug-in**.

# Requirements

To utilize this library in your application code, the following is required:

- [Firebird](https://firebirdsql.org/) version 2.5.* & 3.*
- PHP >= 7.4
- PHP extensions<sup>1</sup>:
  - [ibase](http://php.net/manual/en/book.ibase.php)
  - [mbstring](http://php.net/manual/en/book.mbstring.php)
  - xml
- [doctrine/dbal: ^2.9.3](https://packagist.org/packages/doctrine/dbal#v2.9.3)

<sup>1</sup> Only needed on remote/guest OS, e.g. a remote Ubuntu server or a VM installed through Docker, Vagrant, or XAMPP.

# License & Disclaimer

See [LICENSE](LICENSE) file. Basically: Use this library at your own risk.

## Limitations of Schema Manager

This library does **_not_ fully support generation through the Schema Manager**, i.e.:

1. Generation of database tables, views, etc. from entities.
2. Generation of entities from database tables, views, etc.

Reasons for not investing time in schema generation include that Firebird does not allow renaming of tables, which in turn makes automated schema updates annoying and over-complicated. Better results are probably achieved by writing manual migrations.

# Installation

Via Composer ([`kafoso/doctrine-firebird-driver`](https://packagist.org/packages/kafoso/doctrine-firebird-driver)):

    composer install kafoso/doctrine-firebird-driver

Via Github:

    git clone git@github.com:kafoso/doctrine-firebird-driver.git

## Configuration

### Manual configuration

For example of configuration in PHP, see [`tests/tests/Test/Integration/AbstractIntegrationTest.php`](tests/tests/Test/Integration/AbstractIntegrationTest.php) (in the method `setUp`).

Additional help may be found at: https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/index.html

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

To run tests, fix bugs, provide features, etc. the following is required:

- A system capable of running a virtual machine with [ubuntu/xenial64](https://app.vagrantup.com/ubuntu/boxes/xenial64).
- [Virtualbox](https://www.virtualbox.org/) >= 5.1.0
- [Vagrant](https://www.vagrantup.com/) >= 2.0.0

You may of course install everything manually using your own VM setup. For help and a stack list (required apt-get packages), see the [Vagrantfile](Vagrantfile).

## Installation to run tests

A few steps are required to run all tests. Unit tests ([tests/tests/Test/Unit](tests/tests/Test/Unit)) will run on all environments. However, integration tests ([tests/tests/Test/Integration](tests/tests/Test/Integration)) require the following because they test against a running Firebird database in the VM:

1. `vagrant up`<br>
Install/provision the VM.
2. `vagrant ssh`
3. `sudo su`
4. `apt-get -y install firebird2.5-superclassic`<br>
 You will be prompted to install the database using a password. Please provide this password: `88fb9f307125cc397f70e59c749715e1`. It is re-used when connecting through the DBAL later on.
5. `dpkg-reconfigure firebird2.5-superclassic`<br>
Starts the Firebird database server. Provide same password as above.

Reference: https://firebirdsql.org/manual/ubusetup.html

## Running tests

Due to the database being created by the PHP bootstrap script on the fly, `root` is needed to run the tests **on the VM**.

1. `vagrant ssh`
2. `sudo su`
3. `apt-get install zip -y` (for when installing composer packages)
4. Install composer (on guest OS). Follow these instructions: https://getcomposer.org/download/
5. `mv composer.phar /usr/bin/composer`
6. `cd /var/git/kafoso/doctrine-firebird-driver`
7. `composer install`<sup>1</sup>
8. `cd /var/git/kafoso/doctrine-firebird-driver/tests`
9. `../bin/phpunit tests`

<sup>1</sup> Composer will say you shouldn't run it as root/super user. This is techically true, but it's fine in the VM.

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

Supporting Other Databases
To support a database which is not currently shipped with Doctrine you have to implement the following interfaces and abstract classes:

\Doctrine\DBAL\Driver\Connection
\Doctrine\DBAL\Driver\Statement
\Doctrine\DBAL\Driver
\Doctrine\DBAL\Platforms\AbstractPlatform
\Doctrine\DBAL\Schema\AbstractSchemaManager
For an already supported platform but unsupported driver you only need to implement the first three interfaces, since the SQL Generation and Schema Management is already supported by the respective platform and schema instances. You can also make use of several Abstract unit tests in the \Doctrine\DBAL\Tests package to check if your platform behaves like all the others which is necessary for SchemaTool support, namely:

\Doctrine\DBAL\Tests\Platforms\AbstractPlatformTestCase
\Doctrine\DBAL\Tests\Functional\Schema\AbstractSchemaManagerTestCase
We would be very happy if any support for new databases would be contributed back to Doctrine to make it an even better product.

Implementation Steps in Detail
Add your driver shortcut to the Doctrine\DBAL\DriverManager class.
Make a copy of tests/dbproperties.xml.dev and adjust the values to your driver shortcut and testdatabase.
Create three new classes implementing \Doctrine\DBAL\Driver\Connection, \Doctrine\DBAL\Driver\Statement and Doctrine\DBAL\Driver. You can take a look at the Doctrine\DBAL\Driver\OCI8 driver.
You can run the test suite of your new database driver by calling phpunit. You can set your own settings in the phpunit.xml file.
Start implementing AbstractPlatform and AbstractSchemaManager. Other implementations should serve as good examples.

### AI Context-Setting Statement

As an AI specialized in coding, your task is to improve an existing PHP Doctrine DBAL driver for the Firebird SQL databases, 
specifically versions 2.5 and 3.0. The Driver is not based on Firebird PDO, it is based on PHP Firebird Extension interbase.so (using fbird_* function aliases for ibase_* functions). You can reference the following resources for guidance:

- satag/doctrine-firebird-driver Source Code Branch with the current development: https://github.com/satwareAG/doctrine-firebird-driver/tree/testdbalupgrade Remark: This is the most important source of information. The Git Repository is updated on daily bases.
- Doctrine DBAL Driver documentation: [Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/4.1/reference/supporting-other-databases.html)
- Firebird SQL 2.5 language reference: [Firebird 2.5](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref25/firebird-25-language-reference.html)
- Firebird SQL 3.0 language reference: [Firebird 3.0](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref30/firebird-30-language-reference.html)
- PHP Firebird Extension Source: [PHP Firebird extension](https://github.com/FirebirdSQL/php-firebird)

The PHP Driver will be implemented for PHP 8.1+ and should be covered with PHP Unit Tests. Download all given resources for reference.
