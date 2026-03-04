<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Regression test for GH-50: Schema test transaction deadlock.
 *
 * Firebird's commit_retaining (used in auto-commit mode) retains transaction
 * locks. When schema operations (CREATE TABLE, DROP TABLE) are interleaved
 * with DML inside the same transaction, a deadlock could occur because the
 * DDL statement tries to acquire an exclusive lock on a table that the
 * current transaction already holds a shared lock on.
 *
 * The fix ensures that schema operations commit/rollback the current
 * transaction before executing DDL.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/50
 */
class GH50Test extends FunctionalTestCase
{
    private const TABLE_A = 'gh50_table_a';
    private const TABLE_B = 'gh50_table_b';

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE_A);
        $this->dropTableIfExists(self::TABLE_B);
        parent::tearDown();
    }

    /**
     * Creating a table after a SELECT on another table must not deadlock.
     */
    public function testCreateTableAfterSelectDoesNotDeadlock(): void
    {
        // Create first table and insert data
        $tableA = new Table(self::TABLE_A);
        $tableA->addColumn('id', Types::INTEGER);
        $tableA->setPrimaryKey(['id']);

        $this->dropAndCreateTable($tableA);
        $this->connection->insert(self::TABLE_A, ['id' => 1]);

        // SELECT on table A (acquires shared lock via commit_retaining)
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE_A);
        self::assertSame('1', (string) $count);

        // CREATE TABLE B must not deadlock even though table A lock is held
        $tableB = new Table(self::TABLE_B);
        $tableB->addColumn('id', Types::INTEGER);
        $tableB->setPrimaryKey(['id']);

        // This must not throw a deadlock exception
        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->createTable($tableB);

        self::assertTrue($schemaManager->tablesExist([self::TABLE_B]));
    }

    /**
     * Dropping a table after a SELECT on a different table must not deadlock.
     */
    public function testDropTableAfterSelectDoesNotDeadlock(): void
    {
        // Create both tables
        $tableA = new Table(self::TABLE_A);
        $tableA->addColumn('id', Types::INTEGER);
        $tableA->setPrimaryKey(['id']);

        $tableB = new Table(self::TABLE_B);
        $tableB->addColumn('id', Types::INTEGER);
        $tableB->setPrimaryKey(['id']);

        $this->dropAndCreateTable($tableA);
        $this->dropAndCreateTable($tableB);

        // SELECT on table A
        $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE_A);

        // DROP TABLE B must not deadlock
        $schemaManager = $this->connection->createSchemaManager();
        $schemaManager->dropTable(self::TABLE_B);

        self::assertFalse($schemaManager->tablesExist([self::TABLE_B]));
    }

    /**
     * Multiple sequential schema operations must not accumulate deadlocks.
     */
    public function testSequentialSchemaOperationsDoNotDeadlock(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // Create, insert, select, drop — repeated to verify no lock accumulation
        for ($i = 1; $i <= 3; $i++) {
            $table = new Table(self::TABLE_A);
            $table->addColumn('id', Types::INTEGER);
            $table->setPrimaryKey(['id']);

            $this->dropAndCreateTable($table);
            $this->connection->insert(self::TABLE_A, ['id' => $i]);

            $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE_A);
            self::assertSame('1', (string) $count);

            $schemaManager->dropTable(self::TABLE_A);
            self::assertFalse($schemaManager->tablesExist([self::TABLE_A]));
        }
    }
}
