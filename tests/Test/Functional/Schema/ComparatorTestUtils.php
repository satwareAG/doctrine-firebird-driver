<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use PHPUnit\Framework\TestCase;

final class ComparatorTestUtils
{
    /** @throws Exception */
    public static function diffFromActualToDesiredTable(
        AbstractSchemaManager $schemaManager,
        Comparator $comparator,
        Table $desiredTable,
    ): TableDiff|false {
        return $comparator->diffTable(
            $schemaManager->introspectTable($desiredTable->getName()),
            $desiredTable,
        );
    }

    /** @throws Exception */
    public static function diffFromDesiredToActualTable(
        AbstractSchemaManager $schemaManager,
        Comparator $comparator,
        Table $desiredTable,
    ): TableDiff|false {
        return $comparator->diffTable(
            $desiredTable,
            $schemaManager->introspectTable($desiredTable->getName()),
        );
    }

    public static function assertDiffNotEmpty(Connection $connection, Comparator $comparator, Table $table): void
    {
        $schemaManager = $connection->createSchemaManager();

        $diff = self::diffFromActualToDesiredTable($schemaManager, $comparator, $table);

        TestCase::assertNotFalse($diff);

        $schemaManager->alterTable($diff);

        TestCase::assertFalse(self::diffFromActualToDesiredTable($schemaManager, $comparator, $table));
        TestCase::assertFalse(self::diffFromDesiredToActualTable($schemaManager, $comparator, $table));
    }

    /** @return iterable<string,array<callable(AbstractSchemaManager):Comparator>> */
    public static function comparatorProvider(): iterable
    {
        yield 'Generic comparator' => [
            static fn (): Comparator => new Comparator(),
        ];

        yield 'Platform-specific comparator' => [
            static fn (AbstractSchemaManager $schemaManager): Comparator => $schemaManager->createComparator(),
        ];
    }
}
