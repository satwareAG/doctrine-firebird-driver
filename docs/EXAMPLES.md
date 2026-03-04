# Usage Examples

> **Primary target:** PHP 8.4 + Firebird 3.0 (Amicron ERP production environment)

## Table of Contents

- [Connection Setup](#connection-setup)
- [Basic Queries](#basic-queries)
- [Transactions](#transactions)
- [Named Parameters](#named-parameters)
- [BLOB Handling](#blob-handling)
- [Exception Mode](#exception-mode)
- [Autonomous Transactions (executeAuto)](#autonomous-transactions)
- [CQRS / Audit Pattern (queryInTransaction)](#cqrs--audit-pattern)
- [Connection Info & Diagnostics](#connection-info--diagnostics)
- [Symfony Bundle Integration](#symfony-bundle-integration)
- [Firebird 4.0+ Features (IBatch)](#firebird-40-features-ibatch)

---

## Connection Setup

### Standalone PHP (Recommended)

```php
use Doctrine\DBAL\DriverManager;
use Satag\DoctrineFirebirdDriver\DBAL\FirebirdConnection;

$conn = DriverManager::getConnection([
    'driver_class' => \Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver::class,
    'wrapperClass' => FirebirdConnection::class,
    'host'         => 'localhost',
    'dbname'       => '/var/firebird/amicron.fdb',
    'user'         => 'SYSDBA',
    'password'     => 'masterkey',
    'charset'      => 'UTF8',
    'firebird'     => [
        'like_cast_length' => 500,  // adjust for your LIKE search needs
    ],
]);
```

### Symfony (config/packages/doctrine.yaml)

```yaml
doctrine:
    dbal:
        default_connection: firebird
        connections:
            firebird:
                driver_class:  Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver
                wrapper_class: Satag\DoctrineFirebirdDriver\DBAL\FirebirdConnection
                host:          '%env(DB_HOST)%'
                dbname:        '%env(DB_NAME)%'
                user:          '%env(DB_USER)%'
                password:      '%env(DB_PASSWORD)%'
                charset:       UTF8
                options:
                    firebird:
                        like_cast_length: 500
```

### Environment Variables (.env)

```dotenv
DB_HOST=localhost
DB_NAME=/var/firebird/amicron.fdb
DB_USER=SYSDBA
DB_PASSWORD=masterkey
```

---

## Basic Queries

### SELECT — fetchAllAssociative

```php
// Simple SELECT
$rows = $conn->fetchAllAssociative(
    'SELECT KDNR, KDNAME, EMAIL FROM ADRESSEN WHERE AKTIV = 1 ORDER BY KDNAME'
);

foreach ($rows as $row) {
    echo $row['KDNR'] . ': ' . $row['KDNAME'] . PHP_EOL;
}
```

### SELECT — fetchOne / fetchAssociative

```php
// Single value
$count = $conn->fetchOne('SELECT COUNT(*) FROM ADRESSEN');

// Single row
$customer = $conn->fetchAssociative(
    'SELECT * FROM ADRESSEN WHERE KDNR = ?',
    [42]
);
```

### INSERT / UPDATE / DELETE

```php
// INSERT
$affected = $conn->executeStatement(
    'INSERT INTO ADRESSEN (KDNR, KDNAME, EMAIL) VALUES (?, ?, ?)',
    [1001, 'ACME Corp', 'info@acme.example']
);

// UPDATE
$affected = $conn->executeStatement(
    'UPDATE ADRESSEN SET EMAIL = ? WHERE KDNR = ?',
    ['new@acme.example', 1001]
);

// DELETE
$affected = $conn->executeStatement(
    'DELETE FROM ADRESSEN WHERE KDNR = ?',
    [1001]
);
```

### Generator / Auto-increment (Firebird sequences)

```php
// Get next sequence value before INSERT
$nextId = $conn->fetchOne("SELECT NEXT VALUE FOR GEN_ADRESSEN FROM RDB\$DATABASE");
$conn->executeStatement(
    'INSERT INTO ADRESSEN (KDNR, KDNAME) VALUES (?, ?)',
    [$nextId, 'New Customer']
);
```

---

## Transactions

### Explicit Transaction

```php
$conn->beginTransaction();
try {
    $conn->executeStatement(
        'INSERT INTO AUFTRAG (AUFNR, KDNR, DATUM) VALUES (?, ?, CURRENT_DATE)',
        [5001, 42]
    );
    $conn->executeStatement(
        'INSERT INTO ATRPOS (AUFNR, POSNR, ARTIKEL) VALUES (?, ?, ?)',
        [5001, 1, 'ART-001']
    );
    $conn->commit();
} catch (\Throwable $e) {
    $conn->rollBack();
    throw $e;
}
```

### Nested Transactions (Savepoints) — FB 3.0+

```php
$conn->beginTransaction();          // level 1 — real transaction
try {
    $conn->executeStatement('INSERT INTO AUFTRAG ...', [...]);

    $conn->beginTransaction();      // level 2 — savepoint
    try {
        $conn->executeStatement('INSERT INTO ATRPOS ...', [...]);
        $conn->commit();            // release savepoint
    } catch (\Throwable $e) {
        $conn->rollBack();          // rollback to savepoint only
        // outer transaction still active
    }

    $conn->commit();                // commit outer transaction
} catch (\Throwable $e) {
    $conn->rollBack();
    throw $e;
}
```

---

## Named Parameters

```php
// Doctrine DBAL named parameter syntax
$rows = $conn->fetchAllAssociative(
    'SELECT * FROM ADRESSEN WHERE AKTIV = :active AND PLZ LIKE :zip',
    ['active' => 1, 'zip' => '674%']
);
```

> **Note:** The driver converts `:named` to `?` positional parameters internally,
> fully supporting Firebird's native parameterized queries.

---

## BLOB Handling

### Read BLOB

```php
$stmt = $conn->prepare('SELECT BEMERKUNG FROM ADRESSEN WHERE KDNR = ?');
$stmt->bindValue(1, 42);
$result = $stmt->execute();
$row = $result->fetchAssociative();

// BLOBs are returned as string content (driver handles streaming)
$text = $row['BEMERKUNG'];
```

### Write BLOB

```php
$largeText = file_get_contents('/path/to/notes.txt');

$conn->executeStatement(
    'UPDATE ADRESSEN SET BEMERKUNG = ? WHERE KDNR = ?',
    [$largeText, 42, \Doctrine\DBAL\ParameterType::LARGE_OBJECT]
);
```

---

## Exception Mode

The driver enables **Exception Mode** automatically on connection (php-firebird v7.0.0+, FB 3.0+).
Firebird API errors throw `Firebird\Exception` instead of returning `false`, with SQL State support:

```php
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;

try {
    $conn->executeStatement('INSERT INTO ADRESSEN (KDNR) VALUES (?)', [42]);
} catch (DriverException $e) {
    echo 'Firebird error: ' . $e->getMessage() . PHP_EOL;
    echo 'Error code: ' . $e->getCode() . PHP_EOL;
    // SQL State available via getSqlState() on the underlying Firebird\Exception
}
```

---

## Autonomous Transactions

`executeAuto()` runs a statement in its own auto-committed transaction — useful for logging
that must persist regardless of the main transaction's outcome:

```php
/** @var \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection $nativeConn */
$nativeConn = $conn->getNativeConnection();

// This INSERT commits immediately, independent of any outer transaction
$nativeConn->executeAuto(
    'INSERT INTO AUDIT_LOG (AKTION, BENUTZER, TS) VALUES (?, ?, CURRENT_TIMESTAMP)',
    ['IMPORT_START', 'SYSTEM']
);
```

> **FB version:** Requires Firebird 3.0+. Not available on FB 2.5.

---

## CQRS / Audit Pattern

`queryInTransaction()` executes a query in a **specific independent transaction** — unique to
php-firebird. This enables CQRS patterns where reads/writes use different isolation levels,
or audit inserts that survive main transaction rollback:

```php
/** @var \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection $nativeConn */
$nativeConn = $conn->getNativeConnection();

// Create a separate READ COMMITTED transaction for audit logging
$auditTx = $nativeConn->createIndependentTransaction()
    ->readCommitted()
    ->wait(5)
    ->start();

$conn->beginTransaction();
try {
    // Main work in DBAL-managed transaction
    $conn->executeStatement('UPDATE ARTIKEL SET PREIS = ? WHERE ARTNR = ?', [19.99, 'ART-001']);

    // Audit log in separate transaction — survives even if main tx rolls back
    $nativeConn->queryInTransaction(
        $auditTx,
        'INSERT INTO AUDIT_LOG (AKTION, DETAILS, TS) VALUES (?, ?, CURRENT_TIMESTAMP)',
        ['PREIS_AENDERUNG', 'ART-001: 19.99']
    );
    $auditTx->commit();

    $conn->commit();
} catch (\Throwable $e) {
    // Audit tx already committed — log entry preserved even after rollback
    $conn->rollBack();
    throw $e;
}
```

---

## Connection Info & Diagnostics

```php
/** @var \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection $nativeConn */
$nativeConn = $conn->getNativeConnection();

// Get connection statistics (FB 3.0+)
$info = $nativeConn->getConnectionInfo();
if ($info instanceof \Firebird\DbInfo) {
    echo 'Reads: ' . $info->getReads() . PHP_EOL;
    echo 'Writes: ' . $info->getWrites() . PHP_EOL;
    echo 'Fetches: ' . $info->getFetches() . PHP_EOL;
}

// List attachments blocking a table (requires SYSDBA)
$blockers = $nativeConn->listTableBlockers('ARTIKEL');
foreach ($blockers as $b) {
    echo 'Blocked by attachment: ' . $b['ATTACHMENT_ID'] . PHP_EOL;
}

// Kill a blocking attachment (requires SYSDBA)
$nativeConn->killAttachment(12345);
```

---

## Symfony Bundle Integration

### doctrine.yaml (with satag/amicron-entity-bundle)

```yaml
doctrine:
    dbal:
        connections:
            amicron:
                driver_class:  Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver
                wrapper_class: Satag\DoctrineFirebirdDriver\DBAL\FirebirdConnection
                host:          '%env(AMICRON_DB_HOST)%'
                dbname:        '%env(AMICRON_DB_PATH)%'
                user:          '%env(AMICRON_DB_USER)%'
                password:      '%env(AMICRON_DB_PASSWORD)%'
                charset:       WIN1252     # Amicron ERP default encoding
    orm:
        entity_managers:
            amicron:
                connection: amicron
                mappings:
                    AmicronEntityBundle:
                        is_bundle: true
                        type: attribute
```

### Entity Example (compatible with satag/amicron-entity-bundle)

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ADRESSEN')]
class Adresse
{
    #[ORM\Id]
    #[ORM\Column(name: 'KDNR', type: 'integer')]
    private int $kdnr;

    #[ORM\Column(name: 'KDNAME', type: 'string', length: 80)]
    private string $kdname;

    #[ORM\Column(name: 'EMAIL', type: 'string', length: 100, nullable: true)]
    private ?string $email = null;

    // PHP 8.4 property hooks (zero boilerplate)
    public int $id {
        get => $this->kdnr;
    }
}
```

---

## Firebird 4.0+ Features (IBatch)

> ⚠️ **Requires Firebird Server 4.0+.** NOT available in production (FB 3.0 Amicron ERP).
> Use for Firebird 4.0/5.0 deployments only. Version check is automatic.

### IBatch Bulk INSERT (10–12× faster than row-by-row)

```php
/** @var \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection $nativeConn */
$nativeConn = $conn->getNativeConnection();

// createBatch() throws DriverException if server < 4.0
$result = $nativeConn->executeBatch(
    'INSERT INTO IMPORT_STAGING (ID, NAME, WERT) VALUES (?, ?, ?)',
    [
        [1, 'Item A', 10.50],
        [2, 'Item B', 20.00],
        [3, 'Item C',  5.99],
        // ... thousands of rows
    ]
);

echo 'Inserted: ' . $result->count() . ' rows' . PHP_EOL;

// Check for partial failures (IBatch continues on error by default)
foreach ($result->getErrors() as $error) {
    echo 'Row ' . $error->getRow() . ' failed: ' . $error->getMessage() . PHP_EOL;
}
```

### Version-Guarded Bulk Insert (FB3 safe)

```php
$serverVersion = $conn->getServerVersion();  // e.g. "3.0.10" or "4.0.0"

if (version_compare($serverVersion, '4.0', '>=')) {
    // Fast path: IBatch API
    $nativeConn->executeBatch($sql, $rows);
} else {
    // FB3-safe: row-by-row with prepared statement
    $stmt = $conn->prepare($sql);
    $conn->beginTransaction();
    foreach ($rows as $row) {
        $stmt->executeStatement($row);
    }
    $conn->commit();
}
```
