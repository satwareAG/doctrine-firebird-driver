<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

class AsciiStringTest extends FunctionalTestCase
{
    public function testInsertAndSelect(): void
    {
        $id1 = 'id1';
        $id2 = 'id2';

        $value1 = 'val1';
        $value2 = 'val2';

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        self::assertSame($value1, $this->select($id1));
        self::assertSame($value2, $this->select($id2));
    }

    protected function setUp(): void
    {
        $table = new Table('ascii_table');
        $table->addColumn('id', Types::ASCII_STRING, [
            'length' => 3,
            'fixed' => true,
        ]);

        $table->addColumn('val', Types::ASCII_STRING, ['length' => 4]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    private function insert(string $id, string $value): void
    {
        $result = $this->connection->insert('ascii_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::ASCII,
            ParameterType::ASCII,
        ]);

        self::assertSame(1, $result);
    }

    private function select(string $id): string
    {
        $value = $this->connection->fetchOne(
            'SELECT val FROM ascii_table WHERE id = ?',
            [$id],
            [ParameterType::ASCII],
        );

        self::assertIsString($value);

        return $value;
    }
}
