<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use Throwable;

use function uniqid;

/**
 * Standalone DBAL-pattern test for FK constraint violations.
 *
 * Validates end-to-end: Firebird FK violation → ExceptionConverter → ForeignKeyConstraintViolationException.
 * Mirrors the DBAL 3.10.x Functional/ForeignKeyConstraintViolationsTest pattern.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/59
 */
class ForeignKeyConstraintViolationsTest extends FunctionalTestCase
{
    private string $tableParent = '';
    private string $tableChild  = '';

    public function tearDown(): void
    {
        $this->markConnectionNotReusable();

        $teardownConn  = TestUtil::getConnection();
        $schemaManager = $teardownConn->createSchemaManager();

        try {
            @$schemaManager->dropTable($this->tableChild);
        } catch (Throwable) {
        }

        try {
            @$schemaManager->dropTable($this->tableParent);
        } catch (Throwable) {
        }
    }

    public function testInsertForeignKeyConstraintViolation(): void
    {
        // Insert valid parent row first
        $this->connection->insert($this->tableParent, ['id' => 1]);

        $this->expectException(ForeignKeyConstraintViolationException::class);

        // Insert child row referencing non-existent parent id=99
        $this->connection->insert($this->tableChild, ['id' => 1, 'parent_id' => 99]);
    }

    public function testUpdateForeignKeyConstraintViolation(): void
    {
        // Set up valid parent + child
        $this->connection->insert($this->tableParent, ['id' => 1]);
        $this->connection->insert($this->tableChild, ['id' => 1, 'parent_id' => 1]);

        $this->expectException(ForeignKeyConstraintViolationException::class);

        // Update parent id — child still references old id=1
        $this->connection->update($this->tableParent, ['id' => 2], ['id' => 1]);
    }

    public function testDeleteForeignKeyConstraintViolation(): void
    {
        // Set up valid parent + child
        $this->connection->insert($this->tableParent, ['id' => 1]);
        $this->connection->insert($this->tableChild, ['id' => 1, 'parent_id' => 1]);

        $this->expectException(ForeignKeyConstraintViolationException::class);

        // Delete parent while child still references it
        $this->connection->delete($this->tableParent, ['id' => 1]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tableParent = 'fkv_parent_' . uniqid();
        $this->tableChild  = 'fkv_child_' . uniqid();

        $setupConn     = TestUtil::getConnection();
        $schemaManager = $setupConn->createSchemaManager();

        $parent = Table::editor()
            ->setUnquotedName($this->tableParent)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $child = Table::editor()
            ->setUnquotedName($this->tableChild)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('parent_id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $child->addForeignKeyConstraint($parent->getName(), ['parent_id'], ['id']);

        $schemaManager->createTable($parent);
        $schemaManager->createTable($child);
    }
}
