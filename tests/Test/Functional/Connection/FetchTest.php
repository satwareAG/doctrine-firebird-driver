<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Connection;

use Doctrine\DBAL\Exception\NoKeyValue;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

use function iterator_to_array;

class FetchTest extends FunctionalTestCase
{
    private string $query;

    public function setUp(): void
    {
        $this->query = TestUtil::generateResultSetQuery([
            [
                'a' => 'foo',
                'b' => 1,
            ],
            [
                'a' => 'bar',
                'b' => 2,
            ],
            [
                'a' => 'baz',
                'b' => 3,
            ],
        ], $this->connection->getDatabasePlatform());
    }

    public function testFetchNumeric(): void
    {
        self::assertSame(['foo', 1], $this->connection->fetchNumeric($this->query));
    }

    public function testFetchOne(): void
    {
        self::assertSame('foo', $this->connection->fetchOne($this->query));
    }

    public function testFetchAssociative(): void
    {
        self::assertSame([
            'a' => 'foo',
            'b' => 1,
        ], $this->connection->fetchAssociative($this->query));
    }

    public function testFetchAllNumeric(): void
    {
        self::assertSame([
            ['foo', 1],
            ['bar', 2],
            ['baz', 3],
        ], $this->connection->fetchAllNumeric($this->query));
    }

    public function testFetchAllAssociative(): void
    {
        self::assertSame([
            [
                'a' => 'foo',
                'b' => 1,
            ],
            [
                'a' => 'bar',
                'b' => 2,
            ],
            [
                'a' => 'baz',
                'b' => 3,
            ],
        ], $this->connection->fetchAllAssociative($this->query));
    }

    public function testFetchAllKeyValue(): void
    {
        self::assertSame([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ], $this->connection->fetchAllKeyValue($this->query));
    }

    /**
     * This test covers the requirement for the statement result to have at least two columns,
     * not exactly two as PDO requires.
     */
    public function testFetchAllKeyValueWithLimit(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLServerPlatform) {
            self::markTestSkipped('See https://github.com/doctrine/dbal/issues/2374');
        }

        $query = $platform->modifyLimitQuery($this->query, 1, 1);

        self::assertSame(['bar' => 2], $this->connection->fetchAllKeyValue($query));
    }

    public function testFetchAllKeyValueOneColumn(): void
    {
        $sql = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL();

        $this->expectException(NoKeyValue::class);
        $this->connection->fetchAllKeyValue($sql);
    }

    public function testFetchAllAssociativeIndexed(): void
    {
        self::assertSame([
            'foo' => ['b' => 1],
            'bar' => ['b' => 2],
            'baz' => ['b' => 3],
        ], $this->connection->fetchAllAssociativeIndexed($this->query));
    }

    public function testFetchFirstColumn(): void
    {
        self::assertSame([
            'foo',
            'bar',
            'baz',
        ], $this->connection->fetchFirstColumn($this->query));
    }

    public function testIterateNumeric(): void
    {
        self::assertSame([
            ['foo', 1],
            ['bar', 2],
            ['baz', 3],
        ], iterator_to_array($this->connection->iterateNumeric($this->query)));
    }

    public function testIterateAssociative(): void
    {
        self::assertSame([
            [
                'a' => 'foo',
                'b' => 1,
            ],
            [
                'a' => 'bar',
                'b' => 2,
            ],
            [
                'a' => 'baz',
                'b' => 3,
            ],
        ], iterator_to_array($this->connection->iterateAssociative($this->query)));
    }

    public function testIterateKeyValue(): void
    {
        self::assertSame([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ], iterator_to_array($this->connection->iterateKeyValue($this->query)));
    }

    public function testIterateKeyValueOneColumn(): void
    {
        $sql = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL();

        $this->expectException(NoKeyValue::class);
        iterator_to_array($this->connection->iterateKeyValue($sql));
    }

    public function testIterateAssociativeIndexed(): void
    {
        self::assertSame([
            'foo' => ['b' => 1],
            'bar' => ['b' => 2],
            'baz' => ['b' => 3],
        ], iterator_to_array($this->connection->iterateAssociativeIndexed($this->query)));
    }

    public function testIterateColumn(): void
    {
        self::assertSame([
            'foo',
            'bar',
            'baz',
        ], iterator_to_array($this->connection->iterateColumn($this->query)));
    }
}
