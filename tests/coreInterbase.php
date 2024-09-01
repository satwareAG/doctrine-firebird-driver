<?php

include __DIR__ . '/../vendor/autoload.php';

$database = '192.168.178.2/3050:/databases/auc-14-5.fdb';
$username = 'amicron03';
$password = 'klopf';
$charset = 'utf8';
$buffers = 0;
$dialect = 0;
$role = null;
$sync = null;

$ret = ibase_connect($database, $username, $password,$charset, $buffers, $dialect, $role);
fetchIbaseErrors();
ibase_commit($ret);
$trans = ibase_trans(null, $ret);
fetchIbaseErrors();
$stmt = ibase_prepare('SELECT * from ADRESSEN;');
$result = ibase_execute($stmt);

fetchIbaseErrors();
//var_dump(ibase_fetch_assoc($result));
echo $ret;
echo $ret2;
function fetchIbaseErrors()
{
    echo sprintf('%s: %s', ibase_errcode(), ibase_errmsg());
}
