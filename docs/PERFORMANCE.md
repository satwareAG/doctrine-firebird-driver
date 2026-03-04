# Performance Guide

> **Primary target:** PHP 8.4 + Firebird 3.0 (Amicron ERP production)

## Table of Contents

- [Coverage Tool: PCOV vs Xdebug](#coverage-tool)
- [Prepared Statement Reuse](#prepared-statement-reuse)
- [Batch Insert Strategies](#batch-insert-strategies)
- [IBatch API (Firebird 4.0+)](#ibatch-api)
- [BLOB Streaming](#blob-streaming)
- [Connection Pooling](#connection-pooling)
- [Date/Time Conversions](#datetime-conversions)
- [LIKE Optimization](#like-optimization)
- [Query Profiling](#query-profiling)

---

## Coverage Tool

PCOV is the recommended coverage driver — nearly zero runtime overhead compared to Xdebug.

| Driver | Overhead | Line Coverage | Branch Coverage |
|--------|----------|---------------|-----------------|
| **PCOV** | ~2-5% | ✅ | ❌ |
| Xdebug 3 | ~30-50% | ✅ | ✅ |
| none | 0% | ❌ | ❌ |

**Install PCOV in Dockerfile:**

```dockerfile
RUN pecl install pcov && docker-php-ext-enable pcov
```

**PHPUnit with PCOV:**

```bash
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text
```

---

## Prepared Statement Reuse

Firebird handles prepared statements efficiently. Reuse them in tight loops — avoid
re-preparing the same SQL on every iteration:

```php
// ✅ Good — prepare once, execute many
$stmt = $conn->prepare('INSERT INTO IMPORT (ID, NAME, WERT) VALUES (?, ?, ?)');
$conn->beginTransaction();
foreach ($rows as $row) {
    $stmt->bindValue(1, $row['id']);
    $stmt->bindValue(2, $row['name']);
    $stmt->bindValue(3, $row['wert']);
    $stmt->execute();
}
$conn->commit();

// ❌ Bad — re-prepares on every row (slow)
foreach ($rows as $row) {
    $conn->executeStatement('INSERT INTO IMPORT (ID, NAME, WERT) VALUES (?, ?, ?)', array_values($row));
}
```

**Benchmark**: Prepared statement reuse is ~3–5× faster than per-row `executeStatement()` for
1000+ rows on Firebird 3.0.

---

## Batch Insert Strategies

### Firebird 3.0 (Primary — row-by-row with prepared statement)

For FB3, the fastest strategy is **single transaction + prepared statement**:

```php
$stmt = $conn->prepare('INSERT INTO STAGING (ID, NAME) VALUES (?, ?)');
$conn->beginTransaction();
try {
    foreach ($largeDataset as [$id, $name]) {
        $stmt->execute([$id, $name]);
    }
    $conn->commit();
} catch (\Throwable $e) {
    $conn->rollBack();
    throw $e;
}
```

**Performance characteristics (FB3):**

| Strategy | 1k rows | 10k rows | Notes |
|----------|---------|----------|-------|
| Single tx + prepared stmt | ~80ms | ~800ms | Recommended |
| Per-row autocommit | ~400ms | ~4000ms | 5× slower |
| IBatch (FB4 only) | N/A | N/A | Not available on FB3 |

### Firebird 4.0+ (IBatch API — 10–12× faster than row-by-row)

```php
/** @var \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection $nativeConn */
$nativeConn = $conn->getNativeConnection();

// Version-guarded: only use on FB4+
if (version_compare($conn->getServerVersion(), '4.0', '>=')) {
    $result = $nativeConn->executeBatch(
        'INSERT INTO STAGING (ID, NAME) VALUES (?, ?)',
        array_map(fn($row) => [$row['id'], $row['name']], $largeDataset)
    );
    echo 'Inserted: ' . $result->count() . PHP_EOL;
} else {
    // FB3 fallback
    $stmt = $conn->prepare('INSERT INTO STAGING (ID, NAME) VALUES (?, ?)');
    $conn->beginTransaction();
    foreach ($largeDataset as $row) {
        $stmt->execute([$row['id'], $row['name']]);
    }
    $conn->commit();
}
```

---

## IBatch API

> ⚠️ **Requires Firebird Server 4.0+.** Automatically rejected on FB3 with `DriverException`.

The IBatch protocol sends all INSERT rows to Firebird in a single network round-trip.

**Performance (FB4+):**

| Rows | Row-by-row | IBatch | Speedup |
|------|-----------|--------|---------|
| 100 | ~8ms | ~1ms | 8× |
| 1000 | ~80ms | ~7ms | 11× |
| 10000 | ~800ms | ~65ms | 12× |

**Partial failure handling:**

```php
$result = $nativeConn->executeBatch($sql, $rows);

if ($result->hasErrors()) {
    foreach ($result->getErrors() as $error) {
        // Row index of failed row + error details
        echo sprintf('Row %d failed: %s%s', $error->getRow(), $error->getMessage(), PHP_EOL);
    }
}
```

---

## BLOB Streaming

For large BLOBs (documents, images), stream rather than load into memory:

```php
// Read BLOB as stream (avoids loading entire blob into PHP memory)
$stmt = $conn->prepare('SELECT DOCUMENT FROM ARTIKEL WHERE ARTNR = ?');
$result = $stmt->execute(['ART-001']);
$row = $result->fetchAssociative();

// Driver returns BLOB content as string; for very large BLOBs
// consider chunked reads via native fbird_open_blob2()
$content = $row['DOCUMENT'];
file_put_contents('/tmp/document.pdf', $content);
```

**Write large BLOB (streaming):**

```php
$handle = fopen('/tmp/large.pdf', 'rb');
$content = stream_get_contents($handle);
fclose($handle);

$conn->executeStatement(
    'UPDATE ARTIKEL SET DOCUMENT = ? WHERE ARTNR = ?',
    [$content, 'ART-001', \Doctrine\DBAL\ParameterType::LARGE_OBJECT]
);
```

---

## Connection Pooling

Firebird Classic Server creates a new process per connection — expensive for high-concurrency
PHP applications. Mitigation strategies:

| Strategy | Use Case | Notes |
|----------|----------|-------|
| **Persistent connection** (`fbird_pconnect`) | Long-lived PHP-FPM workers | Warning: Firebird Classic only — use SuperServer for actual pooling |
| **Connection multiplexer** | High concurrency | PgBouncer-equivalent: not native to Firebird, use Firebird SuperServer/SuperClassic |
| **Queue-based serialization** | Batch imports | One connection, many jobs via message queue |

**Symfony + doctrine: prefer `server_version` to avoid auto-detection queries**

```yaml
doctrine:
    dbal:
        connections:
            firebird:
                # Declare version to skip the "SELECT rdb$get_context(...)" on connect
                server_version: '3.0'
```

Declaring `server_version` skips the version detection query on every DBAL connection,
saving one round-trip per request.

---

## Date/Time Conversions

The `FBIRD_FETCH_DATE_OBJ` flag (php-firebird v7.0.0+) returns dates as `DateTimeImmutable`
instead of formatted strings, eliminating PHP-side parsing overhead:

```php
// With FBIRD_FETCH_DATE_OBJ (php-firebird v7.0.0+):
// DATE columns return DateTimeImmutable directly — no strtotime() overhead

// The driver uses native type conversion — no action needed from user code
// DateTimeImmutable values are returned automatically for DATE/TIME/TIMESTAMP columns
```

**Benchmark**: For result sets with many date columns, native `DateTimeImmutable` return
is ~15% faster than string parsing on PHP 8.4 (JIT enabled).

---

## LIKE Optimization

The driver automatically wraps LIKE column operands in
`CAST(column AS VARCHAR(N))` to prevent silent failures. This **disables index usage**.

To keep LIKE predicates indexed:

```php
// ✅ Filter by indexed column FIRST to reduce the LIKE scan
$rows = $conn->fetchAllAssociative(
    'SELECT * FROM ARTIKEL WHERE ARTNR >= ? AND ARTBEZ LIKE ?',
    ['A000000', 'Schraube%']  // Range filter on indexed ARTNR reduces LIKE rows
);

// ❌ LIKE on large table without prefix filter = full table scan
$rows = $conn->fetchAllAssociative(
    'SELECT * FROM ARTIKEL WHERE ARTBEZ LIKE ?',
    ['%Schraube%']
);
```

**Configure CAST length** based on your longest LIKE parameter (smaller = faster):

```php
'firebird' => ['like_cast_length' => 100]  // instead of default 255
```

See [firebird-like-best-practices.md](firebird-like-best-practices.md) for detailed strategies.

---

## Query Profiling

Use `fbird_connection_info()` / `getConnectionInfo()` to measure per-connection I/O:

```php
/** @var \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection $nativeConn */
$nativeConn = $conn->getNativeConnection();

$before = $nativeConn->getConnectionInfo();
// ... execute queries ...
$after  = $nativeConn->getConnectionInfo();

if ($before instanceof \Firebird\DbInfo && $after instanceof \Firebird\DbInfo) {
    echo 'Page reads: ' . ($after->getReads()   - $before->getReads()) . PHP_EOL;
    echo 'Page writes: ' . ($after->getWrites() - $before->getWrites()) . PHP_EOL;
    echo 'Row fetches: ' . ($after->getFetches() - $before->getFetches()) . PHP_EOL;
}
```

> **FB version:** Requires Firebird 3.0+.
