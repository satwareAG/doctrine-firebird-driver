<?php
/**
 * Minimal SIGSEGV reproduction test: Multiple persistent connections
 *
 * Uses fbird_* functions (php-firebird extension), NOT PDO.
 * Tests if having multiple persistent connections affects the crash
 * Expected: SIGSEGV (exit 139) during PHP shutdown if bug present
 */

declare(strict_types=1);

echo "=== Test: Multiple Persistent Connections ===\n";

$host     = 'firebird3';
$port     = 3050;
$database = '/firebird/data/sigsegv-test.fdb';
$user     = 'SYSDBA';
$pass     = 'masterkey';

// Format: host/port:database_path
$dsn = $host . '/' . $port . ':' . $database;

$connections = [];

echo "1. Creating 3 persistent connections via fbird_pconnect...\n";
for ($i = 1; $i <= 3; $i++) {
    echo "   Creating connection #$i...\n";
    $conn = fbird_pconnect($dsn, $user, $pass);
    if ($conn === false) {
        echo "ERROR: Failed to connect #$i - " . fbird_errmsg() . "\n";
        exit(1);
    }

    $connections[$i] = $conn;
}

echo "2. All connections established (persistent)\n";

echo "3. Executing query on each connection...\n";
foreach ($connections as $i => $conn) {
    $result = fbird_query($conn, "SELECT $i AS conn_num FROM RDB\$DATABASE");
    if ($result === false) {
        echo "ERROR: Query failed on #$i - " . fbird_errmsg() . "\n";
        exit(1);
    }

    $row = fbird_fetch_assoc($result);
    echo "   Connection #$i result: " . var_export($row, true) . "\n";
    fbird_free_result($result);
}

echo "4. NOT calling fbird_close (persistent connections)...\n";
// Note: We intentionally do NOT call fbird_close() on persistent connections
// to test if the extension cleans up properly at MSHUTDOWN

echo "5. Script completed successfully\n";
echo "=== PHP shutdown will now occur ===\n";
// SIGSEGV expected to occur after this, during PHP MSHUTDOWN phase
