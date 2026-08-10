<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\Types;
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
    private const TABLE     = 'sm_test_table';
    private const TABLE_FK  = 'sm_fk_table';
    private const TABLE_REF = 'sm_ref_table';
    private const VIEW      = 'sm_test_view';
    private const SEQ       = 'sm_test_seq';

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

    /**
     * Column introspection
     */
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
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::INTEGER)->setDefaultValue(1)->setNotNull(false)->create(),
                Column::editor()
                    ->setUnquotedName('label')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->setDefaultValue('active')
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('1', $columns['status']->getDefault());
        self::assertSame('active', $columns['label']->getDefault());
    }

    public function testNullableColumnIntrospection(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()
                    ->setUnquotedName('nullable_col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('notnull_col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setNotNull(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertFalse($columns['nullable_col']->getNotnull());
        self::assertTrue($columns['notnull_col']->getNotnull());
    }

    /**
     * Alter table
     */
    public function testAlterTableAddColumn(): void
    {
        $table = $this->buildTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->addColumn('extra', Types::INTEGER, ['notnull' => false]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
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

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
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
        $newTable->modifyColumn('val_col', ['default' => 42]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('42', $columns['val_col']->getDefault());
    }

    /**
     * Indexes
     */
    public function testCreateAndListIndexes(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(100)->create(),
                Column::editor()->setUnquotedName('code')->setTypeName(Types::STRING)->setLength(20)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
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
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(100)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $table->addIndex(['name'], 'idx_sm_drop_test');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $index = $table->getIndex('idx_sm_drop_test');
        $schemaManager->dropIndex($index->getName(), $table->getName());

        $indexes = $schemaManager->listTableIndexes(self::TABLE);
        self::assertArrayNotHasKey('idx_sm_drop_test', $indexes);

        $schemaManager->createIndex($index, $table->getName());
        $indexes = $schemaManager->listTableIndexes(self::TABLE);
        self::assertArrayHasKey('idx_sm_drop_test', $indexes);
    }

    /**
     * Foreign keys
     */
    public function testCreateAndListForeignKeys(): void
    {
        $refTable = Table::editor()
            ->setUnquotedName(self::TABLE_REF)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $fkTable = Table::editor()
            ->setUnquotedName(self::TABLE_FK)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('ref_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
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
        $refTable = Table::editor()
            ->setUnquotedName(self::TABLE_REF)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $fkTable = Table::editor()
            ->setUnquotedName(self::TABLE_FK)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('ref_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $fkTable->addForeignKeyConstraint(self::TABLE_REF, ['ref_id'], ['id'], [], 'fk_sm_drop_test');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($refTable);
        $schemaManager->createTable($fkTable);

        $fkeys = $schemaManager->listTableForeignKeys(self::TABLE_FK);
        self::assertCount(1, $fkeys);

        $schemaManager->dropForeignKey($fkeys[0]->getName(), self::TABLE_FK);

        $fkeys = $schemaManager->listTableForeignKeys(self::TABLE_FK);
        self::assertCount(0, $fkeys);
    }

    /**
     * Sequences
     */
    public function testCreateAndDropSequence(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->supportsSequences()) {
            self::markTestSkipped('Platform does not support sequences.');
        }

        $schemaManager = $this->connection->createSchemaManager();
        $sequence      = Sequence::editor()
            ->setUnquotedName(self::SEQ)
            ->setAllocationSize(1)
            ->setInitialValue(1)
            ->create();
        $schemaManager->createSequence($sequence);

        $sequences = $schemaManager->listSequences();
        $names     = array_map(static fn (Sequence $s): string => strtolower($s->getName()), $sequences);
        self::assertContains(strtolower(self::SEQ), $names);

        $schemaManager->dropSequence(self::SEQ);

        $sequences = $schemaManager->listSequences();
        $names     = array_map(static fn (Sequence $s): string => strtolower($s->getName()), $sequences);
        self::assertNotContains(strtolower(self::SEQ), $names);
    }

    /**
     * Views
     */
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

    /**
     * tablesExist
     */
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
    }

    /**
     * Helper
     */
    private function buildTable(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('val_col')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
    }
}
