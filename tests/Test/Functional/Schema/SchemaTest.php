<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_map;
use function strtolower;

/**
 * Functional tests for Schema-level operations.
 *
 * Covers:
 * - Schema introspection (introspectSchema)
 * - Schema object creation/migration (createSchemaObjects, migrateSchema)
 * - Multi-table schema with foreign keys
 * - Schema diff and migration
 *
 * Closes #68
 */
class SchemaTest extends FunctionalTestCase
{
    private const TABLE_A = 'schema_test_a';
    private const TABLE_B = 'schema_test_b';
    private const TABLE_C = 'schema_test_c';

    public function testIntrospectSchemaContainsCreatedTable(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        $table = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $schema = $schemaManager->introspectSchema();
        self::assertTrue($schema->hasTable(self::TABLE_A));
    }

    public function testIntrospectSchemaDoesNotContainDroppedTable(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        $table = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);
        $schemaManager->dropTable(self::TABLE_A);

        $schema = $schemaManager->introspectSchema();
        self::assertFalse($schema->hasTable(self::TABLE_A));
    }

    /**
     * createSchemaObjects
     */
    public function testCreateSchemaObjects(): void
    {
        $schema = new Schema();

        $tableA = $schema->createTable(self::TABLE_A);
        $tableA->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $tableA->addColumn('label', Types::STRING, ['length' => 100, 'notnull' => false]);
        $tableA->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );

        $tableB = $schema->createTable(self::TABLE_B);
        $tableB->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $tableB->addColumn('a_id', Types::INTEGER, ['notnull' => false]);
        $tableB->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $tableB->addForeignKeyConstraint(self::TABLE_A, ['a_id'], ['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        self::assertTrue($schemaManager->tablesExist([self::TABLE_A]));
        self::assertTrue($schemaManager->tablesExist([self::TABLE_B]));

        $fkeys = $schemaManager->listTableForeignKeys(self::TABLE_B);
        self::assertCount(1, $fkeys);
        self::assertSame(self::TABLE_A, strtolower($fkeys[0]->getForeignTableName()));
    }

    /**
     * migrateSchema
     */
    public function testMigrateSchemaAddTable(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        // Start with table A
        $schemaManager = $this->connection->createSchemaManager();

        $tableA = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $schemaManager->createTable($tableA);

        // Migrate to add table B
        $schema = $schemaManager->introspectSchema();
        $tableB = $schema->createTable(self::TABLE_B);
        $tableB->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $tableB->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );

        $schemaManager->migrateSchema($schema);

        self::assertTrue($schemaManager->tablesExist([self::TABLE_A]));
        self::assertTrue($schemaManager->tablesExist([self::TABLE_B]));
    }

    public function testMigrateSchemaDropTable(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        $schemaManager = $this->connection->createSchemaManager();

        $tableA = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName(self::TABLE_B)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager->createTable($tableA);
        $schemaManager->createTable($tableB);

        // Migrate to drop table B
        $schema = $schemaManager->introspectSchema();
        $schema->dropTable(self::TABLE_B);

        $schemaManager->migrateSchema($schema);

        self::assertTrue($schemaManager->tablesExist([self::TABLE_A]));
        self::assertFalse($schemaManager->tablesExist([self::TABLE_B]));
    }

    public function testMigrateSchemaAlterTable(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        $schemaManager = $this->connection->createSchemaManager();

        $tableA = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()
                    ->setUnquotedName('old_col')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $schemaManager->createTable($tableA);

        // Migrate: drop old_col, add new_col
        $schema       = $schemaManager->introspectSchema();
        $schemaTableA = $schema->getTable(self::TABLE_A);
        $schemaTableA->dropColumn('old_col');
        $schemaTableA->addColumn('new_col', Types::INTEGER, ['notnull' => false]);

        $schemaManager->migrateSchema($schema);

        $introspected = $schemaManager->introspectTable(self::TABLE_A);
        self::assertFalse($introspected->hasColumn('old_col'));
        self::assertTrue($introspected->hasColumn('new_col'));
    }

    /**
     * Schema diff
     */
    public function testSchemaDiffDetectsNewTable(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        $schemaManager = $this->connection->createSchemaManager();

        $tableA = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $schemaManager->createTable($tableA);

        $currentSchema = $schemaManager->introspectSchema();

        // Desired schema adds table B
        $desiredSchema = clone $currentSchema;
        $tableB        = $desiredSchema->createTable(self::TABLE_B);
        $tableB->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $tableB->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );

        $diff = $schemaManager->createComparator()->compareSchemas($currentSchema, $desiredSchema);

        $newTables     = $diff->getCreatedTables();
        $newTableNames = array_map(static fn (Table $t): string => strtolower($t->getName()), $newTables);
        self::assertContains(self::TABLE_B, $newTableNames);
    }

    public function testSchemaDiffDetectsDroppedTable(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        $schemaManager = $this->connection->createSchemaManager();

        $tableA = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $tableB = Table::editor()
            ->setUnquotedName(self::TABLE_B)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager->createTable($tableA);
        $schemaManager->createTable($tableB);

        $currentSchema = $schemaManager->introspectSchema();

        // Desired schema drops table B
        $desiredSchema = clone $currentSchema;
        $desiredSchema->dropTable(self::TABLE_B);

        $diff = $schemaManager->createComparator()->compareSchemas($currentSchema, $desiredSchema);

        $droppedTables     = $diff->getDroppedTables();
        $droppedTableNames = array_map(static fn (Table $t): string => strtolower($t->getName()), $droppedTables);
        self::assertContains(self::TABLE_B, $droppedTableNames);
    }

    public function testSchemaNoDiffAfterRoundtrip(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::markTestSkipped('DDL + introspect hangs on FB4+ due to implicit transaction commit');
        }

        $schemaManager = $this->connection->createSchemaManager();

        $tableA = Table::editor()
            ->setUnquotedName(self::TABLE_A)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $schemaManager->createTable($tableA);

        $schema1 = $schemaManager->introspectSchema();
        $schema2 = $schemaManager->introspectSchema();

        $diff = $schemaManager->createComparator()->compareSchemas($schema1, $schema2);

        self::assertEmpty($diff->getCreatedTables(), 'No new tables expected');
        self::assertEmpty($diff->getDroppedTables(), 'No dropped tables expected');
        self::assertEmpty($diff->getAlteredTables(), 'No altered tables expected');
    }

    /**
     * Multi-table schema with FK
     */
    public function testMultiTableSchemaWithForeignKeys(): void
    {
        $schema = new Schema();

        $parent = $schema->createTable(self::TABLE_A);
        $parent->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $parent->addColumn('name', Types::STRING, ['length' => 100, 'notnull' => false]);
        $parent->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );

        $child = $schema->createTable(self::TABLE_B);
        $child->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $child->addColumn('parent_id', Types::INTEGER, ['notnull' => false]);
        $child->addColumn('data', Types::TEXT, ['notnull' => false]);
        $child->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $child->addForeignKeyConstraint(self::TABLE_A, ['parent_id'], ['id']);

        $grandchild = $schema->createTable(self::TABLE_C);
        $grandchild->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $grandchild->addColumn('child_id', Types::INTEGER, ['notnull' => false]);
        $grandchild->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
        );
        $grandchild->addForeignKeyConstraint(self::TABLE_B, ['child_id'], ['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createSchemaObjects($schema);

        self::assertTrue($schemaManager->tablesExist([self::TABLE_A]));
        self::assertTrue($schemaManager->tablesExist([self::TABLE_B]));
        self::assertTrue($schemaManager->tablesExist([self::TABLE_C]));

        $fkeysB = $schemaManager->listTableForeignKeys(self::TABLE_B);
        self::assertCount(1, $fkeysB);

        $fkeysC = $schemaManager->listTableForeignKeys(self::TABLE_C);
        self::assertCount(1, $fkeysC);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTableIfExists(self::TABLE_C);
        $this->dropTableIfExists(self::TABLE_B);
        $this->dropTableIfExists(self::TABLE_A);
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE_C);
        $this->dropTableIfExists(self::TABLE_B);
        $this->dropTableIfExists(self::TABLE_A);
        $this->markConnectionNotReusable();

        parent::tearDown();
    }
}
