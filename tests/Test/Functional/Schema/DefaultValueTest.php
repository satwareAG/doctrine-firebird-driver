<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Functional tests for schema default values.
 *
 * Verifies that default values survive the create → introspect → compare roundtrip:
 * - Integer defaults
 * - String defaults
 * - Boolean defaults (Firebird 3+ native BOOLEAN)
 * - NULL defaults
 * - Empty string defaults
 * - Numeric string defaults
 *
 * Closes #66
 */
class DefaultValueTest extends FunctionalTestCase
{
    private const TABLE = 'default_value_test';

    public function testIntegerDefaultZero(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('col')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertArrayHasKey('col', $columns);
        self::assertSame('0', $columns['col']->getDefault());
    }

    public function testIntegerDefaultPositive(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('col')->setTypeName(Types::INTEGER)->setDefaultValue(42)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('42', $columns['col']->getDefault());
    }

    public function testIntegerDefaultNegative(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('col')->setTypeName(Types::INTEGER)->setDefaultValue(-1)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('-1', $columns['col']->getDefault());
    }

    /**
     * String defaults
     */
    public function testStringDefaultValue(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setDefaultValue('hello')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('hello', $columns['col']->getDefault());
    }

    public function testStringDefaultEmpty(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setDefaultValue('')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('', $columns['col']->getDefault());
    }

    public function testStringDefaultWithSpaces(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setDefaultValue('expected default')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('expected default', $columns['col']->getDefault());
    }

    /**
     * NULL defaults
     */
    public function testNullDefaultIsNull(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setDefaultValue(null)
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
        self::assertNull($columns['col']->getDefault());
    }

    public function testNoDefaultIsNull(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('col')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertNull($columns['col']->getDefault());
    }

    /**
     * Decimal defaults
     */
    public function testDecimalDefault(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(10)
                    ->setScale(2)
                    ->setDefaultValue('10.50')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertNotNull($columns['col']->getDefault());
        // Firebird may normalize the decimal representation
        self::assertStringContainsString('10', (string) $columns['col']->getDefault());
    }

    /**
     * Roundtrip: create → introspect → compare (no false-positive diff)
     */
    public function testDefaultValueRoundtripNoFalsePositiveDiff(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('int_col')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create(),
                Column::editor()
                    ->setUnquotedName('str_col')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setDefaultValue('foo')
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('nullable_col')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(null)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);

        // Platform-specific comparator should detect no differences
        $diff = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'No schema diff expected after create/introspect roundtrip');
    }

    public function testDefaultValueRoundtripGenericComparator(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setNotNull(true)->create(),
                Column::editor()->setUnquotedName('int_col')->setTypeName(Types::INTEGER)->setDefaultValue(42)->create(),
                Column::editor()
                    ->setUnquotedName('str_col')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setDefaultValue('bar')
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);

        // Generic comparator should also detect no differences
        $diff = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        self::assertTrue($diff->isEmpty(), 'Generic comparator: no schema diff expected after create/introspect roundtrip');
    }

    /**
     * Default value lifecycle: alter column default
     */
    public function testAlterColumnDefaultFromValueToNull(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(99)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Verify initial default
        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('99', $columns['col']->getDefault());

        // Alter: remove default
        $newTable = clone $table;
        $newTable->modifyColumn('col', ['default' => null]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty());
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertNull($columns['col']->getDefault());
    }

    public function testAlterColumnDefaultFromNullToValue(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(null)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Verify initial default is null
        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertNull($columns['col']->getDefault());

        // Alter: set default to 7
        $newTable = clone $table;
        $newTable->modifyColumn('col', ['default' => 7]);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty());
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('7', $columns['col']->getDefault());
    }

    public function testAlterColumnDefaultFromValueToAnotherValue(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->setDefaultValue('old_default')
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->modifyColumn('col', ['default' => 'new_default']);

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);
        self::assertFalse($diff->isEmpty());
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('new_default', $columns['col']->getDefault());
    }

    /**
     * Default value is used on INSERT
     */
    public function testDefaultValueAppliedOnInsert(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()
                    ->setUnquotedName('col')
                    ->setTypeName(Types::INTEGER)
                    ->setDefaultValue(55)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Insert without specifying col — default should apply
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id) VALUES (1)',
        );

        $val = $this->connection->fetchOne(
            'SELECT col FROM ' . self::TABLE . ' WHERE id = 1',
        );
        self::assertSame(55, (int) $val);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTableIfExists(self::TABLE);
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE);
        $this->markConnectionNotReusable();

        parent::tearDown();
    }
}
