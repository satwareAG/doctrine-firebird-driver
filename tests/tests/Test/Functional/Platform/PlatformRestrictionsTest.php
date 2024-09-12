<?php

namespace Kafoso\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Schema\Table;

use Doctrine\DBAL\Types\Types;

use Kafoso\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;

use function str_repeat;

/**
 * This class holds tests that make sure generated SQL statements respect to platform restrictions
 * like maximum element name length
 */
class PlatformRestrictionsTest extends FunctionalTestCase
{
    /**
     * Tests element names that are at the boundary of the identifier length limit.
     * Ensures generated auto-increment identifier name respects to platform restrictions.
     */
    public function testMaxIdentifierLengthLimitWithAutoIncrement(): void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $tableName  = str_repeat('x', $platform->getMaxIdentifierLength());
        $columnName = str_repeat('y', $platform->getMaxIdentifierLength());
        $table      = new Table($tableName);
        $table->addColumn($columnName, Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey([$columnName]);
        $this->dropAndCreateTable($table);
        $createdTable = $this->connection->getSchemaManager()->introspectTable($tableName);

        $this->assertTrue($createdTable->hasColumn($columnName));
        $this->assertTrue($createdTable->hasPrimaryKey());
    }
}
