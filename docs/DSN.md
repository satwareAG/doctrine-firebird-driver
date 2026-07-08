# DSN Format Reference

This document describes all supported DSN (Data Source Name) formats for the
`satag/doctrine-firebird-driver` package.

## URL-style DSN (Doctrine DBAL `url` parameter)

DBAL 3.x supports a `url` connection parameter parsed by `Doctrine\DBAL\Tools\DsnParser`.
Register the Firebird driver aliases before parsing:

```php
use Doctrine\DBAL\Tools\DsnParser;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

$parser = new DsnParser(['firebird' => Driver::class, 'firebird3' => Driver::class]);
$params = $parser->parse($dsn);
```

### Supported URL Formats

#### TCP/IP — standard port

```text
firebird://user:password@hostname/path/to/database.fdb
```

Example:

```text
firebird://SYSDBA:masterkey@localhost/var/db/myapp.fdb
```

#### TCP/IP — custom port

```text
firebird://user:password@hostname:port/path/to/database.fdb
```

Example:

```text
firebird://SYSDBA:masterkey@localhost:3050/var/db/myapp.fdb
```

#### TCP/IP — with query parameters

```text
firebird://user:password@hostname:port/path/to/database.fdb?charset=UTF8&role=ADMIN
```

Example with `forceNewConnection` (test/CI environments):

```text
firebird://SYSDBA:masterkey@localhost:3050/var/db/myapp.fdb?charset=UTF8&forceNewConnection=1
```

Supported query parameters:

| Parameter | Description | Default |
|---|---|---|
| `charset` | Character set (e.g. `UTF8`, `WIN1252`) | `UTF8` |
| `role` | SQL role name | none |
| `persistent` | Use persistent connections (`1`/`0`) | `0` |
| `forceNewConnection` | Force a new connection, bypassing connection reuse (`1`/`0`) | `0` |

#### Service name (Firebird service manager path)

```text
firebird://user:password@hostname/service:path/to/database.fdb
```

Example:

```text
firebird://SYSDBA:masterkey@localhost/service_mgr:/var/db/myapp.fdb
```

#### Embedded / local (no host)

For embedded Firebird or local IPC connections, omit the host:

```text
firebird:///absolute/path/to/database.fdb
```

Example:

```text
firebird:///var/db/myapp.fdb
```

## Array-style parameters (DriverManager)

The `DriverManager::getConnection()` array format:

```php
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

$connection = DriverManager::getConnection([
    'driverClass' => Driver::class,
    'host'        => 'localhost',
    'port'        => 3050,
    'dbname'      => '/var/db/myapp.fdb',
    'user'        => 'SYSDBA',
    'password'    => 'masterkey',
    'charset'     => 'UTF8',
]);
```

### Parameter Reference

| Parameter | Type | Description |
|---|---|---|
| `driverClass` | `string` | Must be `Driver::class` |
| `host` | `string` | Firebird server hostname or IP |
| `port` | `int` | Firebird port (default: `3050`) |
| `dbname` | `string` | Absolute path to `.fdb` file on the server |
| `user` | `string` | Database user (e.g. `SYSDBA`) |
| `password` | `string` | Database password |
| `charset` | `string` | Character set (default: `UTF8`) |
| `role` | `string` | SQL role name (optional) |
| `persistent` | `bool` | Use `fbird_pconnect()` instead of `fbird_connect()` |
| `forceNewConnection` | `bool` | Force a new connection via `FBIRD_CONNECT_FORCE_NEW` (default: `false`) |
| `driverOptions` | `array` | Driver-specific options (see below) |

### Driver Options (`driverOptions`)

```php
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;

$connection = DriverManager::getConnection([
    // ...
    'driverOptions' => [
        FirebirdDriver::ATTR_DOCTRINE_RETRY_ON_LOCK => true,
    ],
]);
```

| Option | Type | Description |
|---|---|---|
| `FirebirdDriver::ATTR_DOCTRINE_RETRY_ON_LOCK` | `bool` | Retry DDL/DML on lock conflict |

## Internal Connect String (`FirebirdConnectString`)

The driver internally uses `FirebirdConnectString` to build the native Firebird
connection string passed to `fbird_connect()`:

| Format | Connect string |
|---|---|
| TCP/IP standard | `hostname:/path/to/database.fdb` |
| TCP/IP custom port | `hostname/port:/path/to/database.fdb` |
| Embedded | `/path/to/database.fdb` |

## Related

- `src/Driver/Firebird/Driver/FirebirdConnectString.php` — connect string builder
- `tests/Test/Tools/DsnParserTest.php` — DSN parsing tests
- `docs/RETRY_ON_LOCK.md` — retry-on-lock option documentation
- [Firebird Connection Strings](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref40/firebird-40-language-reference.html)
