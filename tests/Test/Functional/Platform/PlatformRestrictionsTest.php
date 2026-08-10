<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function str_repeat;
use function uniqid;

/**
 * This class holds tests that make sure generated SQL statements respect to platform restrictions
 * like maximum element name length
 */
class PlatformRestrictionsTest extends FunctionalTestCase
{
    private string $table;

    /**
     * Tests element names that are at the boundary of the identifier length limit.
     * Ensures generated auto-increment identifier name respects to platform restrictions.
     */
    public function testMaxIdentifierLengthLimitWithAutoIncrement(): void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $columnName = str_repeat('y', $platform->getMaxIdentifierLength());
        $table      = Table::editor()
            ->setUnquotedName($this->table)
            ->setColumns(
                Column::editor()->setUnquotedName($columnName)->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames($columnName)->create(),
            )
            ->create();
        $this->dropAndCreateTable($table);
        $createdTable = $this->connection->createSchemaManager()->introspectTable($this->table);

        self::assertTrue($createdTable->hasColumn($columnName));
        self::assertTrue($createdTable->getPrimaryKey() !== null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();
        $maxLen   = $platform->getMaxIdentifierLength();
        $suffix   = uniqid();
        $prefix   = str_repeat('x', $maxLen - 14); // 13 chars for uniqid + 1 for underscore

        $this->table = $prefix . '_' . $suffix;
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();

        parent::tearDown();
    }
}
