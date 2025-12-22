# First Citizen Excellence Plan: Doctrine Firebird Driver

**Date**: 2025-12-07 (Updated: 2025-12-22)  
**Status**: Active Development Plan  
**Primary Goal**: Best-in-class Doctrine DBAL Firebird driver using php-firebird extension  
**Strategic Vision**: "First Citizen" status in Doctrine ecosystem

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Research Findings (2025)](#research-findings-2025)
3. [Version Compatibility Matrix](#version-compatibility-matrix)
4. [php-firebird OO Wrapper Integration](#php-firebird-oo-wrapper-integration)
5. [Transaction-Aware Query Architecture](#transaction-aware-query-architecture)
6. [Savepoint Implementation](#savepoint-implementation)
7. [IBatch Bulk Operations](#ibatch-bulk-operations)
8. [DBAL Multi-Version Support](#dbal-multi-version-support)
9. [Firebird Type Mapping](#firebird-type-mapping)
10. [Platform Optimization Strategy](#platform-optimization-strategy)
11. [PHP Firebird Extension Improvements](#php-firebird-extension-improvements)
12. [Implementation Roadmap](#implementation-roadmap)
13. [Success Metrics](#success-metrics)

---

## Executive Summary

### Mission Statement

Transform the Doctrine Firebird Driver from using legacy `ext-interbase` to the modern **php-firebird** (`ext-firebird`) extension, leveraging its unique features to create the best Firebird driver in the PHP ecosystem.

### Key Differentiators

| Feature | PDO_Firebird | ext-interbase | **php-firebird** |
|---------|--------------|---------------|------------------|
| Transaction-Aware Queries | ❌ | ❌ | ✅ `fbird_query_params_tx()` |
| Savepoint Support | ❌ Fails | ⚠️ Limited | ✅ Full OO API |
| IBatch API (FB 4.0+) | ❌ | ❌ | ✅ `Batch` class |
| OO Wrapper | ❌ | ❌ | ✅ `Database`, `Transaction` |
| BLOB Memory Leaks | ⚠️ Known issue | ⚠️ Possible | ✅ Proper cleanup |

### Current State Assessment

| Metric | Current | Target | Gap |
|--------|---------|--------|-----|
| Tests | **1415+** | 1500+ | +85 remaining |
| Coverage | **~88%** | 95%+ | +7% |
| PHPStan Level | 8 | 8 (strict-rules) | ✅ |
| Extension | ext-interbase | **ext-firebird** | ⚠️ Migration needed |
| DBAL Versions | ^3.10 | 3.10, 4.4, 4.5-dev, 5.0-dev | Multi-branch |

---

## Research Findings (2025)

### Source: DeepWiki - doctrine/dbal Driver Architecture

**DBAL 4.x/5.x Breaking Changes:**
- `Driver::connect()` signature changed - all params in single `$params` array
- `Driver` classes marked `final` - cannot be extended
- `ServerInfoAwareConnection` merged into base `Connection` interface
- `ResultStatement` renamed to `Result`
- `Statement::execute()` returns `Result` object (not bool)
- Methods `fetch()`, `fetchAll()` → `fetchNumeric()`, `fetchAssociative()`, `fetchOne()`
- `getExceptionConverter()` required (replaces `convertException()`)

**SchemaManager Changes:**
- `getList*SQL()` methods removed (now internal)
- Must implement: `selectDatabaseColumns()`, `selectDatabaseIndexes()`, `selectDatabaseForeignKeys()`
- No `$database` parameter in `list*()` methods

### Source: Perplexity - 2025 PHP Database Best Practices

**Key Recommendations:**
1. **Exception Mode**: Use `PDO::ERRMODE_EXCEPTION` pattern → php-firebird needs `fbird_set_exception_mode()` (Issue #15)
2. **Native Prepared Statements**: Disable emulation for security/performance
3. **BLOB Handling**: Use streams with `PDO::PARAM_LOB` → php-firebird needs stream support (Issue #16)
4. **Batch Operations**: Loop over `execute()` with prepared statements → IBatch API for Firebird

### Source: GitHub Issues - Known Firebird Driver Problems

**Doctrine DBAL #2194 Documents:**
- ❌ BLOB memory leaks in PDO Firebird
- ❌ Strange transaction handling in PDO Firebird
- ❌ Savepoints fail despite Firebird supporting them
- ❌ 64-bit integer sign issues (negative becomes unsigned)
- ❌ Identifier case handling (Firebird uppercases unquoted names)

**php-firebird Solutions:**
- ✅ Transaction-aware queries via `fbird_query_params_tx()`
- ✅ Full savepoint support in OO wrapper
- ✅ Proper resource cleanup in destructors

---

## Version Compatibility Matrix

### PHP Version Support

| PHP Version | Status | Polyfill Required | Notes |
|-------------|--------|-------------------|-------|
| 8.1 | ✅ Primary baseline | None | EOL Nov 2025, plan migration |
| 8.2 | ✅ Supported | `symfony/polyfill-php82` | DBAL 4.x minimum |
| 8.3 | ✅ Supported | `symfony/polyfill-php83` | `#[\Override]` |
| 8.4 | ✅ Supported | `symfony/polyfill-php84` | Array functions |
| 8.5 | 🔜 Future | `symfony/polyfill-php85` | When stable |

### Firebird Version Support

| Firebird | PHP Extension | Status | Key Features |
|----------|---------------|--------|--------------|
| 2.5 | ext-firebird | ⚠️ Legacy | Classic syntax, generators |
| 3.0 | ext-firebird | ✅ Supported | IDENTITY, BOOLEAN native |
| 4.0 | ext-firebird | ✅ Primary | DECFLOAT, INT128, IBatch, TZ |
| 5.0 | ext-firebird | ✅ Supported | SKIP LOCKED, parallel, compiled stmt cache |
| 6.0 | ext-firebird | 🔜 Future | Parallel queries |

### DBAL Version Strategy

| DBAL | PHP | Branch | Status |
|------|-----|--------|--------|
| 3.10.x | ^8.1 | `main` | ✅ Current stable |
| 4.4.x | ^8.2 | `4.x` | 🔜 Priority |
| 4.5.x | ^8.2 | `4.5-dev` | 🔜 Feature branch |
| 5.0.x | ^8.3 | `5.x` | 🔜 Future |

---

## php-firebird OO Wrapper Integration

### Connection Class Architecture

```php
namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Firebird\Database;
use Firebird\Transaction;

class Connection implements ConnectionInterface
{
    private Database $database;
    private ?Transaction $activeTransaction = null;
    
    public function __construct(array $params)
    {
        $this->database = Database::connect(
            $params['host'] . ':' . $params['dbname'],
            $params['user'] ?? null,
            $params['password'] ?? null,
            $params['charset'] ?? 'UTF8'
        );
    }
    
    public function prepare(string $sql): Statement
    {
        $stmt = $this->database->prepare($sql);
        return new Statement($stmt, $this->database, $this->activeTransaction);
    }
    
    public function query(string $sql): Result
    {
        if ($this->activeTransaction !== null) {
            // UNIQUE: Transaction-aware query
            $result = $this->activeTransaction->query($sql);
        } else {
            $result = $this->database->query($sql);
        }
        return new Result($result);
    }
    
    public function beginTransaction(): void
    {
        $this->activeTransaction = $this->database->beginTransaction();
    }
    
    public function commit(): void
    {
        $this->activeTransaction?->commit();
        $this->activeTransaction = null;
    }
    
    public function rollBack(): void
    {
        $this->activeTransaction?->rollback();
        $this->activeTransaction = null;
    }
    
    public function getNativeConnection(): Database
    {
        return $this->database;
    }
}
```

### Statement Class Architecture

```php
class Statement implements StatementInterface
{
    private mixed $stmt;
    private Database $database;
    private ?Transaction $transaction;
    
    public function execute(?array $params = null): Result
    {
        if ($this->transaction !== null) {
            // Execute within explicit transaction
            $result = fbird_execute_params_tx($this->stmt, $this->transaction->getResource(), $params ?? []);
        } else {
            $result = $this->database->execute($this->stmt, $params ?? []);
        }
        return new Result($result);
    }
}
```

---

## Transaction-Aware Query Architecture

### UNIQUE DIFFERENTIATOR

php-firebird provides `fbird_query_params_tx()` - the ability to execute queries within a specific transaction context. **No other PHP Firebird driver has this.**

### Benefits

1. **Explicit Transaction Control**: Each query can specify its transaction
2. **Multiple Concurrent Transactions**: Single connection, multiple active transactions
3. **Proper ACID Compliance**: Isolation levels respected per-query
4. **DBAL Transaction Interface**: Full compatibility with `beginTransaction()`, `commit()`, `rollBack()`

### Implementation Pattern

```php
// Pattern 1: Implicit (default DBAL behavior)
$conn->beginTransaction();  // Sets $activeTransaction
$conn->query('SELECT...');  // Uses $activeTransaction automatically
$conn->commit();

// Pattern 2: Explicit (advanced usage via native connection)
$db = $conn->getNativeConnection();
$trans1 = $db->transaction()->readCommitted()->start();
$trans2 = $db->transaction()->serializable()->start();

$db->queryWithTransaction($trans1, 'INSERT INTO log...');
$db->queryWithTransaction($trans2, 'UPDATE accounts...');

$trans1->commit();
$trans2->commit();
```

---

## Savepoint Implementation

### Research Finding
GitHub Doctrine DBAL #2194: "Savepoints: Fails in PDO Firebird driver, although Firebird supports them"

### php-firebird Solution

The `Transaction` class provides full savepoint support:

```php
$trans = $db->beginTransaction();

// Create savepoint
$trans->savepoint('sp1');

// Do work
$trans->query('INSERT INTO users...');

// Rollback to savepoint (partial rollback)
$trans->rollbackToSavepoint('sp1');

// Or release savepoint
$trans->releaseSavepoint('sp1');

$trans->commit();
```

### DBAL Platform Integration

```php
class FirebirdPlatform extends AbstractPlatform
{
    public function supportsSavepoints(): bool
    {
        return true;  // NOW WORKS with php-firebird!
    }
    
    public function createSavePoint(string $savepoint): string
    {
        return 'SAVEPOINT ' . $savepoint;
    }
    
    public function releaseSavePoint(string $savepoint): string
    {
        return 'RELEASE SAVEPOINT ' . $savepoint;
    }
    
    public function rollbackSavePoint(string $savepoint): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $savepoint;
    }
}
```

---

## IBatch Bulk Operations

### Firebird 4.0+ Feature

php-firebird provides `Batch` class for efficient bulk operations:

```php
use Firebird\Batch;

$batch = new Batch($conn, 'INSERT INTO users (name, email) VALUES (?, ?)');
$batch->add(['Alice', 'alice@example.com']);
$batch->add(['Bob', 'bob@example.com']);
$batch->add(['Charlie', 'charlie@example.com']);

$result = $batch->execute();
// $result contains: affected rows, errors per row, etc.
```

### DBAL Integration

```php
// Doctrine DBAL batch insert using IBatch
class FirebirdConnection implements Connection
{
    public function executeBatch(string $sql, array $paramSets): int
    {
        $serverVersion = $this->getServerVersion();
        
        if (version_compare($serverVersion, '4.0', '>=')) {
            // Use IBatch API for Firebird 4.0+
            $batch = new Batch($this->database->getResource(), $sql);
            foreach ($paramSets as $params) {
                $batch->add($params);
            }
            return $batch->execute()->getAffectedRows();
        }
        
        // Fallback for older versions
        $stmt = $this->prepare($sql);
        $affected = 0;
        foreach ($paramSets as $params) {
            $stmt->execute($params);
            $affected++;
        }
        return $affected;
    }
}
```

---

## DBAL Multi-Version Support

### Branch Strategy

```
main (DBAL 3.10)
├── 4.x (DBAL 4.4)
│   └── Interface changes applied
├── 4.5-dev (DBAL 4.5)
│   └── New features
└── 5.x (DBAL 5.0)
    └── PHP 8.3+ only
```

### Interface Differences

**DBAL 3.10:**
```php
interface Connection {
    public function prepare(string $sql): Statement;
    public function query(string $sql): Result;
    public function exec(string $sql): int|string;
    // ...
}
```

**DBAL 4.x:**
```php
// Changes:
// - Statement::execute() returns Result (not bool)
// - Result methods renamed: fetch*() → fetchNumeric(), fetchAssociative()
// - Driver::connect() params array only
```

**DBAL 5.x:**
```php
// Changes:
// - PHP 8.3 minimum
// - Deprecated methods removed
// - New platform capabilities
```

---

## Firebird Type Mapping

### Firebird 4.0+ Types

```php
protected function initializeDoctrineTypeMappings(): void
{
    $this->doctrineTypeMapping = [
        // Standard types
        'smallint'    => Types::SMALLINT,
        'integer'     => Types::INTEGER,
        'bigint'      => Types::BIGINT,
        'float'       => Types::FLOAT,
        'double precision' => Types::FLOAT,
        
        // Firebird 4.0+ types
        'decfloat'    => Types::DECIMAL,
        'int128'      => Types::BIGINT,  // Custom handling for 128-bit
        
        // Timezone types (Firebird 4.0+)
        'timestamp with time zone' => Types::DATETIMETZ_MUTABLE,
        'time with time zone'      => Types::TIME_MUTABLE,
        
        // Boolean (Firebird 3.0+)
        'boolean'     => Types::BOOLEAN,
        
        // BLOB types
        'blob'        => Types::BLOB,
        'blob sub_type text' => Types::TEXT,
    ];
}
```

### INT128 Custom Type

```php
class Int128Type extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'INT128';
    }
    
    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        // Return as string to avoid precision loss
        return $value !== null ? (string) $value : null;
    }
}
```

---

## Platform Optimization Strategy

### Version-Specific Platforms

```php
// FirebirdPlatform.php - Base (Firebird 2.5+)
// Firebird3Platform.php - IDENTITY, BOOLEAN
// Firebird4Platform.php - DECFLOAT, INT128, TZ, IBatch
// Firebird5Platform.php - SKIP LOCKED, parallel
```

### Feature Detection

```php
class FirebirdDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        $version = $versionProvider->getServerVersion();
        
        return match (true) {
            version_compare($version, '5.0', '>=') => new Firebird5Platform(),
            version_compare($version, '4.0', '>=') => new Firebird4Platform(),
            version_compare($version, '3.0', '>=') => new Firebird3Platform(),
            default => new FirebirdPlatform(),
        };
    }
}
```

---

## PHP Firebird Extension Improvements

### Created GitHub Issues (2025-12-22)

| Issue | Feature | Priority | Status |
|-------|---------|----------|--------|
| [#15](https://github.com/satwareAG/php-firebird/issues/15) | Exception Mode API | P1 | Created |
| [#16](https://github.com/satwareAG/php-firebird/issues/16) | Stream BLOB Support | P2 | Created |
| [#17](https://github.com/satwareAG/php-firebird/issues/17) | Connection Pool Metadata | P3 | Created |
| [#18](https://github.com/satwareAG/php-firebird/issues/18) | Statement Caching API | P2 | Created |

### Enhancement Summary

1. **Exception Mode (#15)**: Enable `FBIRD_EXCEPTION_MODE_THROW` for clean error handling
2. **Stream BLOB (#16)**: Pass PHP streams directly to avoid memory leaks
3. **Pool Metadata (#17)**: Monitor persistent connection pools
4. **Statement Cache (#18)**: Leverage Firebird 5.0 compiled statement caching

---

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2)

| Task | Effort | Priority |
|------|--------|----------|
| Switch from ext-interbase to ext-firebird | 2d | P0 |
| Update Connection to use \Firebird\Database | 1d | P0 |
| Update Statement for transaction-aware queries | 1d | P0 |
| Implement savepoint support | 4h | P1 |
| Add type mappings for FB 4.0+ | 4h | P1 |

### Phase 2: DBAL 4.x Compatibility (Week 3-4)

| Task | Effort | Priority |
|------|--------|----------|
| Create 4.x branch | 2h | P0 |
| Update Driver::connect() signature | 4h | P0 |
| Update Statement::execute() return type | 4h | P0 |
| Rename Result methods | 4h | P0 |
| Update SchemaManager abstract methods | 1d | P1 |

### Phase 3: Performance Features (Week 5-6)

| Task | Effort | Priority |
|------|--------|----------|
| IBatch integration | 1d | P1 |
| Statement caching layer | 1d | P2 |
| Connection pool monitoring | 4h | P3 |
| Performance benchmarks | 1d | P2 |

### Phase 4: Testing & Documentation (Week 7-8)

| Task | Effort | Priority |
|------|--------|----------|
| Multi-version CI matrix | 4h | P1 |
| Integration tests with php-firebird | 2d | P1 |
| Migration guide from ext-interbase | 1d | P2 |
| API documentation | 1d | P2 |

---

## Success Metrics

### Code Quality

| Metric | Current | Target |
|--------|---------|--------|
| Test count | 1415+ | 1500+ |
| Code coverage | ~88% | 95%+ |
| PHPStan level | 8 | 8 (no baseline) |
| DBAL versions | 1 (3.10) | 4 (3.10, 4.4, 4.5, 5.0) |

### Unique Features (php-firebird)

| Feature | Status |
|---------|--------|
| Transaction-aware queries | ✅ Ready to implement |
| Savepoint support | ✅ Ready to implement |
| IBatch bulk operations | ✅ Ready to implement |
| OO wrapper usage | ✅ Ready to implement |

### Performance Targets

| Metric | Target |
|--------|--------|
| Bulk insert 1000 rows (IBatch) | <200ms |
| Select 10000 rows | <200ms |
| Transaction-aware query overhead | <5% |

---

## References

- [php-firebird Repository](https://github.com/satwareAG/php-firebird)
- [Doctrine DBAL Documentation](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/)
- [Doctrine DBAL #2194 - Firebird Support](https://github.com/doctrine/dbal/issues/2194)
- [Firebird 5.0 Release Notes](https://firebirdsql.org/file/documentation/release_notes/html/en/5_0/rlsnotes50.html)
- [2025 PHP Database Best Practices](https://phpdelusions.net/pdo)

---

*Plan created by Jane Alesi - satware® AI Platform*  
*Last updated: 2025-12-22*
