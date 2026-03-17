<?php
require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

// Mock PHPUnit globals for TestUtil
$GLOBALS['db_driver_class'] = Driver::class;
$GLOBALS['db_host'] = 'firebird3';
$GLOBALS['db_user'] = 'SYSDBA';
$GLOBALS['db_password'] = 'masterkey';
$GLOBALS['db_dbname'] = 'test.fdb';
$GLOBALS['db_charset'] = 'UTF8';

echo "Connecting..." . PHP_EOL;
$connection = TestUtil::getConnection();
echo "Connected OK" . PHP_EOL;

echo "Installing Firebird database schema (full)..." . PHP_EOL;

$schema = new Schema();
$tAlbum = $schema->createTable('Album_' . time());
$tAlbum->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
$tAlbum->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
$tAlbum->setPrimaryKey(['id']);

$tArtist = $schema->createTable('Artist_' . time());
$tArtist->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
$tArtist->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
$tArtist->setPrimaryKey(['id']);

$tSong = $schema->createTable('Song_' . time());
$tSong->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
$tSong->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
$tSong->addColumn('artist_id', 'integer', ['notnull' => true]);
$tSong->setPrimaryKey(['id']);

echo "Executing SQL..." . PHP_EOL;
$platform = $connection->getDatabasePlatform();
foreach ($schema->toSql($platform) as $sql) {
    echo "SQL: $sql" . PHP_EOL;
    $connection->executeStatement($sql);
}

echo "Database schema installed OK" . PHP_EOL;

$connection->close();
echo "Done." . PHP_EOL;
