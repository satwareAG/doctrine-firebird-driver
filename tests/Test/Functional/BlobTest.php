<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function fopen;
use function str_repeat;
use function stream_get_contents;

class BlobTest extends FunctionalTestCase
{
    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testInsert(): void
    {
        $ret = $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'test',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        self::assertSame(1, $ret);
    }

    public function testInsertNull(): void
    {
        $ret = $this->connection->insert('blob_table', [
            'id'         => 1,
            'clobcolumn' => null,
            'blobcolumn' => null,
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        self::assertSame(1, $ret);

        [$clobValue, $blobValue] = $this->fetchRow();
        self::assertNull($clobValue);
        self::assertNull($blobValue);
    }

    public function testInsertProcessesStream(): void
    {
        $longBlob = str_repeat('x', 4 * 8192); // send 4 chunks
        $this->connection->insert('blob_table', [
            'id'        => 1,
            'clobcolumn' => 'ignored',
            'blobcolumn' => fopen('data://text/plain,' . $longBlob, 'r'),
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains($longBlob);
    }

    public function testSelect(): void
    {
        $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'test',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test');
    }

    public function testUpdate(): void
    {
        $this->connection->insert('blob_table', [
            'id' => 1,
            'clobcolumn' => 'test',
            'blobcolumn' => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', ['blobcolumn' => 'test2'], ['id' => 1], [
            ParameterType::LARGE_OBJECT,
            ParameterType::INTEGER,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testUpdateProcessesStream(): void
    {
        // https://github.com/doctrine/dbal/issues/3290

        $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'ignored',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', [
            'id'          => 1,
            'blobcolumn'   => fopen('data://text/plain,test2', 'r'),
        ], ['id' => 1], [
            ParameterType::INTEGER,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testBindParamProcessesStream(): void
    {
        // DBAL4: bindParam() removed; use bindValue() with stream
        $stream = fopen('data://text/plain,test', 'r');

        $stmt = $this->connection->prepare(
            "INSERT INTO blob_table(id, clobcolumn, blobcolumn) VALUES (1, 'ignored', ?)",
        );

        $stmt->bindValue(1, $stream, ParameterType::LARGE_OBJECT);

        $stmt->executeQuery();

        $this->assertBlobContains('test');
    }

    public function testBlobBindingDoesNotOverwritePrevious(): void
    {
        // Use a separate table name to avoid DDL on the shared blob_table,
        // which would trigger dropTableForce() and corrupt OO API pointers.
        $table = Table::editor()
            ->setUnquotedName('blob_table_multi')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName('integer')->create(),
                Column::editor()->setUnquotedName('blobcolumn1')->setTypeName('blob')->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('blobcolumn2')->setTypeName('blob')->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $tableReady = false;
        try {
            $this->connection->executeStatement('DELETE FROM blob_table_multi');
            $tableReady = true;
        } catch (Throwable) {
        }

        if (! $tableReady) {
            $this->dropAndCreateTable($table);
        }

        $this->createdTables = [];

        $params = ['test1', 'test2'];
        $this->connection->executeStatement(
            'INSERT INTO blob_table_multi(id, blobcolumn1, blobcolumn2) VALUES (1, ?, ?)',
            $params,
            [ParameterType::LARGE_OBJECT, ParameterType::LARGE_OBJECT],
        );

        $blobs = $this->connection->fetchNumeric('SELECT blobcolumn1, blobcolumn2 FROM blob_table_multi');
        self::assertIsArray($blobs);

        $actual = [];
        foreach ($blobs as $blob) {
            $blob     = Type::getType('blob')->convertToPHPValue($blob, $this->connection->getDatabasePlatform());
            $actual[] = stream_get_contents($blob);
        }

        self::assertSame(['test1', 'test2'], $actual);
    }

    protected function setUp(): void
    {
        // Avoid DROP+CREATE on every test to prevent Firebird metadata lock
        // corruption. See BinaryDataAccessTest for full rationale.
        $tableReady = false;
        try {
            $this->connection->executeStatement('DELETE FROM blob_table');
            $tableReady = true;
        } catch (Throwable) {
            // Table doesn't exist or has wrong schema - create it
        }

        if (! $tableReady) {
            $table = Table::editor()
                ->setUnquotedName('blob_table')
                ->setColumns(
                    Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                    Column::editor()->setUnquotedName('clobcolumn')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                    Column::editor()->setUnquotedName('blobcolumn')->setTypeName(Types::BLOB)->setNotNull(false)->create(),
                )
                ->setPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
                )
                ->create();

            $this->dropAndCreateTable($table);
        }

        // Prevent disconnect() from dropping the table between tests.
        $this->createdTables = [];
    }

    private function assertBlobContains(string $text): void
    {
        [, $blobValue] = $this->fetchRow();

        $blobValue = Type::getType(Types::BLOB)->convertToPHPValue(
            $blobValue,
            $this->connection->getDatabasePlatform(),
        );

        self::assertIsResource($blobValue);
        self::assertSame($text, stream_get_contents($blobValue));
    }

    /** @return list<mixed> */
    private function fetchRow(): array
    {
        $rows = $this->connection->fetchAllNumeric('SELECT clobcolumn, blobcolumn FROM blob_table');

        self::assertCount(1, $rows);

        return $rows[0];
    }
}
