<?php
/**
 * Minimal SIGSEGV reproduction test: Single persistent connection
 *
 * Uses fbird_* functions (php-firebird extension), NOT PDO.
 * Expected: SIGSEGV (exit 139) during PHP shutdown if bug present
 */

declare(strict_types=1);

echo "=== Test: Single Persistent Connection ===\n";

$host     = 'firebird3';
$port     = 3050;
$database = '/firebird/data/sigsegv-test.fdb';
$user     = 'SYSDBA';
$pass     = 'masterkey';

// Format: host/port:database_path
$dsn = $host . '/' . $port . ':' . $database;

echo "1. Creating persistent connection via fbird_pconnect...\n";
echo "   DSN: $dsn\n";

$conn = fbird_pconnect($dsn, $user, $pass);
if ($conn === false) {
    echo 'ERROR: Failed to connect - ' . fbird_errmsg() . "\n";
    exit(1);
}

echo "2. Connection established (persistent)\n";

echo "3. Executing simple query...\n";
$result = fbird_query($conn, 'SELECT 1 AS test_value FROM RDB$DATABASE');
if ($result === false) {
    echo 'ERROR: Query failed - ' . fbird_errmsg() . "\n";
    exit(1);
}

$row = fbird_fetch_assoc($result);
echo '4. Result: ' . var_export($row, true) . "\n";

echo "5. Freeing result...\n";
fbird_free_result($result);

echo "6. NOT calling fbird_close (persistent connection)...\n";
// Note: We intentionally do NOT call fbird_close() on persistent connections
// to test if the extension cleans up properly at MSHUTDOWN

echo "7. Script completed successfully\n";
echo "=== PHP shutdown will now occur ===\n";
// SIGSEGV expected to occur after this, during PHP MSHUTDOWN phase
