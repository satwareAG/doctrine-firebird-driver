# php-firebird v7.0.0 Integration Plan

**Date**: 2025-12-22  
**Status**: CI Updated - Testing v7.0.0-rc.2  
**Objective**: Integrate all advanced php-firebird v7 features into doctrine-firebird-driver

---

## v7.0.0-rc.2 Release Status ✅

**Released**: 2025-12-22 11:19:32 UTC (satwareAG/php-firebird)

### Key Fixes in v7.0.0-rc.2

1. **Firebird 3.0 compilation compatibility** (Issue #19): Added `fb_blr_compat.h` header with fallback BLR constant definitions for systems without `firebird/impl/blr.h`
2. **Firebird 3.0 runtime connection failure**: Fixed `CheckStatusWrapper::isDirty()` behavior difference between FB3 and FB4+. Changed all error checks to use `hasData()` which correctly checks for actual errors.
3. **CI Matrix tested**: 20 combinations - PHP 8.1-8.5 × Firebird 2.5, 3.0, 4.0, 5.0

### doctrine-firebird-driver Updates (2025-12-22)

| File | Change |
|------|--------|
| `.github/workflows/ci.yml` | Updated to use `v7.0.0-rc.2`, `--with-firebird`, `ext-firebird` |
| `composer.json` | Changed `ext-interbase` → `ext-firebird` |

---

## Current State Analysis

### Already Integrated (Partial v7 Support)
The driver already uses some php-firebird v7 features in `Connection.php`:

- ✅ `Firebird\Database` OO wrapper (via `getOOWrapper()`)
- ✅ `Firebird\Transaction` OO wrapper (via `queryInTransaction()`)
- ✅ `Firebird\TBuilder` fluent API (via `createIndependentTransaction()`)
- ✅ Transaction-aware queries (`fbird_query_params_tx()`)
- ✅ Savepoint support (`fbird_savepoint()`, `fbird_rollback_savepoint()`, `fbird_release_savepoint()`)
- ✅ Autonomous transactions (`fbird_execute_auto()`)
- ✅ Table blocker functions (`fbird_list_table_blockers()`, `fbird_kill_attachment()`)

### Missing v7 Features (To Be Integrated)

| Feature | PHPStan Stubs | Driver Integration | Priority |
|---------|---------------|-------------------|----------|
| **IBatch API** | ❌ Missing | ❌ Missing | P1 - High |
| **fbird_sqlstate()** | ❌ Missing | ❌ Missing | P1 - High |
| **fbird_connection_info()** | ❌ Missing | ❌ Missing | P2 - Medium |
| **fbird_blob_seek()** | ❌ Missing | ❌ Missing | P3 - Low |
| **FBIRD_FETCH_DATE_OBJ** | ✅ Defined | ❌ Not used | P2 - Medium |
| **Limbo transaction functions** | ❌ Missing | ❌ Missing | P3 - Low |
| **Firebird\Batch class** | ❌ Missing | ❌ Missing | P1 - High |
| **Firebird\BatchResult class** | ❌ Missing | ❌ Missing | P1 - High |
| **Firebird\BatchError class** | ❌ Missing | ❌ Missing | P1 - High |
| **Firebird\BlobId class** | ❌ Missing | ❌ Missing | P2 - Medium |
| **Firebird\DbInfo class** | ❌ Missing | ❌ Missing | P3 - Low |

---

## Phase 1: PHPStan Stub Updates (Priority: Immediate)

### 1.1 Add IBatch API Functions to `stubs/FirebirdStub.php`

```php
/**
 * Create a batch operation from a prepared statement (Firebird 4.0+)
 *
 * @param resource $query Prepared statement from fbird_prepare()
 * @param resource|null $trans Optional transaction resource
 * @return resource|false Batch resource or false on failure
 * @since php-firebird 7.0.0
 */
function fbird_batch_create($query, $trans = null) {}

/**
 * Add a row to the batch with automatic type conversion
 *
 * @param resource $batch Batch resource from fbird_batch_create()
 * @param mixed ...$params Parameter values for the row
 * @return bool True on success, false on failure
 * @since php-firebird 7.0.0
 */
function fbird_batch_add($batch, ...$params): bool {}

/**
 * Create an inline BLOB for batch operations
 *
 * @param resource $batch Batch resource
 * @param string $data BLOB data
 * @param int $type BLOB type (default: text)
 * @return string|false BLOB ID string or false on failure
 * @since php-firebird 7.0.0
 */
function fbird_batch_add_blob($batch, string $data, int $type = FBIRD_TEXT) {}

/**
 * Register an existing BLOB ID for batch operations
 *
 * @param resource $batch Batch resource
 * @param string $blob_id Existing BLOB ID
 * @return bool True on success, false on failure
 * @since php-firebird 7.0.0
 */
function fbird_batch_register_blob($batch, string $blob_id): bool {}

/**
 * Execute the batch operation
 *
 * @param resource $batch Batch resource
 * @return array{total_processed: int, success_count: int, error_count: int}|false
 * @since php-firebird 7.0.0
 */
function fbird_batch_execute($batch) {}

/**
 * Cancel the batch without executing
 *
 * @param resource $batch Batch resource
 * @return bool True on success, false on failure
 * @since php-firebird 7.0.0
 */
function fbird_batch_cancel($batch): bool {}
```

### 1.2 Add SQLSTATE and Connection Info Functions

```php
/**
 * Returns the 5-character SQLSTATE code for the last error
 *
 * @return string|false SQLSTATE code (e.g., "23000") or false if no error
 * @since php-firebird 7.0.0
 */
function fbird_sqlstate(): string|false {}

/**
 * Get connection information and statistics
 *
 * @param resource|null $link_identifier Connection resource
 * @return array<string, mixed>|false Connection statistics or false on failure
 * @since php-firebird 7.0.0
 */
function fbird_connection_info($link_identifier = null) {}

/**
 * Seek within a stream BLOB
 *
 * @param resource $blob_handle BLOB stream handle
 * @param int $offset Position offset
 * @param int $whence FBIRD_BLOB_SEEK_SET, FBIRD_BLOB_SEEK_CUR, or FBIRD_BLOB_SEEK_END
 * @return int|false New position or false on failure
 * @since php-firebird 7.0.0
 */
function fbird_blob_seek($blob_handle, int $offset, int $whence = FBIRD_BLOB_SEEK_SET): int|false {}
```

### 1.3 Add Limbo Transaction Functions

```php
/**
 * Retrieve in-doubt (limbo) transaction IDs
 *
 * @param resource $link_identifier Connection resource
 * @return array<int>|false Array of transaction IDs or false on failure
 * @since php-firebird 7.0.0
 */
function fbird_get_limbo_transactions($link_identifier) {}

/**
 * Reconnect to a limbo transaction for recovery
 *
 * @param resource $link_identifier Connection resource
 * @param int $transaction_id Limbo transaction ID
 * @return resource|false Transaction resource or false on failure
 * @since php-firebird 7.0.0
 */
function fbird_reconnect_transaction($link_identifier, int $transaction_id) {}
```

### 1.4 Add OO Classes to `stubs/FirebirdOO.php`

```php
/**
 * Batch operations for high-performance bulk inserts (Firebird 4.0+)
 */
class Batch implements \Countable
{
    public function __construct(mixed $connection, string $sql, mixed $transaction = null) {}
    
    /** @return $this */
    public function add(mixed ...$params): self {}
    
    public function addBlob(string $data, int $type = FBIRD_TEXT): string {}
    
    public function registerBlob(string $blobId): bool {}
    
    public function execute(): BatchResult {}
    
    public function cancel(): bool {}
    
    public function count(): int {}
}

/**
 * Result container for batch operations
 */
class BatchResult implements \Countable, \IteratorAggregate
{
    public function getTotalProcessed(): int {}
    public function getSuccessCount(): int {}
    public function getErrorCount(): int {}
    public function hasErrors(): bool {}
    
    /** @return \Traversable<int, BatchError> */
    public function getErrors(): \Traversable {}
    
    /** @return \Traversable<int, BatchError> */
    public function getIterator(): \Traversable {}
    
    public function count(): int {}
}

/**
 * Per-row error information from batch operations
 */
class BatchError
{
    public function getRowNumber(): int {}
    public function getErrorCode(): int {}
    public function getErrorMessage(): string {}
    public function getSqlState(): string {}
}

/**
 * Type-safe BLOB identifier value object
 */
class BlobId implements \Stringable
{
    public function __construct(string $id) {}
    
    public static function fromString(string $id): self {}
    
    public function getHighPart(): int {}
    public function getLowPart(): int {}
    public function __toString(): string {}
    public function equals(BlobId $other): bool {}
}

/**
 * Database information structure
 */
class DbInfo
{
    public function getVersion(): string {}
    public function getOdsVersion(): int {}
    public function getOdsMinorVersion(): int {}
    public function getPageSize(): int {}
    public function getNumBuffers(): int {}
    public function getSweepInterval(): int {}
    public function getDbSqlDialect(): int {}
    /** @return array<string, mixed> */
    public function toArray(): array {}
}
```

---

## Phase 2: Driver Integration (Priority Order)

### 2.1 P1: IBatch API for Bulk Operations

**Target**: `Statement.php` / `Connection.php`  
**Benefit**: 10-12x INSERT performance improvement for bulk operations

```php
// New method in Connection.php
public function createBatch(string $sql): \Firebird\Batch
{
    if (version_compare($this->serverVersion, '4.0', '<')) {
        throw new DriverException('IBatch API requires Firebird 4.0+');
    }
    
    return new \Firebird\Batch(
        $this->connection,
        $sql,
        $this->firebirdActiveTransaction
    );
}

// Convenience method for bulk inserts
public function executeBatch(string $sql, array $paramSets): int
{
    // Firebird 4.0+: Use IBatch API
    if (version_compare($this->serverVersion, '4.0', '>=')) {
        $batch = $this->createBatch($sql);
        foreach ($paramSets as $params) {
            $batch->add(...$params);
        }
        $result = $batch->execute();
        return $result->getSuccessCount();
    }
    
    // Fallback: Traditional prepared statement loop
    $stmt = $this->prepare($sql);
    $affected = 0;
    foreach ($paramSets as $params) {
        $stmt->execute($params);
        $affected++;
    }
    return $affected;
}
```

### 2.2 P1: SQLSTATE Error Reporting Enhancement

**Target**: `ExceptionConverter.php` / `Exception.php`  
**Benefit**: Standard SQLSTATE codes for better error handling

```php
// Enhanced Exception class
final class Exception extends \Exception implements DriverException
{
    private ?string $sqlState = null;
    
    public static function fromErrorInfo(
        string $message, 
        int $code,
        ?string $sqlState = null
    ): self {
        $exception = new self($message, $code);
        $exception->sqlState = $sqlState ?? self::fetchSqlState();
        return $exception;
    }
    
    private static function fetchSqlState(): ?string
    {
        $state = fbird_sqlstate();
        return $state !== false ? $state : null;
    }
    
    public function getSQLState(): ?string
    {
        return $this->sqlState;
    }
}
```

### 2.3 P2: DateTimeImmutable Support via FBIRD_FETCH_DATE_OBJ

**Target**: `Result.php`  
**Benefit**: Native PHP DateTime objects instead of strings

```php
// Configuration option in Result.php
private bool $fetchDateAsObject = false;

public function setFetchDateAsObject(bool $enabled): void
{
    $this->fetchDateAsObject = $enabled;
}

// Modified fetch methods
private function getFetchFlags(): int
{
    $flags = FBIRD_FETCH_BLOBS;
    if ($this->fetchDateAsObject) {
        $flags |= FBIRD_FETCH_DATE_OBJ;
    }
    return $flags;
}
```

### 2.4 P2: Connection Info for Monitoring

**Target**: `Connection.php`  
**Benefit**: Database connection statistics for monitoring/debugging

```php
/**
 * Get connection information and statistics.
 *
 * @return array<string, mixed> Connection statistics
 */
public function getConnectionInfo(): array
{
    if (!$this->isConnectionValid()) {
        throw new DriverException('Connection is not valid.');
    }
    
    $info = fbird_connection_info($this->connection);
    return $info !== false ? $info : [];
}
```

### 2.5 P3: BLOB Seek Support

**Target**: `Connection.php` or new `BlobStream.php`  
**Benefit**: Random access within large BLOBs

### 2.6 P3: Limbo Transaction Recovery

**Target**: `Connection.php`  
**Benefit**: Recovery of in-doubt transactions

---

## Phase 3: Testing Strategy

### Unit Tests (New)
- `tests/Test/Unit/Driver/Firebird/BatchTest.php` - IBatch API
- `tests/Test/Unit/Driver/Firebird/SqlStateTest.php` - SQLSTATE extraction
- `tests/Test/Unit/Driver/Firebird/DateTimeObjectTest.php` - DateTime handling

### Functional Tests (New)
- `tests/Test/Functional/BatchInsertTest.php` - Bulk operations
- `tests/Test/Functional/SqlStateExceptionTest.php` - Error codes
- `tests/Test/Functional/DateTimeFetchTest.php` - DateTime fetching

### Performance Benchmarks (New)
- `tests/Benchmark/BatchVsLoopInsertBench.php` - Compare IBatch vs loop

---

## Implementation Checklist

### Phase 1: Stubs (Week 1) - COMPLETED ✅
- [x] Add IBatch functions to `FirebirdStub.php`
- [x] Add `fbird_sqlstate()` to `FirebirdStub.php`
- [x] Add `fbird_connection_info()` to `FirebirdStub.php`
- [x] Add `fbird_blob_seek()` to `FirebirdStub.php`
- [x] Add limbo transaction functions to `FirebirdStub.php`
- [x] Add `Batch`, `BatchResult`, `BatchError` classes to `FirebirdOO.php`
- [x] Add `BlobId`, `DbInfo` classes to `FirebirdOO.php`
- [x] Run PHPStan to verify stub correctness

### Phase 2: Driver Integration (Weeks 2-3) - COMPLETED ✅
- [x] Implement `createBatch()` and `executeBatch()` in `Connection.php`
- [x] Enhance `Exception.php` with SQLSTATE support
- [ ] Update `ExceptionConverter.php` to use SQLSTATE (P3 - optional enhancement)
- [x] Add DateTimeImmutable fetch option to `Result.php`
- [x] Add `getConnectionInfo()` to `Connection.php`
- [ ] Add BLOB seek support (P3 - deferred)
- [x] Add limbo transaction recovery

### Phase 3: Testing (Week 4) - COMPLETED ✅
- [x] Write unit tests for new features
  - Created `tests/Test/Unit/Driver/ExceptionTest.php` - SQLSTATE support tests
  - Updated `tests/Test/Unit/Driver/ConnectionTest.php` - IBatch API, connection info, limbo transaction tests
  - Updated `tests/Test/Unit/Driver/ResultTest.php` - DateTimeImmutable fetch tests
- [ ] Write functional tests against Firebird 4.0+/5.0 (requires Docker environment)
- [ ] Create performance benchmarks (deferred - requires Firebird 4.0+)
- [x] Unit tests passing: 106 tests, 169 assertions

### Phase 4: Documentation (Week 4)
- [ ] Update README with v7 features
- [ ] Create migration guide
- [ ] Add code examples

---

## Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| v7 Feature Coverage | ~40% | 100% |
| PHPStan Stub Coverage | ~60% | 100% |
| Bulk INSERT Performance | Baseline | 10x improvement |
| Test Coverage | ~88% | ≥90% |

---

## References

- [php-firebird v7.0.0-rc.1 CHANGELOG](https://github.com/satwareAG/php-firebird/blob/main/CHANGELOG.md)
- [First Citizen Excellence Plan](./2025-12-07-first-citizen-excellence-plan.md)
- [Firebird 4.0 IBatch API Documentation](https://firebirdsql.org/file/documentation/release_notes/html/en/4_0/rlsnotes40.html#rnfb40-apiods-api-batchops)

---

*Plan created by Jane Alesi - satware® AI Platform*
*Date: 2025-12-22*
