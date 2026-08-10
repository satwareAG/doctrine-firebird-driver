<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

class BooleanTest extends FunctionalTestCase
{
    #[DataProvider('booleanProvider')]
    public function testInsertAndSelect(bool $boolValue, bool $useSmallIntBoolean = true, string $charTrue = 'Y', string $charFalse = 'N'): void
    {
        $this->connection->getDatabasePlatform()->setUseSmallIntBoolean($useSmallIntBoolean);
        if (! $useSmallIntBoolean) {
            $this->connection->getDatabasePlatform()->setCharTrue($charTrue);
            $this->connection->getDatabasePlatform()->setCharFalse($charFalse);
        }

        $table = Table::editor()
            ->setUnquotedName('boolean_table')
            ->setColumns(
                Column::editor()->setUnquotedName('bool')->setTypeName(Types::BOOLEAN)->create(),
            )
            ->create();
        $this->dropAndCreateTable($table);

        $result = $this->connection->insert('boolean_table', ['bool' => $boolValue], [Types::BOOLEAN]);
        self::assertSame(1, $result);

        $value = $this->connection->fetchOne('SELECT bool FROM boolean_table');

        self::assertSame($boolValue, $this->connection->getDatabasePlatform()->convertFromBoolean($value));
    }

    /** @return Iterator<int, array<int, bool|string>> */
    public static function booleanProvider(): Iterator
    {
        yield [true, true];
        yield [false, true];
        yield [true, false];
        yield [false, false];
        yield [true, false, 'J', 'N'];
        yield [true, false, 'U', 'N'];
    }
}
