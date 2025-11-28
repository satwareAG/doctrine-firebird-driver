# Recommendations for php-firebird Extension Improvements

This document outlines specific `fbird_*` extension functions and behaviors that, if improved, would significantly enhance the developer experience and make the Doctrine Firebird driver implementation smoother and more robust (a "Rails-like" experience).

## 1. `fbird_fetch_*` functions

**Current Behavior:**
- Emits PHP warnings (e.g., `SQL error code = -504 Invalid cursor reference`) when fetching from a cursor that has been closed (e.g., by a transaction commit) or consumed.
- Returns `false` on failure or EOF, but the accompanying warning forces developers to use the error suppression operator (`@`) to keep logs clean.

**Recommendation:**
- **Clean EOF:** Return `false` without warning when the result set is exhausted.
- **Checkable State:** Provide a way to check `fbird_result_status($result)` to see if it's valid/open before fetching, OR simply return `false` (with an error code available via `fbird_errcode`) instead of emitting a warning for "Invalid cursor" if the cursor was implicitly closed by a commit.
- **Consistency:** Ensure `fetch` behaves consistently regardless of whether `fbird_commit_ret` was called.

## 2. `fbird_execute` with Prepared Statements

**Current Behavior:**
- Reusing a prepared statement resource with new parameters (via `fbird_execute($stmt, ...$params)`) sometimes leads to invalid cursors on subsequent fetches, especially in `autoCommit` environments.
- "Invalid cursor" errors occur on the *second* execution's fetch, suggesting the statement handle might lose its validity or linkage to the transaction context unexpectedly.

**Recommendation:**
- **Robust Reuse:** Ensure `fbird_execute` correctly resets the statement state and binds new parameters cleanly, even if the previous result set was freed or the transaction context was retained via `fbird_commit_ret`.
- **Error Reporting:** If execution fails due to transaction state, return `false` explicitly with a clear error message, rather than producing a zombie result resource that fails on fetch.

## 3. Transaction Management (`fbird_commit_ret`)

**Current Behavior:**
- `fbird_commit_ret` retains the transaction context, but its effect on open cursors (result sets) and prepared statements is sometimes ambiguous or leads to "Invalid cursor" warnings when accessing resources associated with the "previous" epoch of the transaction.

**Recommendation:**
- **Clear Lifecycle:** Explicitly document (or improve behavior) regarding which resources survive a `commit_ret`. Ideally, prepared statement handles should survive and remain executable.
- **Auto-Close:** If cursors must be closed on commit, ensure `fbird_fetch` returns `false` gracefully for those cursors instead of warning.

## 4. BLOB Handling (`fbird_blob_*`)

**Current Behavior:**
- Requires manual creation (`fbird_blob_create`), chunked writing (`fbird_blob_add`), and closing (`fbird_blob_close`).
- If an error occurs during write, the blob handle might remain open or leak if not carefully managed with `try-finally`.

**Recommendation:**
- **Stream Support:** Allow passing a PHP stream resource directly to `fbird_execute` for BLOB parameters, letting the extension handle the chunked writing and closing internally. This would interact seamlessly with PHP streams.

## 5. Insert ID / Returning Support

**Current Behavior:**
- No native `fbird_last_insert_id` function.
- Reliance on `RETURNING` clause in SQL string manipulation.

**Recommendation:**
- **Native Helper:** Provide a function or metadata helper to retrieve the last generated value for a specific relation/field if available, or normalized support for `RETURNING` values in the result handle of an INSERT statement.

## 6. General Error Handling

**Current Behavior:**
- Heavy reliance on PHP Warnings (`E_WARNING`) for runtime errors (connection failure, SQL errors).

**Recommendation:**
- **Exceptions:** Opt-in mode to throw `Firebird\Exception` instead of emitting warnings. This aligns with modern PHP practices (PDO, etc.) and simplifies error handling flow.
