# Doctrine Firebird Driver - Implementation Plan

**Created**: 2025-12-22  
**Based on**: First Citizen Excellence Plan (research-backed)  
**Target**: Migration from ext-interbase to php-firebird (ext-firebird)

---

## Phase 1: Foundation Migration (P0 - Critical Path)

### 1.1 Switch Extension Dependency

**composer.json changes:**
```json
{
    "require": {
        "php": "^8.1",
        "ext-firebird": "*",
        "doctrine/dbal": "^3.10 || ^4.4"
    },
    "conflict": {
        "ext-interbase": "*"
    },
    "suggest": {
        "ext-firebird": "Required for Firebird database support (https://github.com/satwareAG/php-firebird)"
    }
}
```

**Verification:**
```bash
php -m | grep fbird
php -r "var_dump(function_exists('fbird_connect'));"
```

### 1.2 Connection Class Refactoring

**File**: `src/Driver/Firebird/Connection.php`

**Current (ext-interbase):**
```php
$this->connection = ibase_connect($connectionString, $username, $password, $charset);
```

**Target (php-firebird):**
```php
use Firebird\Database;

private Database $database;
private ?Transaction $activeTransaction = null;

public function __construct(array $params)
{
    $dsn = $this->buildConnectionString($params);
    $this->database = Database::connect(
        $dsn,
        $params['user'] ?? null,
        $params['password'] ?? null,
        $params['charset'] ?? 'UTF8',
        $params['buffers'] ?? 0,
        $params['dialect'] ?? 3,
        $params['role'] ?? null
    );
}

private function buildConnectionString(array $params): string
{
    $host = $params['host'] ?? 'localhost';
    $port = $params['port'] ?? 3050;
    $dbname = $params['dbname'];
    
    if ($port !== 3050) {
        return "{$host}/{$port}:{$dbname}";
    }
    return "{$host}:{$dbname}";
}
```

### 1.3 Statement Class Refactoring

**File**: `src/Driver/Firebird/Statement.php`

**Transaction-Aware Pattern:**
```php
class Statement implements StatementInterface
{
    private mixed $stmt;
    private Database $database;
    private ?Transaction $transaction;
    private array $boundParams = [];
    
    public function __construct(mixed $stmt, Database $database, ?Transaction $transaction)
    {
        $this->stmt = $stmt;
        $this->database = $database;
        $this->transaction = $transaction;
    }
    
    public function bindValue(int|string $param, mixed $value, ParameterType $type = ParameterType::STRING): void
    {
        $this->boundParams[$param] = $this->convertValue($value, $type);
    }
    
    public function execute(?array $params = null): Result
    {
        $executeParams = $params ?? array_values($this->boundParams);
        
        if ($this->transaction !== null && $this->transaction->isActive()) {
            // Transaction-aware execution (UNIQUE FEATURE)
            $result = fbird_execute_params_tx(
                $this->stmt, 
                $this->transaction->getResource(), 
                $executeParams
            );
        } else {
            $result = $this->database->execute($this->stmt, $executeParams);
        }
        
        if ($result === false) {
            throw new Exception(fbird_errmsg());
        }
        
        return new Result($result);
    }
}
```

### 1.4 Result Class Updates

**File**: `src/Driver/Firebird/Result.php`

```php
class Result implements ResultInterface
{
    private mixed $result;
    private int $affectedRows = 0;
    
    public function fetchNumeric(): array|false
    {
        return fbird_fetch_row($this->result);
    }
    
    public function fetchAssociative(): array|false
    {
        return fbird_fetch_assoc($this->result);
    }
    
    public function fetchOne(): mixed
    {
        $row = $this->fetchNumeric();
        return $row !== false ? $row[0] : false;
    }
    
    public function fetchAllNumeric(): array
    {
        $rows = [];
        while (($row = $this->fetchNumeric()) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function fetchAllAssociative(): array
    {
        $rows = [];
        while (($row = $this->fetchAssociative()) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function free(): void
    {
        if (is_resource($this->result)) {
            fbird_free_result($this->result);
        }
        $this->result = null;
    }
}
```

---

## Phase 2: Transaction & Savepoint Support

### 2.1 Transaction Methods in Connection

```php
class Connection implements ConnectionInterface
{
    public function beginTransaction(): void
    {
        if ($this->activeTransaction !== null) {
            throw new Exception('Transaction already active');
        }
        $this->activeTransaction = $this->database->beginTransaction();
    }
    
    public function commit(): void
    {
        if ($this->activeTransaction === null) {
            throw new Exception('No active transaction');
        }
        $this->activeTransaction->commit();
        $this->activeTransaction = null;
    }
    
    public function rollBack(): void
    {
        if ($this->activeTransaction === null) {
            throw new Exception('No active transaction');
        }
        $this->activeTransaction->rollback();
        $this->activeTransaction = null;
    }
    
    // Savepoint methods (required by DBAL)
    public function createSavepoint(string $savepoint): void
    {
        if ($this->activeTransaction === null) {
            throw new Exception('No active transaction for savepoint');
        }
        $this->activeTransaction->savepoint($savepoint);
    }
    
    public function releaseSavepoint(string $savepoint): void
    {
        $this->activeTransaction?->releaseSavepoint($savepoint);
    }
    
    public function rollbackSavepoint(string $savepoint): void
    {
        $this->activeTransaction?->rollbackToSavepoint($savepoint);
    }
}
```

### 2.2 Platform Savepoint Support

**File**: `src/Platform/FirebirdPlatform.php`

```php
public function supportsSavepoints(): bool
{
    return true;  // Works with php-firebird!
}

public function createSavePoint(string $savepoint): string
{
    return 'SAVEPOINT ' . $this->quoteSingleIdentifier($savepoint);
}

public function releaseSavePoint(string $savepoint): string
{
    return 'RELEASE SAVEPOINT ' . $this->quoteSingleIdentifier($savepoint);
}

public function rollbackSavePoint(string $savepoint): string
{
    return 'ROLLBACK TO SAVEPOINT ' . $this->quoteSingleIdentifier($savepoint);
}
```

---

## Phase 3: Firebird 4.0+ Features

### 3.1 Type Mappings

**File**: `src/Platform/Firebird4Platform.php`

```php
class Firebird4Platform extends Firebird3Platform
{
    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();
        
        // Firebird 4.0+ types
        $this->doctrineTypeMapping['decfloat'] = Types::DECIMAL;
        $this->doctrineTypeMapping['int128'] = 'firebird_int128';
        $this->doctrineTypeMapping['timestamp with time zone'] = Types::DATETIMETZ_MUTABLE;
        $this->doctrineTypeMapping['time with time zone'] = Types::TIME_MUTABLE;
    }
    
    public function hasNativeDecimalFloatType(): bool
    {
        return true;
    }
    
    public function getDecimalFloatDeclaration(array $column): string
    {
        return 'DECFLOAT' . ($column['precision'] ?? '' ? '(' . $column['precision'] . ')' : '');
    }
    
    public function supportsTimezoneTypes(): bool
    {
        return true;
    }
}
```

### 3.2 IBatch Integration

**File**: `src/Driver/Firebird/BatchExecutor.php`

```php
use Firebird\Batch;

class BatchExecutor
{
    public function executeBatch(Database $database, string $sql, array $paramSets): BatchResult
    {
        if (!class_exists(Batch::class)) {
            // Fallback for older versions
            return $this->executeBatchFallback($database, $sql, $paramSets);
        }
        
        $batch = new Batch($database->getResource(), $sql);
        
        foreach ($paramSets as $params) {
            $batch->add($params);
        }
        
        return $batch->execute();
    }
    
    private function executeBatchFallback(Database $database, string $sql, array $paramSets): BatchResult
    {
        $stmt = $database->prepare($sql);
        $affectedRows = 0;
        $errors = [];
        
        foreach ($paramSets as $index => $params) {
            try {
                $database->execute($stmt, $params);
                $affectedRows++;
            } catch (\Exception $e) {
                $errors[$index] = $e->getMessage();
            }
        }
        
        return new BatchResult($affectedRows, $errors);
    }
}
```

---

## Phase 4: DBAL 4.x Compatibility Branch

### 4.1 Driver Class Changes

**File**: `src/Driver/Firebird/Driver.php` (4.x branch)

```php
// DBAL 4.x: connect() receives all params in single array
public function connect(array $params): Connection
{
    // Note: username, password already in $params array
    return new Connection($params);
}

// DBAL 4.x: getExceptionConverter() is required
public function getExceptionConverter(): ExceptionConverter
{
    return new ExceptionConverter();
}
```

### 4.2 Result Method Renames (4.x)

```php
// DBAL 3.x methods (deprecated in 4.x)
public function fetch(): array|false { /* ... */ }
public function fetchAll(): array { /* ... */ }
public function fetchColumn(): mixed { /* ... */ }

// DBAL 4.x replacements
public function fetchNumeric(): array|false { /* ... */ }
public function fetchAssociative(): array|false { /* ... */ }
public function fetchOne(): mixed { /* ... */ }
public function fetchAllNumeric(): array { /* ... */ }
public function fetchAllAssociative(): array { /* ... */ }
```

### 4.3 Statement::execute() Return Type (4.x)

```php
// DBAL 3.x
public function execute(?array $params = null): bool

// DBAL 4.x
public function execute(?array $params = null): Result
```

---

## Phase 5: Testing Strategy

### 5.1 CI Matrix

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  test:
    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3', '8.4']
        firebird: ['3.0', '4.0', '5.0']
        dbal: ['3.10', '4.4']
        exclude:
          - dbal: '4.4'
            php: '8.1'  # DBAL 4.x requires PHP 8.2+
    
    services:
      firebird:
        image: jacobalberty/firebird:${{ matrix.firebird }}
        env:
          FIREBIRD_DATABASE: test.fdb
          FIREBIRD_USER: SYSDBA
          FIREBIRD_PASSWORD: masterkey
        ports:
          - 3050:3050
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: fbird
      
      - name: Install dependencies
        run: composer install --prefer-dist
      
      - name: Run tests
        run: vendor/bin/phpunit --testsuite=unit
```

### 5.2 Integration Tests

```php
class TransactionAwareQueryTest extends TestCase
{
    public function testQueryUsesActiveTransaction(): void
    {
        $conn = $this->getConnection();
        $conn->beginTransaction();
        
        // Query should use active transaction
        $result = $conn->executeQuery('SELECT * FROM test_table');
        
        // Verify transaction ID is consistent
        $trans = $conn->getNativeConnection()->getActiveTransaction();
        $this->assertNotNull($trans);
        $this->assertTrue($trans->isActive());
        
        $conn->commit();
    }
    
    public function testSavepointSupport(): void
    {
        $conn = $this->getConnection();
        $conn->beginTransaction();
        
        $conn->executeStatement("INSERT INTO test_table (name) VALUES ('before')");
        
        $conn->createSavepoint('sp1');
        $conn->executeStatement("INSERT INTO test_table (name) VALUES ('after')");
        
        $conn->rollbackSavepoint('sp1');
        $conn->commit();
        
        // Only 'before' should exist
        $result = $conn->executeQuery("SELECT * FROM test_table WHERE name = 'after'");
        $this->assertFalse($result->fetchAssociative());
    }
}
```

---

## Phase 6: Migration Guide

### For Existing Users

**composer.json:**
```diff
{
    "require": {
-       "ext-interbase": "*",
+       "ext-firebird": "*",
        "satag/doctrine-firebird-driver": "^2.0"
    }
}
```

**PHP Extension:**
```bash
# Remove ext-interbase
# Install php-firebird from https://github.com/satwareAG/php-firebird

# Verify
php -m | grep fbird
```

**Code Changes:**
- None required for basic usage
- Transaction-aware queries available via `getNativeConnection()`
- Savepoints now work correctly

---

## Milestones

| Milestone | Target Date | Status |
|-----------|-------------|--------|
| Phase 1: Foundation | Week 1-2 | 🔜 |
| Phase 2: Transactions | Week 3 | 🔜 |
| Phase 3: FB 4.0+ Features | Week 4 | 🔜 |
| Phase 4: DBAL 4.x Branch | Week 5-6 | 🔜 |
| Phase 5: Testing | Week 7 | 🔜 |
| Phase 6: Documentation | Week 8 | 🔜 |
| Release v2.0.0 | Week 9 | 🔜 |

---

## References

- [php-firebird GitHub](https://github.com/satwareAG/php-firebird)
- [First Citizen Excellence Plan](./2025-12-07-first-citizen-excellence-plan.md)
- [Doctrine DBAL 4.x Upgrade Guide](https://github.com/doctrine/dbal/blob/4.4.x/UPGRADE.md)
- [php-firebird Enhancement Issues](https://github.com/satwareAG/php-firebird/issues)
  - #15: Exception Mode API
  - #16: Stream BLOB Support
  - #17: Connection Pool Metadata
  - #18: Statement Caching API

---

*Implementation Plan created by Jane Alesi - satware® AI Platform*  
*Created: 2025-12-22*
