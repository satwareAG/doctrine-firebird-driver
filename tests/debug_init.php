<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/phpunit.bootstrap.php';

use Satag\DoctrineFirebirdDriver\Test\TestUtil;

// Simulate phpunit.xml globals
$GLOBALS['db_driver_class'] = 'Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver';
$GLOBALS['db_host']         = getenv('DB_HOST') ?: '127.0.0.1';
$GLOBALS['db_user']         = 'SYSDBA';
$GLOBALS['db_password']     = 'masterkey';
$GLOBALS['db_dbname']       = 'test.fdb';
$GLOBALS['db_charset']      = 'UTF8';

echo 'Calling TestUtil::initializeDatabase...' . PHP_EOL;
TestUtil::initializeDatabase(true, 'TestClass');
echo 'initializeDatabase returned OK' . PHP_EOL;
