<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_change_key_case;
use function count;

use const CASE_LOWER;

class ModifyLimitQueryTest extends FunctionalTestCase
{
    private string $table = 'modify_limit_table';
    private string $table2 = 'modify_limit_table2';

    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testModifyLimitQuerySimpleQuery(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);
        $this->connection->insert($this->table, ['test_int' => 3]);
        $this->connection->insert($this->table, ['test_int' => 4]);

        $sql = 'SELECT * FROM ' . $this->table . ' ORDER BY test_int ASC';

        $this->assertLimitResult([1, 2, 3, 4], $sql, 10, 0);
        $this->assertLimitResult([1, 2], $sql, 2, 0);
        $this->assertLimitResult([3, 4], $sql, 2, 2);
        $this->assertLimitResult([2, 3, 4], $sql, null, 1);
    }

    public function testModifyLimitQueryJoinQuery(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);

        $this->connection->insert($this->table2, ['test_int' => 1]);
        $this->connection->insert($this->table2, ['test_int' => 1]);
        $this->connection->insert($this->table2, ['test_int' => 1]);
        $this->connection->insert($this->table2, ['test_int' => 2]);
        $this->connection->insert($this->table2, ['test_int' => 2]);

        $sql = 'SELECT ' . $this->table . '.test_int FROM ' . $this->table . ' INNER JOIN ' . $this->table2
            . ' ON ' . $this->table . '.test_int = ' . $this->table2 . '.test_int'
            . ' ORDER BY ' . $this->table . '.test_int DESC';

        $this->assertLimitResult([2, 2, 1, 1, 1], $sql, 10, 0);
        $this->assertLimitResult([1, 1, 1], $sql, 3, 2);
        $this->assertLimitResult([2, 2], $sql, 2, 0);
    }

    public function testModifyLimitQueryNonDeterministic(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);
        $this->connection->insert($this->table, ['test_int' => 3]);
        $this->connection->insert($this->table, ['test_int' => 4]);

        $sql = 'SELECT * FROM ' . $this->table;

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0, false);
        $this->assertLimitResult([4, 3], $sql, 2, 0, false);
        $this->assertLimitResult([2, 1], $sql, 2, 2, false);
    }

    public function testModifyLimitQueryGroupBy(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);

        $this->connection->insert($this->table2, ['test_int' => 1]);
        $this->connection->insert($this->table2, ['test_int' => 1]);
        $this->connection->insert($this->table2, ['test_int' => 1]);
        $this->connection->insert($this->table2, ['test_int' => 2]);
        $this->connection->insert($this->table2, ['test_int' => 2]);

        $sql = 'SELECT ' . $this->table . '.test_int FROM ' . $this->table . ' ' .
               'INNER JOIN ' . $this->table2 . ' ON ' . $this->table . '.test_int = ' . $this->table2 . '.test_int ' .
               'GROUP BY ' . $this->table . '.test_int ' .
               'ORDER BY ' . $this->table . '.test_int ASC';
        $this->assertLimitResult([1, 2], $sql, 10, 0);
        $this->assertLimitResult([1], $sql, 1, 0);
        $this->assertLimitResult([2], $sql, 1, 1);
    }

    public function testModifyLimitQuerySubSelect(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);
        $this->connection->insert($this->table, ['test_int' => 3]);
        $this->connection->insert($this->table, ['test_int' => 4]);

        $sql = 'SELECT ' . $this->table . '.*, (SELECT COUNT(*) FROM ' . $this->table . ') AS cnt'
            . ' FROM ' . $this->table . ' ORDER BY test_int DESC';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0);
        $this->assertLimitResult([4, 3], $sql, 2, 0);
        $this->assertLimitResult([2, 1], $sql, 2, 2);
    }

    public function testModifyLimitQueryFromSubSelect(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);
        $this->connection->insert($this->table, ['test_int' => 3]);
        $this->connection->insert($this->table, ['test_int' => 4]);

        $sql = 'SELECT * FROM (SELECT * FROM ' . $this->table . ') sub ORDER BY test_int DESC';

        $this->assertLimitResult([4, 3, 2, 1], $sql, 10, 0);
        $this->assertLimitResult([4, 3], $sql, 2, 0);
        $this->assertLimitResult([2, 1], $sql, 2, 2);
    }

    public function testModifyLimitQueryLineBreaks(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);
        $this->connection->insert($this->table, ['test_int' => 3]);

        $sql = <<<SQL
SELECT
*
FROM
{$this->table}
ORDER
BY
test_int
ASC
SQL;

        $this->assertLimitResult([2], $sql, 1, 1);
    }

    public function testModifyLimitQueryZeroOffsetNoLimit(): void
    {
        $this->connection->insert($this->table, ['test_int' => 1]);
        $this->connection->insert($this->table, ['test_int' => 2]);

        $sql = 'SELECT test_int FROM ' . $this->table . ' ORDER BY test_int ASC';

        $this->assertLimitResult([1, 2], $sql, null, 0);
    }

    public function testLimitWhenOrderByWithSubqueryWithOrderBy(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof DB2Platform) {
            self::markTestSkipped('DB2 cannot handle ORDER BY in subquery');
        }

        if ($platform instanceof OraclePlatform) {
            $this->markTestSkipped('Oracle cannot handle ORDER BY in subquery');
        }

        $this->connection->insert($this->table2, ['test_int' => 3]);
        $this->connection->insert($this->table2, ['test_int' => 1]);
        $this->connection->insert($this->table2, ['test_int' => 2]);

        $subquery = 'SELECT test_int FROM ' . $this->table2 . ' T2 WHERE T1.id=T2.id ORDER BY test_int';

        if ($platform instanceof SQLServerPlatform) {
            $subquery .= ' OFFSET 0 ROWS';
        }

        $sql = 'SELECT test_int FROM ' . $this->table2 . ' T1 ORDER BY (' . $subquery . ') ASC';

        $this->assertLimitResult([1, 2, 3], $sql, 10, 0);
    }

    protected function setUp(): void
    {
        $this->table = 'modify_limit_' . uniqid();
        $this->table2 = 'modify_limit2_' . uniqid();

        $table = new Table($this->table);
        $table->addColumn('test_int', Types::INTEGER);
        $table->setPrimaryKey(['test_int']);

        $table2 = new Table($this->table2);
        $table2->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table2->addColumn('test_int', Types::INTEGER);
        $table2->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
        $this->dropAndCreateTable($table2);
    }

    /** @param array<int, int> $expectedResults */
    private function assertLimitResult(
        array $expectedResults,
        string $sql,
        int|null $limit,
        int $offset,
        bool $deterministic = true,
    ): void {
        $p    = $this->connection->getDatabasePlatform();
        $data = [];
        foreach ($this->connection->fetchAllAssociative($p->modifyLimitQuery($sql, $limit, $offset)) as $row) {
            $row    = array_change_key_case($row, CASE_LOWER);
            $data[] = $row['test_int'];
        }

        /**
         * Do not assert the order of results when results are non-deterministic
         */
        if ($deterministic) {
            self::assertEquals($expectedResults, $data);
        } else {
            self::assertCount(count($expectedResults), $data);
        }
    }
}
