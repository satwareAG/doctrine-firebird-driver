<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\Comparator;
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

        $diff = $schemaManager->createComparator()->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'Platform comparator: no diff expected after create/introspect roundtrip');
    }

    public function testNoFalsePositiveDiffGenericComparator(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);

        $diff = (new Comparator())->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'Generic comparator: no diff expected after create/introspect roundtrip');
    }

    public function testNoFalsePositiveDiffBothDirections(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $comparator  = $schemaManager->createComparator();

        // Both directions should show no diff
        self::assertFalse(
            $comparator->diffTable($onlineTable, $table),
            'online→offline: no diff expected',
        );
        self::assertFalse(
            $comparator->diffTable($table, $onlineTable),
            'offline→online: no diff expected',
        );
    }

    // =========================================================================
    // Detect real changes: added column
    // =========================================================================

    public function testDetectsAddedColumn(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->addColumn('new_col', Types::INTEGER, ['notnull' => false]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff, 'Comparator should detect added column');

        $addedColumns = $diff->getAddedColumns();
        self::assertCount(1, $addedColumns);
        self::assertSame('new_col', strtolower(array_values($addedColumns)[0]->getName()));
    }

    // =========================================================================
    // Detect real changes: dropped column
    // =========================================================================

    public function testDetectsDroppedColumn(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->dropColumn('str_col');

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff, 'Comparator should detect dropped column');

        $droppedColumns = $diff->getDroppedColumns();
        self::assertCount(1, $droppedColumns);
        self::assertSame('str_col', strtolower(array_values($droppedColumns)[0]->getName()));
    }

    // =========================================================================
    // Detect real changes: modified column type
    // =========================================================================

    public function testDetectsModifiedColumnType(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->changeColumn('int_col', ['type' => Type::getType(Types::BIGINT)]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff, 'Comparator should detect type change');
    }

    // =========================================================================
    // Detect real changes: modified column default
    // =========================================================================

    public function testDetectsModifiedColumnDefault(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->changeColumn('int_col', ['default' => 999]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff, 'Comparator should detect default value change');
    }

    // =========================================================================
    // Apply diff and verify no further diff
    // =========================================================================

    public function testApplyDiffAndVerifyNoDiff(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Add a column
        $newTable = clone $table;
        $newTable->addColumn('extra_col', Types::STRING, ['length' => 50, 'notnull' => false]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        // After applying, no further diff should be detected
        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff2       = $schemaManager->createComparator()->diffTable($onlineTable, $newTable);
        self::assertFalse($diff2, 'No diff expected after applying schema change');
    }

    public function testDropColumnAndVerifyNoDiff(): void
    {
        $table = $this->buildTestTable();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->dropColumn('str_col');

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff2       = $schemaManager->createComparator()->diffTable($onlineTable, $newTable);
        self::assertFalse($diff2, 'No diff expected after dropping column');
    }

    // =========================================================================
    // Index changes
    // =========================================================================

    public function testNoFalsePositiveDiffWithIndex(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 100]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['name'], 'idx_name');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'No diff expected for table with index after roundtrip');
    }

    public function testDetectsAddedIndex(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 100]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->addIndex(['name'], 'idx_name_new');

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff, 'Comparator should detect added index');
    }

    // =========================================================================
    // Foreign key changes
    // =========================================================================

    public function testNoFalsePositiveDiffWithForeignKey(): void
    {
        $refTable = new Table(self::TABLE_REF);
        $refTable->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $refTable->setPrimaryKey(['id']);

        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('ref_id', Types::INTEGER, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint(self::TABLE_REF, ['ref_id'], ['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($refTable);
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'No diff expected for table with FK after roundtrip');
    }

    // =========================================================================
    // Unique constraint
    // =========================================================================

    public function testNoFalsePositiveDiffWithUniqueIndex(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('email', Types::STRING, ['length' => 200]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'uniq_email');

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'No diff expected for table with unique index after roundtrip');
    }

    // =========================================================================
    // Multiple column types roundtrip
    // =========================================================================

    public function testNoFalsePositiveDiffMultipleColumnTypes(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('big_col', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('str_col', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('text_col', Types::TEXT, ['notnull' => false]);
        $table->addColumn('dec_col', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('float_col', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('dt_col', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('date_col', Types::DATE_MUTABLE, ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);
        $diff        = $schemaManager->createComparator()->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'No diff expected for multi-type table after roundtrip');
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
    }// =========================================================================

// No false-positive diffs after roundtrip
// =========================================================================


    // =========================================================================
    // Helper
    // =========================================================================

    private function buildTestTable(): Table
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('int_col', Types::INTEGER, ['default' => 0, 'notnull' => false]);
        $table->addColumn('str_col', Types::STRING, ['length' => 100, 'default' => 'default_val', 'notnull' => false]);
        $table->addColumn('nullable_col', Types::INTEGER, ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        return $table;
    }
}
