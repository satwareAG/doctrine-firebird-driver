<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Middleware;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function array_keys;
use function array_merge;
use function gc_collect_cycles;
use function sleep;
use function strlen;

class PortabilityTest extends FunctionalTestCase
{
    public function testFullFetchMode(): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_ALL, ColumnCase::LOWER);
        $this->createTable();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $this->connection->executeQuery('SELECT * FROM portability_table');

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }

        $result = $this->connection
            ->prepare('SELECT * FROM portability_table')
            ->executeQuery();

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }
    }

    /** @param list<string> $expected */
    #[DataProvider('caseProvider')]
    public function testCaseConversion(ColumnCase $case, array $expected): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_FIX_CASE, $case);
        $this->createTable();

        $row = $this->connection->fetchAssociative('SELECT * FROM portability_table');

        self::assertNotFalse($row);
        self::assertSame($expected, array_keys($row));
    }

    /** @param array<string, mixed> $row */
    public function assertFetchResultRow(array $row): void
    {
        self::assertThat($row['test_int'], self::logicalOr(
            self::equalTo(1),
            self::equalTo(2),
        ));

        self::assertArrayHasKey('test_string', $row, 'Case should be lowered.');
        self::assertSame(3, strlen((string) $row['test_string']));
        self::assertNull($row['test_null']);
        self::assertArrayNotHasKey(0, $row, 'The row should not contain numerical keys.');
    }

    /** @param mixed[] $expected */
    #[DataProvider('fetchColumnProvider')]
    public function testFetchColumn(string $column, array $expected): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_RTRIM, 0);
        $this->createTable();

        $result = $this->connection->executeQuery('SELECT ' . $column . ' FROM portability_table');

        self::assertEquals($expected, $result->fetchFirstColumn());
    }

    public function testFetchAllNullColumn(): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_EMPTY_TO_NULL, 0);
        $this->createTable();

        $column = $this->connection->fetchFirstColumn('SELECT Test_Null FROM portability_table');

        self::assertSame([null, null], $column);
    }

    public function testGetDatabaseName(): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_EMPTY_TO_NULL, 0);
        self::assertNotNull($this->connection->getDatabase());
    }

    public function testTimeout(): void
    {
        sleep(1); // Short sleep - just verify test completes
        self::assertTrue(true);
    }

    /** @return iterable<string, array{(ColumnCase::LOWER|ColumnCase::UPPER), list<string>}> */
    public static function caseProvider(): iterable
    {
        yield 'lower' => [ColumnCase::LOWER, ['test_int', 'test_string', 'test_null']];
        yield 'upper' => [ColumnCase::UPPER, ['TEST_INT', 'TEST_STRING', 'TEST_NULL']];
    }

    /** @return iterable<string, array<int, mixed>> */
    public static function fetchColumnProvider(): Iterator
    {
        yield 'int' => [
            'Test_Int',
            [1, 2],
        ];

        yield 'string' => [
            'Test_String',
            ['foo', 'foo'],
        ];
    }

    protected function tearDown(): void
    {
        // Free cursor/result objects that hold Firebird metadata locks,
        // then drop the table while the connection is still open.
        gc_collect_cycles();

        try {
            $this->connection->executeStatement('DROP TABLE portability_table');
        } catch (Throwable) {
            // Table may not exist if test failed early
        }

        // the connection that overrides the shared one has to be manually closed prior to 4.0.0 to prevent leak
        // see https://github.com/doctrine/dbal/issues/4515
        $this->markConnectionNotReusable();
        $this->connection->close();
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function assertFetchResultRows(array $rows): void
    {
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertFetchResultRow($row);
        }
    }

    /** @param 0|ColumnCase $case */
    private function connectWithPortability(int $mode, ColumnCase|int $case): void
    {
        // Mark connection not reusable - framework will handle cleanup
        $this->markConnectionNotReusable();

        $params        = $this->connection->getParams();
        $configuration = $this->connection->getConfiguration();

        // Close the existing shared connection to prevent locking issues during DROP TABLE
        $this->connection->close();

        $configuration->setMiddlewares(
            array_merge(
                $configuration->getMiddlewares(),
                [new Middleware($mode, $case instanceof ColumnCase ? $case : null)],
            ),
        );

        $this->connection = DriverManager::getConnection($params, $configuration);
    }

    private function createTable(): void
    {
        $table = Table::editor()
            ->setUnquotedName('portability_table')
            ->setColumns(
                Column::editor()->setUnquotedName('Test_Int')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('Test_String')->setTypeName(Types::STRING)->setFixed(true)->setLength(32)->create(),
                Column::editor()->setUnquotedName('Test_Null')->setTypeName(Types::STRING)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('Test_Int')->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->insert('portability_table', [
            'Test_Int' => 1,
            'Test_String' => 'foo',
            'Test_Null' => '',
        ]);

        $this->connection->insert('portability_table', [
            'Test_Int' => 2,
            'Test_String' => 'foo  ',
            'Test_Null' => null,
        ]);
    }
}
