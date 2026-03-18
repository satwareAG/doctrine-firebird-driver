<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use LogicException;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_change_key_case;
use function array_map;
use function uniqid;

use const CASE_LOWER;

class LegacyAPITest extends FunctionalTestCase
{
    use VerifyDeprecations;

    private string $table = 'legacy_table';

    public function testFetchWithAssociativeMode(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $row = array_change_key_case($stmt->fetch(FetchMode::ASSOCIATIVE), CASE_LOWER);
        self::assertSame(1, $row['test_int']);
    }

    public function testFetchWithNumericMode(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $row = $stmt->fetch(FetchMode::NUMERIC);
        self::assertSame(1, $row[0]);
    }

    public function testFetchWithColumnMode(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $row = $stmt->fetch(FetchMode::COLUMN);
        self::assertSame(1, $row);
    }

    public function testFetchWithTooManyArguments(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetch(FetchMode::COLUMN, 2);
    }

    public function testFetchWithUnsupportedFetchMode(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetch(1);
    }

    public function testFetchAllWithAssociativeModes(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::ASSOCIATIVE);
        $rows = array_map(static fn ($row) => array_change_key_case($row, CASE_LOWER), $rows);

        self::assertSame([['test_int' => 1]], $rows);
    }

    public function testFetchAllWithNumericModes(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::NUMERIC);
        self::assertSame([[0 => 1]], $rows);
    }

    public function testFetchAllWithColumnMode(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertSame([1], $rows);
    }

    public function testFetchAllWithTooManyArguments(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetchAll(FetchMode::COLUMN, 2);
    }

    public function testFetchAllWithUnsupportedFetchMode(): void
    {
        $sql = 'SELECT test_int FROM ' . $this->table . ' WHERE test_int = 1';

        $stmt = $this->connection->executeQuery($sql);

        $this->expectException(LogicException::class);

        $stmt->fetchAll(1);
    }

    public function testExecuteUpdate(): void
    {
        $this->connection->executeUpdate(
            'INSERT INTO ' . $this->table . ' (test_int, test_string) VALUES (?, ?)',
            [2, 'bar'],
            ['integer', 'string'],
        );

        $sql = 'SELECT test_string FROM ' . $this->table;

        $stmt = $this->connection->executeQuery($sql);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4007');

        $rows = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertSame(['foo', 'bar'], $rows);
    }

    public function testQuery(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4163');

        $stmt = $this->connection->query('SELECT test_string FROM ' . $this->table . ' WHERE test_int = 1');

        self::assertSame('foo', $stmt->fetchOne());
    }

    public function testExec(): void
    {
        $this->connection->insert($this->table, [
            'test_int' => 2,
            'test_string' => 'bar',
        ]);

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/4163');

        $count = $this->connection->exec('DELETE FROM ' . $this->table . ' WHERE test_int > 1');

        self::assertSame(1, $count);
    }

    protected function setUp(): void
    {
        $this->table = 'legacy_' . uniqid();
        $table       = new Table($this->table);
        $table->addColumn('test_int', Types::INTEGER);
        $table->addColumn('test_string', Types::STRING);
        $table->setPrimaryKey(['test_int']);

        $this->dropAndCreateTable($table);

        $this->connection->insert($this->table, [
            'test_int' => 1,
            'test_string' => 'foo',
        ]);
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }
}
