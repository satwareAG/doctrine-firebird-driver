# Retry-on-Lock Middleware

The Firebird driver includes a built-in retry mechanism for lock conflicts via the
`ATTR_DOCTRINE_RETRY_ON_LOCK` driver option.

## Background

Firebird uses a multi-version concurrency control (MVCC) model. In `commit_retaining`
(auto-commit) mode, a transaction retains its locks after each statement. This means
that a `SELECT` statement can hold a shared lock on a table, preventing a subsequent
`DROP TABLE` or `ALTER TABLE` from acquiring the exclusive lock it needs.

This manifests as a Firebird error:

```text
object TABLE_NAME is in use
```

## The `ATTR_DOCTRINE_RETRY_ON_LOCK` Option

The `FirebirdDriver::ATTR_DOCTRINE_RETRY_ON_LOCK` constant enables automatic retry
logic. When a lock conflict is detected, the driver:

1. Commits the current transaction (releasing all shared locks)
2. Retries the failed statement

### Configuration

```php
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;

$connection = DriverManager::getConnection([
    'driverClass' => \Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver::class,
    'host'        => 'localhost',
    'port'        => 3050,
    'dbname'      => '/var/db/myapp.fdb',
    'user'        => 'SYSDBA',
    'password'    => 'masterkey',
    'driverOptions' => [
        FirebirdDriver::ATTR_DOCTRINE_RETRY_ON_LOCK => true,
    ],
]);
```

### Default Behaviour

| Option value | Behaviour |
|---|---|
| `true` | Retry DDL/DML on lock conflict (commit + retry once) |
| `false` (default) | Throw exception immediately on lock conflict |

## Use Cases

### Schema Migrations

When running Doctrine Migrations against a live Firebird database, `commit_retaining`
can cause lock conflicts between migration steps. Enabling retry-on-lock allows
migrations to proceed without manual intervention:

```php
// In your migration bootstrap
$connection = DriverManager::getConnection([
    // ...
    'driverOptions' => [
        FirebirdDriver::ATTR_DOCTRINE_RETRY_ON_LOCK => true,
    ],
]);

$migrator = new Migrator($connection);
$migrator->migrate();
```

### Schema Manager Operations

The Firebird Schema Manager (`FirebirdSchemaManager`) uses DDL statements internally.
Enabling retry-on-lock prevents spurious "object in use" errors during:

- `dropTable()` after a `SELECT` on the same table
- `alterTable()` when the table has active read locks
- `createIndex()` on a table with pending transactions

## Implementation Status

> **Note:** The `ATTR_DOCTRINE_RETRY_ON_LOCK` constant is defined in `FirebirdDriver`
> and the retry logic is tracked in the driver's connection layer. The feature is
> currently in the implementation phase. See `RetryOnLockTest.php` for the planned
> test scenarios.

## Related

- `src/Driver/FirebirdDriver.php` — constant definition
- `tests/Test/Functional/Driver/Firebird/RetryOnLockTest.php` — planned tests
- Firebird documentation: [Transaction Management](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref40/firebird-40-language-reference.html#fblangref40-transacs)
