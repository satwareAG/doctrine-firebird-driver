<?php
/**
 * Minimal SIGSEGV reproduction test: Regular (non-persistent) connection
 *
 * Uses fbird_* functions (php-firebird extension), NOT PDO.
 * Control test - regular connections should NOT cause SIGSEGV
 * Expected: Clean exit (exit 0) - no SIGSEGV
 */

declare(strict_types=1);

echo "=== Test: Regular (Non-Persistent) Connection ===\n";

$host     = 'firebird3';
$port     = 3050;
$database = '/firebird/data/sigsegv-test.fdb';
$user     = 'SYSDBA';
$pass     = 'masterkey';

// Format: host/port:database_path
$dsn = $host . '/' . $port . ':' . $database;

echo "1. Creating regular connection via fbird_connect (NOT persistent)...\n";
echo "   DSN: $dsn\n";

$conn = fbird_connect($dsn, $user, $pass);
if ($conn === false) {
    echo 'ERROR: Failed to connect - ' . fbird_errmsg() . "\n";
    exit(1);
}

echo "2. Connection established (non-persistent)\n";

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

echo "6. Explicitly closing connection with fbird_close...\n";
fbird_close($conn);
echo "7. Connection closed\n";

echo "8. Script completed successfully\n";
echo "=== PHP shutdown will now occur ===\n";
// Should exit cleanly (exit 0) - no SIGSEGV expected for non-persistent connections
