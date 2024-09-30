<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use DateTime;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function array_filter;
use function strtolower;

class WriteTest extends FunctionalTestCase
{
    public function testExecuteStatementFirstTypeIsNull(): void
    {
        $sql = 'INSERT INTO write_table (test_string, test_int) VALUES (?, ?)';
        $this->connection->executeStatement($sql, ['text', 1111], [null, ParameterType::INTEGER]);

        $sql = 'SELECT * FROM write_table WHERE test_string = ? AND test_int = ?';
        self::assertTrue((bool) $this->connection->fetchFirstColumn($sql, ['text', 1111]));
    }

    public function testExecuteStatement(): void
    {
        $sql      = 'INSERT INTO write_table (test_int) VALUES ( ' . $this->connection->quote(1) . ')';
        $affected = $this->connection->executeStatement($sql);

        self::assertSame(1, $affected, 'executeStatement() should return the number of affected rows!');
    }

    public function testExecuteStatementWithTypes(): void
    {
        $sql      = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $affected = $this->connection->executeStatement(
            $sql,
            [1, 'foo'],
            [ParameterType::INTEGER, ParameterType::STRING],
        );

        self::assertSame(1, $affected, 'executeStatement() should return the number of affected rows!');
    }

    public function testPrepareRowCountReturnsAffectedRows(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, 'foo');

        self::assertSame(1, $stmt->execute()->rowCount());
    }

    public function testPrepareWithPrimitiveTypes(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, 'foo', ParameterType::STRING);

        self::assertSame(1, $stmt->execute()->rowCount());
    }

    public function testPrepareWithDoctrineMappingTypes(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, Type::getType(Types::INTEGER));
        $stmt->bindValue(2, 'foo', Type::getType(Types::STRING));

        self::assertSame(1, $stmt->execute()->rowCount());
    }

    public function testPrepareWithDoctrineMappingTypeNames(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, 'integer');
        $stmt->bindValue(2, 'foo', 'string');

        self::assertSame(1, $stmt->execute()->rowCount());
    }

    public function insertRows(): void
    {
        self::assertSame(1, $this->connection->insert('write_table', ['test_int' => 1, 'test_string' => 'foo']));
        self::assertSame(1, $this->connection->insert('write_table', ['test_int' => 2, 'test_string' => 'bar']));
    }

    public function testInsert(): void
    {
        $this->insertRows();
    }

    public function testDelete(): void
    {
        $this->insertRows();

        self::assertSame(1, $this->connection->delete('write_table', ['test_int' => 2]));
        self::assertCount(1, $this->connection->fetchAllAssociative('SELECT * FROM write_table'));

        self::assertSame(1, $this->connection->delete('write_table', ['test_int' => 1]));
        self::assertCount(0, $this->connection->fetchAllAssociative('SELECT * FROM write_table'));
    }

    public function testUpdate(): void
    {
        $this->insertRows();

        self::assertSame(1, $this->connection->update(
            'write_table',
            ['test_string' => 'bar'],
            ['test_string' => 'foo'],
        ));

        self::assertSame(2, $this->connection->update(
            'write_table',
            ['test_string' => 'baz'],
            ['test_string' => 'bar'],
        ));

        self::assertSame(0, $this->connection->update(
            'write_table',
            ['test_string' => 'baz'],
            ['test_string' => 'bar'],
        ));
    }

    public function testLastInsertId(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsIdentityColumns()) {
            self::markTestSkipped('Test only works on platforms with identity columns.');
        }

        self::assertSame(1, $this->connection->insert('write_table', ['test_int' => 2, 'test_string' => 'bar']));
        $num = $this->connection->lastInsertId();

        self::assertGreaterThan(0, $num, 'LastInsertId() should be non-negative number.');
    }

    public function testLastInsertIdSequence(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences()) {
            self::markTestSkipped('Test only works on platforms with sequences.');
        }

        $sequence = new Sequence('write_table_id_seq');
        try {
            $this->connection->getSchemaManager()->createSequence($sequence);
        } catch (Throwable) {
        }

        $sequences = $this->connection->getSchemaManager()->listSequences();
        self::assertCount(1, array_filter($sequences, static fn ($sequence): bool => strtolower((string) $sequence->getName()) === 'write_table_id_seq'));

        $nextSequenceVal = $this->connection->fetchOne(
            $this->connection->getDatabasePlatform()->getSequenceNextValSQL('write_table_id_seq'),
        );

        $lastInsertId = $this->lastInsertId('write_table_id_seq');

        self::assertGreaterThan(0, $lastInsertId);
        self::assertEquals($nextSequenceVal, $lastInsertId);
    }

    public function testLastInsertIdNoSequenceGiven(): void
    {
        if (
            ! $this->connection->getDatabasePlatform()->supportsSequences()
            || $this->connection->getDatabasePlatform()->supportsIdentityColumns()
        ) {
            self::markTestSkipped(
                "Test only works consistently on platforms that support sequences and don't support identity columns.",
            );
        }

        self::assertFalse($this->lastInsertId());
    }

    public function testInsertWithKeyValueTypes(): void
    {
        $testString = new DateTime('2013-04-14 10:10:10');

        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $testString],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchOne('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertSame($testString->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    public function testUpdateWithKeyValueTypes(): void
    {
        $testString = new DateTime('2013-04-14 10:10:10');

        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $testString],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $testString = new DateTime('2013-04-15 10:10:10');

        $this->connection->update(
            'write_table',
            ['test_string' => $testString],
            ['test_int' => '30'],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchOne('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertSame($testString->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    public function testDeleteWithKeyValueTypes(): void
    {
        $val = new DateTime('2013-04-14 10:10:10');
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $val],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $this->connection->delete('write_table', [
            'test_int' => 30,
            'test_string' => $val,
        ], [
            'test_string' => 'datetime',
            'test_int' => 'integer',
        ]);

        $data = $this->connection->fetchOne('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertFalse($data);
    }

    public function testEmptyIdentityInsert(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsIdentityColumns() && ! $platform->usesSequenceEmulatedIdentityColumns()) {
            self::markTestSkipped(
                'Test only works on platforms with identity columns or sequence emulated identity columns.',
            );
        }

        $table = new Table('test_empty_identity');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        try {
            $this->connection->getSchemaManager()->dropTable($table->getQuotedName($platform));
        } catch (Throwable) {
        }

        foreach ($platform->getCreateTableSQL($table) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $seqName = $platform->usesSequenceEmulatedIdentityColumns()
            ? $platform->getIdentitySequenceName('test_empty_identity', 'id')
            : null;

        $sql = $platform->getEmptyIdentityInsertSQL('test_empty_identity', 'id');

        $this->connection->executeStatement($sql);

        $firstId = $this->lastInsertId($seqName);

        $this->connection->executeStatement($sql);

        $secondId = $this->lastInsertId($seqName);

        self::assertGreaterThan($firstId, $secondId);
    }

    public function testUpdateWhereIsNull(): void
    {
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->connection->update('write_table', ['test_int' => 10], ['test_string' => null], [
            'test_string' => 'string',
            'test_int' => 'integer',
        ]);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    public function testDeleteWhereIsNull(): void
    {
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->connection->delete('write_table', ['test_string' => null], ['test_string' => 'string']);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    protected function setUp(): void
    {
        $table = new Table('write_table');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('test_int', Types::INTEGER);
        $table->addColumn('test_string', Types::STRING, ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('DELETE FROM write_table');
    }

    /**
     * Returns the ID of the last inserted row or skips the test if the currently used driver
     * doesn't support this feature
     *
     * @throws Exception
     */
    private function lastInsertId(string|null $name = null): string|int|false
    {
        try {
            return $this->connection->lastInsertId($name);
        } catch (Exception $e) {
            if ($e->getSQLState() === 'IM001') {
                self::markTestSkipped($e->getMessage());
            }

            throw $e;
        }
    }
}
