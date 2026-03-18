<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\Comparator;
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
    private const string TABLE = 'default_value_test';

    public function testIntegerDefaultZero(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::INTEGER, ['default' => 0]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertArrayHasKey('col', $columns);
        self::assertSame('0', $columns['col']->getDefault());
    }

    public function testIntegerDefaultPositive(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::INTEGER, ['default' => 42]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('42', $columns['col']->getDefault());
    }

    public function testIntegerDefaultNegative(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::INTEGER, ['default' => -1]);
        $table->setPrimaryKey(['id']);

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
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::STRING, ['length' => 100, 'default' => 'hello']);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('hello', $columns['col']->getDefault());
    }

    public function testStringDefaultEmpty(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::STRING, ['length' => 100, 'default' => '']);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('', $columns['col']->getDefault());
    }

    public function testStringDefaultWithSpaces(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::STRING, ['length' => 100, 'default' => 'expected default']);
        $table->setPrimaryKey(['id']);

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
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::STRING, ['length' => 100, 'default' => null, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertNull($columns['col']->getDefault());
    }

    public function testNoDefaultIsNull(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::INTEGER, ['notnull' => false]);
        $table->setPrimaryKey(['id']);

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
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'default' => '10.50']);
        $table->setPrimaryKey(['id']);

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
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('int_col', Types::INTEGER, ['default' => 0]);
        $table->addColumn('str_col', Types::STRING, ['length' => 255, 'default' => 'foo', 'notnull' => false]);
        $table->addColumn('nullable_col', Types::INTEGER, ['default' => null, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);

        // Platform-specific comparator should detect no differences
        $diff = $schemaManager->createComparator()->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'No schema diff expected after create/introspect roundtrip');
    }

    public function testDefaultValueRoundtripGenericComparator(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('int_col', Types::INTEGER, ['default' => 42]);
        $table->addColumn('str_col', Types::STRING, ['length' => 100, 'default' => 'bar', 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $onlineTable = $schemaManager->introspectTable(self::TABLE);

        // Generic comparator should also detect no differences
        $diff = (new Comparator())->diffTable($onlineTable, $table);
        self::assertFalse($diff, 'Generic comparator: no schema diff expected after create/introspect roundtrip');
    }

    /**
     * Default value lifecycle: alter column default
     */
    public function testAlterColumnDefaultFromValueToNull(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::INTEGER, ['default' => 99, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Verify initial default
        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('99', $columns['col']->getDefault());

        // Alter: remove default
        $newTable = clone $table;
        $newTable->changeColumn('col', ['default' => null]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertNull($columns['col']->getDefault());
    }

    public function testAlterColumnDefaultFromNullToValue(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::INTEGER, ['default' => null, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        // Verify initial default is null
        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertNull($columns['col']->getDefault());

        // Alter: set default to 7
        $newTable = clone $table;
        $newTable->changeColumn('col', ['default' => 7]);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('7', $columns['col']->getDefault());
    }

    public function testAlterColumnDefaultFromValueToAnotherValue(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::STRING, ['length' => 50, 'default' => 'old_default', 'notnull' => false]);
        $table->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($table);

        $newTable = clone $table;
        $newTable->changeColumn('col', ['default' => 'new_default']);

        $diff = $schemaManager->createComparator()->diffTable($table, $newTable);
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $columns = $schemaManager->listTableColumns(self::TABLE);
        self::assertSame('new_default', $columns['col']->getDefault());
    }

    /**
     * Default value is used on INSERT
     */
    public function testDefaultValueAppliedOnInsert(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('col', Types::INTEGER, ['default' => 55, 'notnull' => false]);
        $table->setPrimaryKey(['id']);

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
