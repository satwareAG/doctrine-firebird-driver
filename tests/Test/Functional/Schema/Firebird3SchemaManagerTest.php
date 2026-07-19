<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\Large;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

#[Large]
class Firebird3SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * Skip testListDatabases for Firebird in CI environments.
     *
     * Creating databases via fbird_query(FBIRD_CREATE, "CREATE DATABASE...")
     * requires server-side filesystem access. In Docker CI environments,
     * the Firebird server container has a different filesystem than the
     * PHP test container, so database files cannot be created in paths
     * relative to the test database location.
     */
    public function testListDatabases(): void
    {
        self::markTestSkipped(
            'Firebird CREATE DATABASE requires server-side filesystem access, ' .
            'which is not available in containerized CI environments.',
        );
    }

    /**
     * Override parent test to use table-level operations instead of full schema introspection.
     * Full introspectSchema() is too slow in Firebird as it scans all system tables.
     */
    public function testMigrateSchema(): void
    {
        $this->createTestTable('table_to_alter');
        $this->createTestTable('table_to_drop');

        // Use table-level introspection instead of full schema (much faster)
        $tableToAlter = $this->schemaManager->introspectTable('table_to_alter');

        // Drop column and add new one
        $newTableToAlter = clone $tableToAlter;
        $newTableToAlter->dropColumn('foreign_key_test');
        $newTableToAlter->addColumn('number', Types::INTEGER);

        $diff = $this->schemaManager->createComparator()->compareTables($tableToAlter, $newTableToAlter);
        if ($diff !== false) {
            $this->schemaManager->alterTable($diff);
        }

        // Drop the other table
        $this->schemaManager->dropTable('table_to_drop');

        // Create new table
        $tableToCreate = new Table('table_to_create');
        $tableToCreate->addColumn('id', Types::INTEGER, ['notnull' => true]);
        $tableToCreate->setPrimaryKey(['id']);
        $this->dropTableIfExists('table_to_create');
        $this->schemaManager->createTable($tableToCreate);

        // Verify using table-level checks (faster than introspectSchema)
        $alteredTable = $this->schemaManager->introspectTable('table_to_alter');
        self::assertFalse($alteredTable->hasColumn('foreign_key_test'));
        self::assertTrue($alteredTable->hasColumn('number'));

        self::assertFalse($this->schemaManager->tablesExist(['table_to_drop']));
        self::assertTrue($this->schemaManager->tablesExist(['table_to_create']));
    }

    public function testSchemaIntrospection(): void
    {
        parent::testSchemaIntrospection();
    }

    public function testGetBooleanColumn(): void
    {
        $table = new Table('boolean_column_test');
        $table->addColumn('bool', Types::BOOLEAN);
        $table->addColumn('bool_commented', Types::BOOLEAN, ['comment' => "That's a comment"]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('boolean_column_test');

        self::assertInstanceOf(BooleanType::class, $columns['bool']->getType());
        self::assertInstanceOf(BooleanType::class, $columns['bool_commented']->getType());

        // DBAL4: Column::getComment() returns '' (never null); empty comment = ''
        self::assertSame('', $columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }

    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof Firebird3Platform;
    }
}
