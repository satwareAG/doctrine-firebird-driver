<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
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
        $table = Table::editor()
            ->setUnquotedName('ucv_pk_table')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $this->dropAndCreateTable($table);

        $this->connection->insert('ucv_pk_table', ['id' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('ucv_pk_table', ['id' => 1]);
    }

    public function testInsertDuplicateUniqueIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('ucv_uidx_table')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('val')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $table->addUniqueIndex(['val']);
        $this->dropAndCreateTable($table);

        $this->connection->insert('ucv_uidx_table', ['id' => 1, 'val' => 42]);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('ucv_uidx_table', ['id' => 2, 'val' => 42]);
    }

    public function testInsertDuplicateUniqueConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('ucv_ucon_table')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->setLength(100)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $table->addUniqueConstraint(['email'], 'uq_ucv_email');
        $this->dropAndCreateTable($table);

        $this->connection->insert('ucv_ucon_table', ['id' => 1, 'email' => 'test@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('ucv_ucon_table', ['id' => 2, 'email' => 'test@example.com']);
    }
}
