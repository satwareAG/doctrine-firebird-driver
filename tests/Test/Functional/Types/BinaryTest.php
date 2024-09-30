<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function is_resource;
use function random_bytes;
use function str_replace;
use function stream_get_contents;

class BinaryTest extends FunctionalTestCase
{
    public function testInsertAndSelect(): void
    {
        $id1 = random_bytes(16);
        $id2 = random_bytes(16);

        $value1 = random_bytes(64);
        $value2 = random_bytes(64);

        /** @see https://bugs.php.net/bug.php?id=76322 */
        if (false) {
            $value1 = str_replace("\x00", "\xFF", $value1);
            $value2 = str_replace("\x00", "\xFF", $value2);
        }

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        self::assertSame($value1, $this->select($id1));
        self::assertSame($value2, $this->select($id2));
    }

    protected function setUp(): void
    {
        $table = new Table('binary_table');
        $table->addColumn('id', Types::BINARY, [
            'length' => 16,
            'fixed' => true,
        ]);
        $table->addColumn('val', Types::BINARY, ['length' => 64]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    private function insert(string $id, string $value): void
    {
        $result = $this->connection->insert('binary_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::BINARY,
            ParameterType::BINARY,
        ]);

        self::assertSame(1, $result);
    }

    private function select(string $id): mixed
    {
        $value = $this->connection->fetchOne(
            'SELECT val FROM binary_table WHERE id = ?',
            [$id],
            [ParameterType::BINARY],
        );

        // Currently, `BinaryType` mistakenly converts string values fetched from the DB to a stream.
        // It should be the opposite. Streams should be used to represent large objects, not binary
        // strings. The confusion comes from the PostgreSQL's type system where binary strings and
        // large objects are represented by the same BYTEA type
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return $value;
    }
}
