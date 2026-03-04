<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Verifies FirebirdPlatform::getAlterTableSQL() correctly generates DDL to add a PK
 * with generator/trigger to an existing table without data loss.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/62
 */
class NewPrimaryKeyWithNewAutoIncrementColumnTest extends FunctionalTestCase
{
    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testAddPrimaryKeyToExistingTable(): void
    {
        // Create table without PK
        $table = new Table('npk_nopk_table');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('name', Types::STRING, ['length' => 50]);
        $this->dropAndCreateTable($table);

        // Insert data before adding PK
        $this->connection->insert('npk_nopk_table', ['id' => 1, 'name' => 'Alice']);
        $this->connection->insert('npk_nopk_table', ['id' => 2, 'name' => 'Bob']);

        // Now create a new schema with PK added
        $newTable = new Table('npk_nopk_table');
        $newTable->addColumn('id', Types::INTEGER);
        $newTable->addColumn('name', Types::STRING, ['length' => 50]);
        $newTable->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $comparator    = $schemaManager->createComparator();
        $diff          = $comparator->compareTables($table, $newTable);

        // Execute the ALTER TABLE DDL
        $schemaManager->alterTable($diff);

        // Verify data is intact
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM npk_nopk_table');
        self::assertSame(2, (int) $count);

        // Verify PK constraint exists
        $introspected = $schemaManager->introspectTable('npk_nopk_table');
        self::assertNotNull($introspected->getPrimaryKey());
    }

    public function testAddAutoIncrementPrimaryKeyColumn(): void
    {
        // Create table without autoincrement PK
        $table = new Table('npk_autoinc_table');
        $table->addColumn('name', Types::STRING, ['length' => 50]);
        $this->dropAndCreateTable($table);

        // Insert data
        $this->connection->insert('npk_autoinc_table', ['name' => 'test']);

        // Create new schema with autoincrement PK
        $newTable = new Table('npk_autoinc_table');
        $newTable->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $newTable->addColumn('name', Types::STRING, ['length' => 50]);
        $newTable->setPrimaryKey(['id']);

        $schemaManager = $this->connection->createSchemaManager();
        $comparator    = $schemaManager->createComparator();
        $diff          = $comparator->compareTables($table, $newTable);

        // Execute the ALTER TABLE DDL — should not throw
        $schemaManager->alterTable($diff);

        // Verify table is queryable
        $rows = $this->connection->fetchAllAssociative('SELECT name FROM npk_autoinc_table');
        self::assertNotEmpty($rows);
    }

    public function testSequenceCreatedForAutoIncrementColumn(): void
    {
        // Create table with autoincrement PK from the start
        $table = new Table('npk_seq_table');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('val', Types::STRING, ['length' => 20]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        // Insert rows — autoincrement should work
        $this->connection->insert('npk_seq_table', ['val' => 'first']);
        $this->connection->insert('npk_seq_table', ['val' => 'second']);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, val FROM npk_seq_table ORDER BY id',
        );

        self::assertCount(2, $rows);
        // IDs should be sequential positive integers
        self::assertGreaterThan(0, (int) $rows[0]['id']);
        self::assertGreaterThan((int) $rows[0]['id'], (int) $rows[1]['id']);
    }
}
