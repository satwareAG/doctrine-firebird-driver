<?php
/**
 * MINIMAL REPRODUCTION: SIGFPE in fbird_batch_create() against Firebird 4.0
 *
 * Bug: Calling fbird_batch_create() on a prepared statement crashes with
 * SIGFPE (Floating point exception / division by zero) inside libfbclient.so.
 *
 * Environment:
 *   - PHP 8.4.19
 *   - php-firebird 10.3.6 (compiled with FB_API_VER=40)
 *   - libfbclient 4.0.5.3140 (Debian)
 *   - Firebird server 4.0.2 (jacobalberty/firebird:v4 Docker image)
 *
 * GDB stack trace shows:
 *   zif_fbird_batch_create → fbbatch_create → statement->createBatch()
 *   → libfbclient internal → division by zero (rdx=0)
 *
 * This is a php-firebird extension bug, NOT a doctrine-firebird-driver bug.
 *
 * Run inside Docker app container:
 *   docker compose exec -T -e DB_HOST=firebird4 app php /app/tests/debug/fb4-batch-repro.php
 */

$host = getenv('DB_HOST') ?: 'firebird4';
$db = '/firebird/data/test.fdb';
$user = 'sysdba';
$pass = 'masterkey';
$charset = 'UTF8';
$dsn = "{$host}:{$db}";

echo "=== SIGFPE Reproduction: fbird_batch_create() ===\n\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Extension: " . phpversion('firebird') . "\n";
echo "DSN: {$dsn}\n\n";

// Step 1: Connect
$conn = fbird_connect($dsn, $user, $pass, $charset);
if ($conn === false) {
    echo "FAILED to connect: " . fbird_errmsg() . "\n";
    exit(1);
}
echo "Connected (type=" . gettype($conn) . ")\n";

// Step 2: Get server version
$tx = fbird_trans($conn);
$result = fbird_query($tx, "SELECT rdb\$get_context('SYSTEM', 'ENGINE_VERSION') AS VER FROM RDB\$DATABASE");
$row = fbird_fetch_assoc($result);
echo "Server: Firebird " . ($row['VER'] ?? 'unknown') . "\n";
fbird_free_result($result);
fbird_commit($tx);

// Step 3: Create a test table
$tx = fbird_trans($conn);
fbird_query($tx, "EXECUTE BLOCK AS BEGIN
    IF (EXISTS(SELECT 1 FROM RDB\$RELATIONS WHERE RDB\$RELATION_NAME = 'BATCH_REPRO_TEST'))
    THEN EXECUTE STATEMENT 'DROP TABLE BATCH_REPRO_TEST';
END");
fbird_commit($tx);

$tx = fbird_trans($conn);
fbird_query($tx, "CREATE TABLE BATCH_REPRO_TEST (ID INTEGER NOT NULL PRIMARY KEY, NAME VARCHAR(100))");
fbird_commit($tx);
echo "Created BATCH_REPRO_TEST table\n";

// Step 4: Prepare an INSERT statement
$tx = fbird_trans($conn);
$stmt = fbird_prepare($conn, $tx, "INSERT INTO BATCH_REPRO_TEST (ID, NAME) VALUES (?, ?)");
if ($stmt === false) {
    echo "FAILED to prepare: " . fbird_errmsg() . "\n";
    exit(1);
}
echo "Prepared INSERT statement (type=" . gettype($stmt) . ")\n";

// Step 5: Check if fbird_batch_create exists
if (!function_exists('fbird_batch_create')) {
    echo "fbird_batch_create() does NOT exist\n";
    exit(1);
}
echo "fbird_batch_create() is available\n\n";

// Step 6: THIS IS THE CRASH POINT
echo ">>> Calling fbird_batch_create(\$stmt)...\n";
echo ">>> If this crashes with SIGFPE, the bug is confirmed.\n\n";

$batch = fbird_batch_create($stmt);

if ($batch === false) {
    echo "fbird_batch_create() returned false: " . fbird_errmsg() . "\n";
} else {
    echo "fbird_batch_create() succeeded: type=" . gettype($batch) . "\n";
    // Cleanup
    fbird_batch_cancel($batch);
}

// Cleanup
fbird_rollback($tx);

$tx = fbird_trans($conn);
fbird_query($tx, "DROP TABLE BATCH_REPRO_TEST");
fbird_commit($tx);

fbird_close($conn);
echo "\n=== Completed without crash ===\n";
