<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function rtrim;

final class DecimalTest extends FunctionalTestCase
{
    #[DataProvider('dataValuesProvider')]
    public function testInsertAndRetrieveDecimal(string $expected): void
    {
        $table = Table::editor()
            ->setUnquotedName('decimal_table')
            ->setColumns(
                Column::editor()->setUnquotedName('val')->setTypeName(Types::DECIMAL)->setPrecision(4)->setScale(2)->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->insert(
            'decimal_table',
            ['val' => $expected],
            ['val' => Types::DECIMAL],
        );

        $value = Type::getType(Types::DECIMAL)->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM decimal_table'),
            $this->connection->getDatabasePlatform(),
        );

        self::assertIsString($value);
        self::assertSame($this->stripTrailingZero($expected), $this->stripTrailingZero($value));
    }

    /** @return string[][] */
    public static function dataValuesProvider(): Iterator
    {
        yield ['13.37'];
        yield ['13.0'];
    }

    private function stripTrailingZero(string $expected): string
    {
        return rtrim(rtrim($expected, '0'), '.');
    }
}
