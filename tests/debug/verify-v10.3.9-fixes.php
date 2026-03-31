<?php
/**
 * Direct extension-level test for php-firebird v10.3.9 fixes.
 * Bypasses PHPUnit/Doctrine to verify C-level fixes work.
 *
 * Tests: #185 (IBatch GC_ADDREF), #184 (OO handle after close), #180 (SIGFPE guard)
 */

$host = getenv('DB_HOST') ?: 'firebird4';
$db = $host . ':/firebird/data/test.fdb';
$user = 'SYSDBA';
$pass = 'masterkey';

echo "=== php-firebird v" . phpversion('firebird') . " fix verification ===\n\n";

// === Test #180: SIGFPE guard on parameterless batch ===
echo "--- Test #180: SIGFPE guard on parameterless batch ---\n";
$conn = fbird_connect($db, $user, $pass);
if (!$conn) {
    die("Connection failed: " . fbird_errmsg() . "\n");
}

$query = fbird_query($conn, 'SELECT 1 FROM RDB$DATABASE');
$result = @fbird_batch_create($query);
if ($result === false) {
    echo "PASS: fbird_batch_create() correctly rejected parameterless statement\n";
    echo "  (No SIGFPE crash - #180 guard works)\n";
} else {
    echo "UNEXPECTED: batch created for parameterless statement\n";
    fbird_batch_cancel($result);
}
fbird_free_result($query);
fbird_close($conn);
echo "\n";

// === Test #185: IBatch handle survives GC ===
echo "--- Test #185: IBatch handle after GC ---\n";
$conn = fbird_connect($db, $user, $pass);
if (!$conn) {
    die("Connection failed: " . fbird_errmsg() . "\n");
}

// Create a temp table
$trans = fbird_trans($conn);
fbird_query($trans, 'RECREATE TABLE batch_verify_test (id INTEGER, name VARCHAR(50))');
fbird_commit($trans);

$trans = fbird_trans($conn);
$query = fbird_prepare($trans, 'INSERT INTO batch_verify_test (id, name) VALUES (?, ?)');
$batch = fbird_batch_create($query);

if (!$batch) {
    echo "FAIL: fbird_batch_create() failed: " . fbird_errmsg() . "\n";
} else {
    // Add some rows
    fbird_batch_add($batch, 1, 'Alice');
    fbird_batch_add($batch, 2, 'Bob');

    // Force GC to test if query resource survives
    unset($query);
    gc_collect_cycles();

    // Execute - this is where "invalid batch handle" would occur if #185 not fixed
    $result = fbird_batch_execute($batch);
    if ($result === false) {
        echo "FAIL: fbird_batch_execute() failed after GC: " . fbird_errmsg() . "\n";
    } else {
        echo "PASS: Batch executed successfully after GC (result type: " . gettype($result) . ")\n";
    }
}
fbird_commit($trans);

// Cleanup
$trans = fbird_trans($conn);
fbird_query($trans, 'DROP TABLE batch_verify_test');
fbird_commit($trans);
fbird_close($conn);
echo "\n";

// === Test #184: OO Connection::close() handle preservation ===
echo "--- Test #184: OO Connection close + procedural reconnect ---\n";
if (class_exists('Firebird\Connection')) {
    // Test: OO close then procedural fbird_connect
    $ooConn = new \Firebird\Connection($db, $user, $pass);
    echo "  OO Connection created\n";
    $ooConn->close();
    echo "  OO Connection closed\n";

    // Procedural reconnect - verifies default_link is properly cleared
    $conn2 = fbird_connect($db, $user, $pass);
    if ($conn2) {
        $res = fbird_query($conn2, 'SELECT 1 FROM RDB$DATABASE');
        if ($res) {
            echo "PASS: Procedural reconnect after OO close works\n";
            fbird_free_result($res);
        } else {
            echo "FAIL: Query failed after reconnect: " . fbird_errmsg() . "\n";
        }
        fbird_close($conn2);
    } else {
        echo "FAIL: fbird_connect failed after OO close: " . fbird_errmsg() . "\n";
    }

    // Test: OO close then OO reconnect
    $ooConn3 = new \Firebird\Connection($db, $user, $pass);
    echo "  OO Connection 3 created\n";
    $ooConn3->close();
    echo "  OO Connection 3 closed\n";
    $ooConn4 = new \Firebird\Connection($db, $user, $pass);
    try {
        $ooConn4->execute('SELECT 1 FROM RDB$DATABASE');
        echo "PASS: OO reconnect after OO close works\n";
    } catch (Throwable $e) {
        echo "FAIL: OO reconnect error: " . $e->getMessage() . "\n";
    }
    $ooConn4->close();
} else {
    echo "SKIP: Firebird\Connection class not available\n";
}

// === Test #185 via OO API (same path as BatchTest.php) ===
echo "\n--- Test #185 via OO wrapper (Firebird\\Batch) ---\n";
if (class_exists('Firebird\Connection') && class_exists('Firebird\Batch')) {
    $ooConn3 = new \Firebird\Connection($db, $user, $pass);

    // Create temp table
    $ooConn3->execute('RECREATE TABLE oo_batch_test (id INTEGER, name VARCHAR(50))');
    $ooConn3->commit();

    try {
        $batch = $ooConn3->createBatch('INSERT INTO oo_batch_test (id, name) VALUES (?, ?)');
        $batch->add(1, 'Alice');
        $batch->add(2, 'Bob');
        $result = $batch->execute();
        echo "PASS: OO Batch executed, successCount=" . $result->successCount . "\n";
    } catch (Throwable $e) {
        echo "FAIL: OO Batch error: " . $e->getMessage() . "\n";
    }

    // Cleanup
    $ooConn3->execute('DROP TABLE oo_batch_test');
    $ooConn3->commit();
    $ooConn3->close();
} else {
    echo "SKIP: Firebird\Batch class not available\n";
}

echo "\n=== Done ===\n";
