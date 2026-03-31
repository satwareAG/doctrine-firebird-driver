<?php
/**
 * Test: Does fbird_batch_create() crash when used with a SELECT statement?
 *
 * The BatchTest::setUp() calls createBatch('SELECT 1 FROM RDB$DATABASE')
 * to verify IBatch support. If SELECT has no input parameters, the message
 * length could be 0, causing division by zero in libfbclient.
 */

$host = getenv('DB_HOST') ?: 'firebird4';
$dsn = "{$host}:/firebird/data/test.fdb";

echo "=== Test: fbird_batch_create() with SELECT ===\n\n";

$conn = fbird_connect($dsn, 'sysdba', 'masterkey', 'UTF8');
echo "Connected\n";

// Test 1: SELECT without parameters (this is what BatchTest does)
echo "\nTest 1: SELECT 1 FROM RDB\$DATABASE (no input params)\n";
$tx = fbird_trans($conn);
$stmt = fbird_prepare($conn, $tx, "SELECT 1 FROM RDB\$DATABASE");
echo "  Prepared (type=" . gettype($stmt) . ")\n";
echo "  >>> Calling fbird_batch_create()...\n";
$batch = @fbird_batch_create($stmt);
if ($batch === false) {
    echo "  Returned false: " . fbird_errmsg() . "\n";
} else {
    echo "  Succeeded (type=" . gettype($batch) . ")\n";
    @fbird_batch_cancel($batch);
}
fbird_rollback($tx);

// Test 2: SELECT with parameters
echo "\nTest 2: SELECT 1 FROM RDB\$DATABASE WHERE 1=? (with input param)\n";
$tx = fbird_trans($conn);
$stmt2 = fbird_prepare($conn, $tx, "SELECT 1 FROM RDB\$DATABASE WHERE 1=?");
echo "  Prepared (type=" . gettype($stmt2) . ")\n";
echo "  >>> Calling fbird_batch_create()...\n";
$batch2 = @fbird_batch_create($stmt2);
if ($batch2 === false) {
    echo "  Returned false: " . fbird_errmsg() . "\n";
} else {
    echo "  Succeeded (type=" . gettype($batch2) . ")\n";
    @fbird_batch_cancel($batch2);
}
fbird_rollback($tx);

fbird_close($conn);
echo "\n=== Completed ===\n";
