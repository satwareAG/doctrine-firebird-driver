<?php
/**
 * Minimal SIGSEGV reproduction test: Persistent connection with open transaction
 *
 * Uses fbird_* functions (php-firebird extension), NOT PDO.
 * Tests if an open transaction at shutdown affects the crash
 * Expected: SIGSEGV (exit 139) during PHP shutdown if bug present
 */

declare(strict_types=1);

echo "=== Test: Persistent Connection with Open Transaction ===\n";

$host     = 'firebird3';
$port     = 3050;
$database = '/firebird/data/sigsegv-test.fdb';
$user     = 'SYSDBA';
$pass     = 'masterkey';

// Format: host/port:database_path
$dsn = $host . '/' . $port . ':' . $database;

echo "1. Creating persistent connection via fbird_pconnect...\n";
$conn = fbird_pconnect($dsn, $user, $pass);
if ($conn === false) {
    echo 'ERROR: Failed to connect - ' . fbird_errmsg() . "\n";
    exit(1);
}

echo "2. Connection established (persistent)\n";

echo "3. Starting explicit transaction...\n";
// Use fbird_trans_start with options array (php-firebird v7+ API)
$trans = fbird_trans_start($conn, [
    'access_mode' => FBIRD_WRITE,
    'isolation' => FBIRD_COMMITTED | FBIRD_REC_VERSION,
    'lock_resolution' => FBIRD_WAIT,
    'lock_timeout' => 5,
]);
if ($trans === false) {
    echo 'ERROR: Failed to start transaction - ' . fbird_errmsg() . "\n";
    exit(1);
}

echo "4. Transaction started\n";

echo "5. Executing query within transaction...\n";
$result = fbird_query($trans, 'SELECT 1 AS test_value FROM RDB$DATABASE');
if ($result === false) {
    echo 'ERROR: Query failed - ' . fbird_errmsg() . "\n";
    exit(1);
}

$row = fbird_fetch_assoc($result);
echo '6. Result: ' . var_export($row, true) . "\n";

echo "7. Freeing result...\n";
fbird_free_result($result);

echo "8. Committing transaction...\n";
if (! fbird_commit($trans)) {
    echo 'ERROR: Commit failed - ' . fbird_errmsg() . "\n";
    exit(1);
}

echo "9. Transaction committed\n";

echo "10. NOT calling fbird_close (persistent connection)...\n";
// Note: We intentionally do NOT call fbird_close() on persistent connections
// to test if the extension cleans up properly at MSHUTDOWN

echo "11. Script completed successfully\n";
echo "=== PHP shutdown will now occur ===\n";
// SIGSEGV expected to occur after this, during PHP MSHUTDOWN phase
