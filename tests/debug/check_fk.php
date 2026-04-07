<?php
require __DIR__ . '/../../vendor/autoload.php';
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

$platform = new class extends FirebirdPlatform {};
$table = new Table('"quoted"');
$table->addColumn('"create"', 'string', ['length' => 255, 'notnull' => true]);
$table->addColumn('foo', 'string', ['length' => 255, 'notnull' => true]);
$table->addColumn('"bar"', 'string', ['length' => 255, 'notnull' => true]);
$table->addForeignKeyConstraint('"foreign"', ['"create"', 'foo', '"bar"'], ['"create"', 'bar', '"foo-bar"'], [], 'FK_WITH_RESERVED_KEYWORD');
$table->addForeignKeyConstraint('foo', ['"create"', 'foo', '"bar"'], ['"create"', 'bar', '"foo-bar"'], [], 'FK_WITH_NON_RESERVED_KEYWORD');
$table->addForeignKeyConstraint('`foo-bar`', ['create', 'foo', '`bar`'], ['create', 'bar', '`foo-bar`'], [], 'FK_WITH_INTENDED_QUOTATION');
$found = $platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS);
foreach ($found as $i => $sql) {
    echo "[$i] $sql\n";
}
