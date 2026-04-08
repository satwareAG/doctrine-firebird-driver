<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_map;
use function strtolower;

class AlterColumnTest extends FunctionalTestCase
{
    public function testColumnPositionRetainedAfterAltering(): void
    {
        $table = new Table('test_alter');
        $table->addColumn('c1', Types::INTEGER);
        $table->addColumn('c2', Types::INTEGER);

        $this->dropAndCreateTable($table);

        $table->getColumn('c1')
            ->setType(Type::getType(Types::STRING));

        $sm         = $this->connection->createSchemaManager();
        $comparator = $sm->createComparator();
        $diff       = $comparator->compareTables($sm->introspectTable('test_alter'), $table);

        self::assertFalse($diff->isEmpty());
        $sm->alterTable($diff);

        $table = $sm->introspectTable('test_alter');
        // DBAL4: getColumns() returns numeric array; extract names via array_map
        self::assertSame(['c1', 'c2'], array_map(static fn ($col) => strtolower($col->getName()), $table->getColumns()));
    }
}
