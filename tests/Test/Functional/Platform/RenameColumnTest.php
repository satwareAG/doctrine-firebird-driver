<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_map;
use function strtolower;
use function uniqid;

class RenameColumnTest extends FunctionalTestCase
{
    private string $table;

    #[DataProvider('columnNameProvider')]
    public function testColumnPositionRetainedAfterRenaming(string $columnName, string $newColumnName): void
    {
        $table = Table::editor()
            ->setUnquotedName($this->table)
            ->setColumns(
                Column::editor()->setUnquotedName($columnName)->setTypeName(Types::STRING)->create(),
                Column::editor()->setUnquotedName('c2')->setTypeName(Types::INTEGER)->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table->dropColumn($columnName)
            ->addColumn($newColumnName, Types::STRING);

        $sm         =  $this->connection->createSchemaManager();
        $comparator = $sm->createComparator();
        $diff       = $comparator->compareTables($sm->introspectTable($this->table), $table);

        self::assertFalse($diff->isEmpty());
        $sm->alterTable($diff);

        $table = $sm->introspectTable($this->table);
        // DBAL4: getColumns() returns numeric array; extract names via array_map
        self::assertSame([strtolower($newColumnName), 'c2'], array_map(static fn ($col) => strtolower($col->getName()), $table->getColumns()));
    }

    /** @return iterable<array{string}> */
    public static function columnNameProvider(): iterable
    {
        yield ['c1', 'c1_x'];
        yield ['C1', 'c1_x'];
        yield ['importantColumn', 'veryImportantColumn'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->table = 'test_rename_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();

        parent::tearDown();
    }
}
