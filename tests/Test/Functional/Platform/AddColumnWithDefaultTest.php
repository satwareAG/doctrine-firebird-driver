<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;


class AddColumnWithDefaultTest extends \Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase
{
    public function testAddColumnWithDefault(): void
    {
        $schemaManager = $this->connection->getSchemaManager();

        $table = new Table('add_default_test');

        $table->addColumn('original_field', Types::STRING);
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement("INSERT INTO add_default_test (original_field) VALUES ('one')");

        $table->addColumn('new_field', Types::STRING, ['default' => 'DEFAULT']);

        $diff = $schemaManager->createComparator()->diffTable(
            $schemaManager->introspectTable('add_default_test'),
            $table,
        );
        self::assertNotFalse($diff);
        $schemaManager->alterTable($diff);

        $query  = 'SELECT original_field, new_field FROM add_default_test';
        $result = $this->connection->fetchNumeric($query);
        self::assertSame(['one', 'DEFAULT'], $result);
    }
}
