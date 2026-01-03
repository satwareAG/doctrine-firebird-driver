# PHP Firebird Extension Integration & Modernization Report

**Date:** 2025-11-27
**To:** Doctrine Firebird Driver Development Team
**From:** PHP Firebird Extension Team
**Subject:** Readiness of Modernized Extension and Required Driver Updates for Firebird 4.0+ Support

## 1. Executive Summary

We are pleased to announce that the modernization of the `php-firebird` extension (C++17) is complete. The extension now fully supports **Firebird 4.0+ data types**, including `TIMESTAMP WITH TIME ZONE`, `TIME WITH TIME ZONE`, `INT128`, and `DECFLOAT`, via robust string-based binding and fetching mechanisms.

Additionally, we have implemented a **Comprehensive Transaction API** to solve long-standing stability issues with nested transactions (savepoints) and locking strategies.

Zero performance regressions were found, and memory safety has been significantly enhanced.

We have conducted integration verification and identified that while the **Extension** is ready, the **Doctrine Driver** requires specific configuration updates to leverage these new features.

## 2. Integration Verification Results

We performed identifying tests using raw PHP scripts interacting directly with the `ibase_` API provided by the extension.

### Capability Confirmation: `TIMESTAMP WITH TIME ZONE`
- **Test:** Bound explicit timezone string literal (`'2025-01-01 12:00:00.0000 America/New_York'`) to a `TIMESTAMP WITH TIME ZONE` column.
- **Result (Insert):** ✅ Success. The extension's fallback to `SQL_TEXT` binding is correctly handled by the Firebird engine for valid string literals.
- **Result (Select):** ✅ Success. The extension correctly decodes `SQL_TIMESTAMP_TZ` binary data into PHP strings (format: `"Y-m-d H:i:s.u Timezone"`), preserving the stored timezone information.

### Current Driver Status
We simulated Doctrine operations and identified two blocking issues in the current `doctrine-firebird-driver` implementation:

1. **Silent Data Loss (DDL Mapping):**
   - `FirebirdPlatform` currently maps `datetimetz` to `TIMESTAMP`.
   - **Effect:** Data is stored without timezone information. Timezone is lost upon retrieval (defaults to UTC/Session TZ).
   
2. **Unknown Type Error (Introspection):**
   - When a table correctly defines `TIMESTAMP WITH TIME ZONE`, the driver throws `Doctrine\DBAL\Exception: Unknown database type timestamp with time zone requested`.
   - **Effect:** Schema introspection and operations relying on it fail because the driver does not recognize the type string returned by the extension.

## 3. Action Items for Doctrine Driver Team

To fully support Firebird 4.0+ features, we recommend the following changes in `Satag\DoctrineFirebirdDriver`:

### A. Update Platform DDL (`Firebird4Platform.php`)
Override the DDL generation methods to use native Firebird 4.0 types.

```php
// In Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform

public function getDateTimeTzTypeDeclarationSQL(array $column)
{
    return 'TIMESTAMP WITH TIME ZONE';
}

public function getTimeTzTypeDeclarationSQL(array $column)
{
    return 'TIME WITH TIME ZONE';
}
```

### B. Register New Type Mappings
Update `initializeDoctrineTypeMappings` to recognize the new types reported by the extension.

```php
protected function initializeDoctrineTypeMappings(): void
{
    parent::initializeDoctrineTypeMappings();
    // Map Firebird 4.0+ native types to Doctrine types
    $this->doctrineTypeMapping['timestamp with time zone'] = Types::DATETIMETZ_MUTABLE;
    $this->doctrineTypeMapping['time with time zone'] = Types::TIMETZ_MUTABLE; // Check DBAL support
    $this->doctrineTypeMapping['int128'] = Types::BIGINT; // Or string if precision matters
    $this->doctrineTypeMapping['decfloat'] = Types::DECIMAL;
}
```

### C. Date Formatting for Binding
Ensure that `DateTimeTzType` values are converted to a string format that Firebird accepts. The standard `Y-m-d H:i:s` drops the timezone information.

We recommend verifying if overriding `getDateTimeTzFormatString` is necessary:
```php
public function getDateTimeTzFormatString(): string
{
    // Format supporting timezone identifier
    return 'Y-m-d H:i:s.u e'; // Example: 2025-01-01 12:00:00.0000 America/New_York
}
```

## 4. Bug Reporting & Collaboration

We are committed to ensuring the underlying extension provides a stable foundation for your driver.

**Request:**
If you encounter issues that appear to be bugs in the `ibm_execute`, `ibase_fetch`, or binding logic—or if you require specific binary binding support for performance reasons—please **report them immediately to the php-firebird extension team**.

Conversely, if you discover bugs within the Driver logic that were masked by previous extension behavior, please implement fixes on the **Driver side** (Doctrine) repository.

## 5. Transaction API Enhancements (2025 Integration)

To support robust nested transactions and advanced locking, the extension now exposes the complete Firebird Transaction API via modern `fbird_` functions (all `ibase_` functions also have `fbird_` aliases now).

### 5.1 Native Savepoints
The driver can now use native functions instead of raw DSQL for savepoints. These functions wrap DSQL safely and handle errors consistently.

- `fbird_savepoint(resource $trans, string $name): bool`
- `fbird_rollback_savepoint(resource $trans, string $name): bool`
- `fbird_release_savepoint(resource $trans, string $name): bool`

**Recommendation:** Update `FirebirdPlatform` to use these functions if `function_exists('fbird_savepoint')` is true, replacing `EXECUTE STATEMENT 'SAVEPOINT ...'` logic.

### 5.2 Expanded Transaction Start (TPB)
A new function `fbird_trans_start` allows explicit control over the Transaction Parameter Block (TPB), enabling features like **Table Reservation** and **Read Consistency** (FB 4.0+).

```php
// Example: Start a transaction with Table Reservation (Exclusive Write Lock)
$trans = fbird_trans_start($db, [
    'tables' => [
        'orders' => IBASE_LOCK_WRITE,
        'inventory' => IBASE_LOCK_READ
    ], 
    'isolation' => IBASE_READ_COMMITTED | IBASE_REC_VERSION,
    'lock_resolution' => IBASE_WAIT,
    'lock_timeout' => 10 // Wait up to 10 seconds
]);
```

**New Constants:**
- `IBASE_LOCK_SHARED`, `IBASE_LOCK_PROTECTED`, `IBASE_LOCK_EXCLUSIVE`
- `IBASE_LOCK_READ`, `IBASE_LOCK_WRITE`
- `IBASE_READ_CONSISTENCY`

### 5.3 Transaction State Inspection
The driver can now inspect the state of a transaction resource without attempting a query (which would fail if the transaction is dead).

```php
$info = fbird_trans_info($trans);
// Returns: ['id' => 123, 'state' => 'ACTIVE', 'isolation' => 'READ_COMMITTED', 'access_mode' => 'READ_WRITE']
```

**We look forward to seeing full Firebird 4.0 support in Doctrine!**

**Signed,**
The PHP Firebird Extension Team
