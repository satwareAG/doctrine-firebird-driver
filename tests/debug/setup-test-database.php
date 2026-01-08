<?php
/**
 * Creates the test database for SIGSEGV isolation tests using isql-fb
 *
 * This script creates a minimal Firebird database that can be used
 * for testing persistent connection behavior.
 */

declare(strict_types=1);

echo "=== Creating Test Database ===\n";

$host     = 'firebird3';
$port     = 3050;
$database = '/firebird/data/sigsegv-test.fdb';
$user     = 'SYSDBA';
$pass     = 'masterkey';

// Format: host/port:database_path
$dsn = $host . '/' . $port . ':' . $database;

echo "1. Checking if database already exists...\n";
echo "   DSN: $dsn\n";

// Try to connect first - if it works, database exists
$conn = @fbird_connect($dsn, $user, $pass);
if ($conn !== false) {
    echo "2. Database already exists, will use it\n";
    fbird_close($conn);
    echo "=== Database Ready ===\n";
    exit(0);
}

echo "2. Database does not exist, creating via isql-fb...\n";

// Create database using isql-fb utility
$sql = "CREATE DATABASE '$dsn' USER '$user' PASSWORD '$pass' DEFAULT CHARACTER SET UTF8;";
$cmd = "echo \"$sql\" | isql-fb -q 2>&1";

echo "   Running: $cmd\n";
$output     = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

if ($returnCode !== 0) {
    echo "ERROR: Failed to create database\n";
    echo 'Output: ' . implode("\n", $output) . "\n";
    exit(1);
}

echo "3. Database created, verifying connection...\n";

// Verify the database was created
$conn = @fbird_connect($dsn, $user, $pass);
if ($conn === false) {
    echo 'ERROR: Database created but cannot connect - ' . fbird_errmsg() . "\n";
    exit(1);
}

echo "4. Connection verified\n";
fbird_close($conn);

echo "=== Database Setup Complete ===\n";
