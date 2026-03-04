<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Standalone DBAL-pattern test for unique constraint violations.
 *
 * Validates end-to-end: Firebird unique violation → ExceptionConverter → UniqueConstraintViolationException.
 * Mirrors the DBAL 3.10.x Functional/UniqueConstraintViolationsTest pattern.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/60
 */
class UniqueConstraintViolationsTest extends FunctionalTestCase
{
    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testInsertDuplicatePrimaryKey(): void
    {
        $table = new Table('ucv_pk_table');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->insert('ucv_pk_table', ['id' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('ucv_pk_table', ['id' => 1]);
    }

    public function testInsertDuplicateUniqueIndex(): void
    {
        $table = new Table('ucv_uidx_table');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['val']);
        $this->dropAndCreateTable($table);

        $this->connection->insert('ucv_uidx_table', ['id' => 1, 'val' => 42]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('ucv_uidx_table', ['id' => 2, 'val' => 42]);
    }

    public function testInsertDuplicateUniqueConstraint(): void
    {
        $table = new Table('ucv_ucon_table');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('email', Types::STRING, ['length' => 100]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueConstraint(['email'], 'uq_ucv_email');
        $this->dropAndCreateTable($table);

        $this->connection->insert('ucv_ucon_table', ['id' => 1, 'email' => 'test@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('ucv_ucon_table', ['id' => 2, 'email' => 'test@example.com']);
    }
}
