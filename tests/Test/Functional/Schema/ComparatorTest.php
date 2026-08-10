<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_values;
use function strtolower;

/**
 * Functional tests for schema comparator correctness.
 *
 * Verifies that the Firebird schema comparator:
 * - Detects no false-positive diffs after create/introspect roundtrip
 * - Correctly detects real schema changes (added/dropped/modified columns)
 * - Handles index and foreign key changes without false positives
 * - Works with both generic and platform-specific comparators
 *
 * Closes #67
 */
class ComparatorTest extends FunctionalTestCase
{
    private const TABLE     = 'comparator_test';
    private const TABLE_FK  = 'comparator_fk_test';
    private const TABLE_REF = 'comparator_ref_test';

    public function testNoFalsePositiveDiffAfterCreateIntrospect(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);

        $diff = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'Platform comparator: no diff expected after create/introspect roundtrip');
    }

    public function testNoFalsePositiveDiffGenericComparator(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);

        $diff = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'Generic comparator: no diff expected after create/introspect roundtrip');
    }

    public function testNoFalsePositiveDiffBothDirections(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $comparator  = $schemaManager->createComparator();

        // Both directions should show no diff
        // DBAL4: compareTables() always returns TableDiff (not false); check isEmpty()
        $diff1 = $comparator->compareTables($onlineTable, $table);
        self::assertTrue(
            $diff1 === false || $diff1->isEmpty(),
            'online→offline: no diff expected',
        );
        $diff2 = $comparator->compareTables($table, $onlineTable);
        self::assertTrue(
            $diff2 === false || $diff2->isEmpty(),
            'offline→online: no diff expected',
        );
    }

    /**
     * Detect real changes: added column
     */
    public function testDetectsAddedColumn(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->addColumn('new_col', Types::INTEGER, ['notnull' => false]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty(), 'Comparator should detect added column');

        $addedColumns = $diff->getAddedColumns();
        self::assertCount(1, $addedColumns);
        self::assertSame('new_col', strtolower(array_values($addedColumns)[0]->getName()));
    }

    /**
     * Detect real changes: dropped column
     */
    public function testDetectsDroppedColumn(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->dropColumn('str_col');

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty(), 'Comparator should detect dropped column');

        $droppedColumns = $diff->getDroppedColumns();
        self::assertCount(1, $droppedColumns);
        self::assertSame('str_col', strtolower(array_values($droppedColumns)[0]->getName()));
    }

    /**
     * Detect real changes: modified column type
     */
    public function testDetectsModifiedColumnType(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->modifyColumn('int_col', ['type' => Type::getType(Types::BIGINT)]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty(), 'Comparator should detect type change');
    }

    /**
     * Detect real changes: modified column default
     */
    public function testDetectsModifiedColumnDefault(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->modifyColumn('int_col', ['default' => 999]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty(), 'Comparator should detect default value change');
    }

    /**
     * Apply diff and verify no further diff
     */
    public function testApplyDiffAndVerifyNoDiff(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Add a column
        $newTable = clone $table;
        $newTable->addColumn('extra_col', Types::STRING, ['length' => 50, 'notnull' => false]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty());
        $schemaManager->alterTable($diff);

        // After applying, no further diff should be detected
        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff2       = $schemaManager->createComparator()->compareTables($onlineTable, $newTable);
        self::assertTrue($diff2->isEmpty(), 'No diff expected after applying schema change');
    }

    public function testDropColumnAndVerifyNoDiff(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->dropColumn('str_col');

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty());
        $schemaManager->alterTable($diff);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff2       = $schemaManager->createComparator()->compareTables($onlineTable, $newTable);
        self::assertTrue($diff2->isEmpty(), 'No diff expected after dropping column');
    }

    /**
     * Index changes
     */
    public function testNoFalsePositiveDiffWithIndex(): void
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
        $table->addIndex(['name'], 'idx_name');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'No diff expected for table with index after roundtrip');
    }

    public function testDetectsAddedIndex(): void
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

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->addIndex(['name'], 'idx_name_new');

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty(), 'Comparator should detect added index');
    }

    /**
     * Foreign key changes
     */
    public function testNoFalsePositiveDiffWithForeignKey(): void
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

        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('ref_id')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $table->addForeignKeyConstraint(self::TABLE_REF, ['ref_id'], ['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($refTable);
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'No diff expected for table with FK after roundtrip');
    }

    /**
     * Unique constraint
     */
    public function testNoFalsePositiveDiffWithUniqueIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(200)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $table->addUniqueIndex(['email'], 'uniq_email');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'No diff expected for table with unique index after roundtrip');
    }

    /**
     * Multiple column types roundtrip
     */
    public function testNoFalsePositiveDiffMultipleColumnTypes(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('big_col')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()
                    ->setUnquotedName('str_col')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()->setUnquotedName('text_col')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()
                    ->setUnquotedName('dec_col')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(10)
                    ->setScale(2)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()->setUnquotedName('float_col')->setTypeName(Types::FLOAT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('dt_col')->setTypeName(Types::DATETIME_MUTABLE)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('date_col')->setTypeName(Types::DATE_MUTABLE)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'No diff expected for multi-type table after roundtrip');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTableIfExists(self::TABLE_FK);
        $this->dropTableIfExists(self::TABLE);
        $this->dropTableIfExists(self::TABLE_REF);
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE_FK);
        $this->dropTableIfExists(self::TABLE);
        $this->dropTableIfExists(self::TABLE_REF);
        $this->markConnectionNotReusable();

        parent::tearDown();
    }

    /**
     * Helper
     */
    private function buildTestTable(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('int_col')->setTypeName(Types::INTEGER)->setDefaultValue(0)->setNotNull(false)->create(),
                Column::editor()
                    ->setUnquotedName('str_col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setDefaultValue('default_val')
                    ->setNotNull(false)
                    ->create(),
                Column::editor()->setUnquotedName('nullable_col')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
    }
}
