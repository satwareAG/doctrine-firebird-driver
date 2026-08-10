<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function sprintf;
use function uniqid;

class DateExpressionTest extends FunctionalTestCase
{
    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    #[DataProvider('differenceProvider')]
    public function testDifference(string $date1, string $date2, int $expected): void
    {
        $tableName = 'date_expr_test' . uniqid();
        $table     = Table::editor()
            ->setUnquotedName($tableName)
            ->setColumns(
                Column::editor()->setUnquotedName('date1')->setTypeName(Types::DATETIME_MUTABLE)->create(),
                Column::editor()->setUnquotedName('date2')->setTypeName(Types::DATETIME_MUTABLE)->create(),
            )
            ->create();
        $this->dropAndCreateTable($table);
        $this->connection->insert($tableName, [
            'date1' => $date1,
            'date2' => $date2,
        ]);

        $platform = $this->connection->getDatabasePlatform();

        $sql  = sprintf('SELECT %s FROM %s', $platform->getDateDiffExpression('date1', 'date2'), $tableName);
        $diff = $this->connection->fetchOne($sql);

        self::assertSame($expected, $diff);
    }

    /** @return array<string, array{string, string, int}> */
    public static function differenceProvider(): Iterator
    {
        yield 'same day' => ['2018-04-14 23:59:59', '2018-04-14 00:00:00', 0];
        yield 'midnight' => ['2018-04-14 00:00:00', '2018-04-13 23:59:59', 1];
    }
}
