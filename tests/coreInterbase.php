<?php

/****
 * https://stackoverflow.com/questions/38343540/best-practices-when-using-php-with-firebird-or-interbase
 */
include __DIR__ . '/../vendor/autoload.php';

$database = '192.168.0.5/3050:/databases/auc-14-5.fdb';
$username = 'amicron03';
$password = 'klopf';
$charset = 'utf8';
$buffers = 0;
$dialect = 0;
$role = null;


$dbh = ibase_connect($database, $username, $password,$charset, $buffers, $dialect);
// Failure to connect
if ( !$dbh ) {
    throw new Exception( 'Failed to connect to database because: ' . ibase_errmsg(), ibase_errcode() );
}

$th = ibase_trans( $dbh, IBASE_READ+IBASE_COMMITTED+IBASE_REC_NO_VERSION);
if ( !$th ) {
    throw new Exception( 'Unable to create new transaction because: ' . ibase_errmsg(), ibase_errcode() );
}

$qs = 'select * from AUFTRAG';
$qh = ibase_query( $th, $qs );

if ( !$qh ) {
    throw new Exception( 'Unable to process query because: ' . ibase_errmsg(), ibase_errcode() );
}

$rows = array();
while ( $row = ibase_fetch_object( $qh ) ) {
    $rows[] = $row->KUNDENNR;
}

$th = ibase_trans( $dbh, IBASE_READ+IBASE_COMMITTED+IBASE_REC_NO_VERSION);
if ( !$th ) {
    throw new Exception( 'Unable to create new transaction because: ' . ibase_errmsg(), ibase_errcode() );
}

$qs = 'select * from AUFTRAG';
$qh = ibase_query( $th, $qs );

if ( !$qh ) {
    throw new Exception( 'Unable to process query because: ' . ibase_errmsg(), ibase_errcode() );
}

$rows = array();
while ( $row = ibase_fetch_object( $qh ) ) {
    $rows[] = $row->KUNDENNR;
}

// $rows[] now holds results. If there were any.

// Even though nothing was changed the transaction must be
// closed. Commit vs Rollback - question of style, but Commit
// is encouraged. And there shouldn't <gasp>used the S word</gasp>
// be an error for a read-only commit...

if ( !ibase_commit( $th ) ) {
    throw new Exception( 'Unable to commit transaction because: ' . ibase_errmsg(), ibase_errcode() );
}

// Good form would dictate error traps for these next two...
// ...but these are the least likely to break...
// and my fingers are getting tired.
// Release PHP memory for the result set, and formally
// close the database connection.
ibase_free_result( $qh );
ibase_close( $dbh );

$th = ibase_trans( $dbh, IBASE_READ+IBASE_COMMITTED+IBASE_REC_NO_VERSION);
if ( !$th ) {
    throw new Exception( 'Unable to create new transaction because: ' . ibase_errmsg(), ibase_errcode() );
}

$qs = 'select * from AUFTRAG';
$qh = ibase_query( $th, $qs );

if ( !$qh ) {
    throw new Exception( 'Unable to process query because: ' . ibase_errmsg(), ibase_errcode() );
}

$rows = array();
while ( $row = ibase_fetch_object( $qh ) ) {
    $rows[] = $row->KUNDENNR;
}

if ( !ibase_commit( $th ) ) {
    throw new Exception( 'Unable to commit transaction because: ' . ibase_errmsg(), ibase_errcode() );
}


if ( !ibase_commit( $th ) ) {
    throw new Exception( 'Unable to commit transaction because: ' . ibase_errmsg(), ibase_errcode() );
}
