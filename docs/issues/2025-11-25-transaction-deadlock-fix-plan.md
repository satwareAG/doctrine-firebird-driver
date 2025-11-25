# Transaction Deadlock Fix Plan using `fbird_trans`

## Issue Description
Tests involving `createTransaction` are failing or hanging due to a deadlock.
Changes in `src/Driver/Firebird/Connection.php` introduced `fbird_prepare` + `execute` logic (or `fbird_query`) for `SET TRANSACTION` statements.
The PHP Firebird/Interbase extension likely starts an implicit default transaction (Snapshot isolation usually) when `fbird_query` or `fbird_prepare` is called without an active transaction handle.
When we execute `SET TRANSACTION...`, we intend to start our own explicit transaction (e.g., Read Committed).
However, the implicit transaction (T1) is already active holding locks (or metadata locks), and our new explicit transaction (T2) might conflict or wait on T1, causing a deadlock or unexpected behavior, especially with DDL operations in tests.

## Root Cause
Using SQL (`SET TRANSACTION ...`) to start a transaction via `fbird_query` triggers the extension to ensure a transaction context exists *before* executing the SQL (to prepare it), creating T1. The execution of `SET TRANSACTION` then creates T2. Having nested/multiple transactions where T1 is default (Snapshot) leads to locking issues.

## Solution
Use `fbird_trans` (API call `isc_start_transaction`) directly to create the transaction handle. This avoids sending SQL to the server to be parsed/prepared, thus bypassing the implicit transaction creation by the driver.

## Implementation Details

Refactor `createTransaction` in `src/Driver/Firebird/Connection.php`.

### Mapping Constants
We need to map Doctrine's `TransactionIsolationLevel` to Firebird constants (`IBASE_*`).

| Doctrine Isolation Level | Firebird SQL Equivalent | `fbird_trans` Flags |
| :--- | :--- | :--- |
| `READ_UNCOMMITTED` | `READ UNCOMMITTED RECORD_VERSION` | `IBASE_READ` \| `IBASE_COMMITTED` \| `IBASE_REC_VERSION` (Approximation as FB treats Read Uncommitted similar to Read Committed with versions usually, or check if specific constant exists. Current constant list lacks `IBASE_UNCOMMITTED`. Will map to Read Committed + Rec Version) |
| `READ_COMMITTED` | `READ COMMITTED RECORD_VERSION` | `IBASE_WRITE` \| `IBASE_COMMITTED` \| `IBASE_REC_VERSION` |
| `REPEATABLE_READ` | `SNAPSHOT` | `IBASE_WRITE` \| `IBASE_CONCURRENCY` |
| `SERIALIZABLE` | `SNAPSHOT TABLE STABILITY` | `IBASE_WRITE` \| `IBASE_CONSISTENCY` |

**Wait Mode:**
*   `WAIT` -> `IBASE_WAIT`
*   `NO WAIT` -> `IBASE_NOWAIT`
*   `LOCK TIMEOUT` -> `fbird_trans` does not natively support passing a timeout value easily in PHP API (it takes int flags). We will map to `IBASE_WAIT` if timeout > 0 or -1, and `IBASE_NOWAIT` otherwise. Note: We lose precise timeout control but gain stability preventing deadlocks.

### Planned Changes in `Connection.php`

1.  Modify `createTransaction()`:
    *   Remove SQL generation logic for `SET TRANSACTION`.
    *   Determine integer flags based on `$this->attrDcTransIsolationLevel` and `$this->attrDcTransWait`.
    *   Call `fbird_trans($this->connection, $flags)`.
    *   Store result in `$this->firebirdActiveTransaction`.

2.  Remove `getStartTransactionSql()` if no longer needed, or keep it for reference/logging? Likely remove or deprecate internal usage.

## Tasks
- [ ] Create a reproduction script? (Existing tests reproduce it)
- [ ] Modify `Connection.php`.
- [ ] Verify with `tests/Test/Functional/Driver/Firebird/ConnectionTest.php` (or create a specific transaction test).
