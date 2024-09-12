<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Test\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Kafoso\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;

class BooleanTest extends FunctionalTestCase
{
    public function booleanProvider(): array
    {
        return [
            [true, true],
            [false, true],
            [true, false],
            [false, false],
            [true, false, 'J', 'N'],
            [true, false, 'U', 'N'],
        ];
    }
    /**
     * @dataProvider booleanProvider
     */
    public function testInsertAndSelect(bool $boolValue, bool $useSmallIntBoolean = true, string $charTrue = 'Y', string $charFalse = 'N'): void
    {
        $this->connection->getDatabasePlatform()->setUseSmallIntBoolean($useSmallIntBoolean);
        if (!$useSmallIntBoolean) {
            $this->connection->getDatabasePlatform()->setCharTrue($charTrue);
            $this->connection->getDatabasePlatform()->setCharFalse($charFalse);
        }
        $table = new Table('boolean_table');
        $table->addColumn('bool', Types::BOOLEAN);
        $this->dropAndCreateTable($table);

        $result = $this->connection->insert('boolean_table', ['bool' => $boolValue], [Types::BOOLEAN]);
        self::assertSame(1, $result);

        $value = $this->connection->fetchOne('SELECT bool FROM boolean_table');

        self::assertSame($boolValue, $this->connection->getDatabasePlatform()->convertFromBoolean($value));
    }


}
