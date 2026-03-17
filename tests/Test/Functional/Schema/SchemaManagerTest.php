<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function array_map;
use function strtolower;

/**
 * Functional tests for FirebirdSchemaManager.
 *
 * Covers the full schema lifecycle:
 * - Table create, introspect, alter, drop
 * - Column types and constraints
 * - Indexes (regular, unique, composite)
 * - Foreign keys
 * - Sequences
 * - Views
 *
 * Closes #68
 */
class SchemaManagerTest extends FunctionalTestCase
{
    private const string TABLE     = 'sm_test_table';
    private const string TABLE_FK  = 'sm_fk_table';
    private const string TABLE_REF = 'sm_ref_table';
    private const string VIEW      = 'sm_test_view';
    private const string SEQ       = 'sm_test_seq';

    public function testCreateAndIntrospectTable(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        self::assertTrue($schemaManager->tablesExist([self::TABLE]));

        $introspected = $schemaManager->introspectTable(self::TABLE);
        self::assertTrue($introspected->hasColumn('id'));
        self::assertTrue($introspected->hasColumn('name'));
        self::assertTrue($introspected->hasColumn('val_col'));
    }

    public function testDropTable(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);
        self::assertTrue($schemaManager->tablesExist([self::TABLE]));

        $schemaManager->dropTable(self::TABLE);
        self::assertFalse($schemaManager->tablesExist([self::TABLE]));
    }

    public function testListTables(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $tableNames = array_map('strtolower', $schemaManager->listTableNames());
        self::assertContains(self::TABLE, $tableNames);
    }

    // =========================================================================
    // Column introspection
    // =========================================================================

    public function testListTableColumns(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);

        self::assertArrayHasKey('id', $columns);
        self::assertArrayHasKey('name', $columns);
        self::assertArrayHasKey('val_col', $columns);

        self::assertTrue($columns['id']->getNotnull());
        self::assertSame(255, $columns['name']->getLength());
    }

    public function testColumnDefaultValueIntrospection(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('status', Types::INTEGER, ['default' => 1, 'notnull' => false]);
        $table->addColumn('label', Types::STRING, ['length' => 50, 'default' => 'active', 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('1', $columns['status']->getDefault());
        self::assertSame('active', $columns['label']->getDefault());
    }

    public function testNullableColumnIntrospection(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('nullable_col', Types::STRING, ['length' => 100, 'notnull' => false]);
        $table->addColumn('notnull_col', Types::STRING, ['length' => 100, 'notnull' => true]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertFalse($columns['nullable_col']->getNotnull());
        self::assertTrue($columns['notnull_col']->getNotnull());
    }

    // =========================================================================
    // Alter table
    // =========================================================================

    public function testAlterTableAddColumn(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->addColumn('extra', Types::INTEGER, ['notnull' => false]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $introspected = $schemaManager->introspectTable(self::TABLE);
        self::assertTrue($introspected->hasColumn('extra'));
    }

    public function testAlterTableDropColumn(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->dropColumn('val_col');

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $introspected = $schemaManager->introspectTable(self::TABLE);
        self::assertFalse($introspected->hasColumn('val_col'));
    }

    public function testAlterTableModifyColumnDefault(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->changeColumn('val_col', ['default' => 42]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('42', $columns['val_col']->getDefault());
    }

    // =========================================================================
    // Indexes
    // =========================================================================

    public function testCreateAndListIndexes(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 100]);
        $table->addColumn('code', Types::STRING, ['length' => 20]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['name'], 'idx_sm_name');
        $table->addUniqueIndex(['code'], 'uniq_sm_code');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $indexes = $schemaManager->listTableIndexes(self::TABLE);

        self::assertArrayHasKey('primary', $indexes);
        self::assertArrayHasKey('idx_sm_name', $indexes);
        self::assertArrayHasKey('uniq_sm_code', $indexes);

        self::assertTrue($indexes['primary']->isPrimary());
        self::assertFalse($indexes['idx_sm_name']->isUnique());
        self::assertTrue($indexes['uniq_sm_code']->isUnique());
    }

    public function testDropAndCreateIndex(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 100]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['name'], 'idx_sm_drop_test');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $index = $table->getIndex('idx_sm_drop_test');
        $schemaManager->dropIndex($index, $table);

        $indexes = $schemaManager->listTableIndexes(self::TABLE);
        self::assertArrayNotHasKey('idx_sm_drop_test', $indexes);

        $schemaManager->createIndex($index, $table);
        $indexes = $schemaManager->listTableIndexes(self::TABLE);
        self::assertArrayHasKey('idx_sm_drop_test', $indexes);
    }

    // =========================================================================
    // Foreign keys
    // =========================================================================

    public function testCreateAndListForeignKeys(): void
    {
        $refTable = new Table(self::TABLE_REF);
        $refTable->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $refTable->setPrimaryKey(['id']);

        $fkTable = new Table(self::TABLE_FK);
        $fkTable->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $fkTable->addColumn('ref_id', Types::INTEGER, ['notnull' => false]);
        $fkTable->setPrimaryKey(['id']);
        $fkTable->addForeignKeyConstraint(self::TABLE_REF, ['ref_id'], ['id'], [], 'fk_sm_test');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($refTable);
        $schemaManager->createTable($fkTable);

        $fkeys = $schemaManager->listTableForeignKeys(self::TABLE_FK);
        self::assertCount(1, $fkeys);

        $fk = $fkeys[0];
        self::assertInstanceOf(ForeignKeyConstraint::class, $fk);
        self::assertSame([self::TABLE_REF], [strtolower($fk->getForeignTableName())]);
        self::assertSame(['ref_id'], array_map('strtolower', $fk->getLocalColumns()));
        self::assertSame(['id'], array_map('strtolower', $fk->getForeignColumns()));
    }

    public function testDropForeignKey(): void
    {
        $refTable = new Table(self::TABLE_REF);
        $refTable->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $refTable->setPrimaryKey(['id']);

        $fkTable = new Table(self::TABLE_FK);
        $fkTable->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $fkTable->addColumn('ref_id', Types::INTEGER, ['notnull' => false]);
        $fkTable->setPrimaryKey(['id']);
        $fkTable->addForeignKeyConstraint(self::TABLE_REF, ['ref_id'], ['id'], [], 'fk_sm_drop_test');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($refTable);
        $schemaManager->createTable($fkTable);

        $fkeys = $schemaManager->listTableForeignKeys(self::TABLE_FK);
        self::assertCount(1, $fkeys);

        $schemaManager->dropForeignKey($fkeys[0], self::TABLE_FK);

        $fkeys = $schemaManager->listTableForeignKeys(self::TABLE_FK);
        self::assertCount(0, $fkeys);
    }

    // =========================================================================
    // Sequences
    // =========================================================================

    public function testCreateAndDropSequence(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsSequences()) {
            self::markTestSkipped('Platform does not support sequences.');
        }

        $schemaManager = $this->connection->createSchemaManager();
        $sequence      = new Sequence(self::SEQ, 1, 1);
        $schemaManager->createSequence($sequence);

        if ($platform instanceof Firebird3Platform) {
            $sequences = $schemaManager->listSequences();
            $names     = array_map(static fn (Sequence $s): string => strtolower($s->getName()), $sequences);
            self::assertContains(self::SEQ, $names);
        }

        $schemaManager->dropSequence(self::SEQ);

        if (! ($platform instanceof Firebird3Platform)) {
            return;
        }

        $sequences = $schemaManager->listSequences();
        $names     = array_map(static fn (Sequence $s): string => strtolower($s->getName()), $sequences);
        self::assertNotContains(self::SEQ, $names);
    }

    // =========================================================================
    // Views
    // =========================================================================

    public function testCreateAndDropView(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $view = new View(self::VIEW, 'SELECT id, name FROM ' . self::TABLE);
        $schemaManager->createView($view);

        $views     = $schemaManager->listViews();
        $viewNames = array_map(static fn ($v): string => strtolower($v->getName()), $views);
        self::assertContains(self::VIEW, $viewNames);

        $schemaManager->dropView(self::VIEW);

        $views     = $schemaManager->listViews();
        $viewNames = array_map(static fn ($v): string => strtolower($v->getName()), $views);
        self::assertNotContains(self::VIEW, $viewNames);
    }

    // =========================================================================
    // tablesExist
    // =========================================================================

    public function testTablesExistReturnsTrueForExistingTable(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        self::assertTrue($schemaManager->tablesExist([self::TABLE]));
    }

    public function testTablesExistReturnsFalseForNonExistingTable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        self::assertFalse($schemaManager->tablesExist(['non_existing_table_xyz']));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $schemaManager = $this->connection->createSchemaManager();

        // Drop in dependency order
        try {
            $schemaManager->dropView(self::VIEW);
        } catch (Throwable) {
        }

        $this->dropTableIfExists(self::TABLE_FK);
        $this->dropTableIfExists(self::TABLE);
        $this->dropTableIfExists(self::TABLE_REF);

        try {
            $schemaManager->dropSequence(self::SEQ);
        } catch (Throwable) {
        }
    }

    protected function tearDown(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        try {
            $schemaManager->dropView(self::VIEW);
        } catch (Throwable) {
        }

        $this->dropTableIfExists(self::TABLE_FK);
        $this->dropTableIfExists(self::TABLE);
        $this->dropTableIfExists(self::TABLE_REF);

        try {
            $schemaManager->dropSequence(self::SEQ);
        } catch (Throwable) {
        }

        $this->markConnectionNotReusable();

        parent::tearDown();
    }// =========================================================================

// Table lifecycle
// =========================================================================


    // =========================================================================
    // Helper
    // =========================================================================

    private function buildTable(): Table
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('val_col', Types::INTEGER, ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        return $table;
    }
}
