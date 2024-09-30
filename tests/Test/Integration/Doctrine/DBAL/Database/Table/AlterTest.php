<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

use function md5;
use function strtoupper;
use function substr;

class AlterTest extends AbstractIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testAlterTable(): void
    {
        self::assertTrue(true);
        $connection = $this->_entityManager->getConnection();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $sql        = "CREATE TABLE {$tableName} (foo INTEGER DEFAULT 0 NOT NULL)";
        $connection->executeStatement($sql);

        $tableDiff                        = new TableDiff($tableName);
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'foo',
            new Column(
                'bar',
                Type::getType('string'),
            ),
            ['type'],
        );
        $statements                       = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertCount(1, $statements);
        foreach ($statements as $statement) {
            $connection->executeStatement($statement);
        }

        $sql    = "SELECT 1
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'
            AND RF.RDB\$FIELD_NAME = 'FOO'
            AND F.RDB\$FIELD_TYPE = " . FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR;
        $result = $connection->executeQuery($sql);
        self::assertInstanceOf(Result::class, $result);
        self::assertSame(1, $result->fetchOne(), 'Column change failed. SQL: ' . self::statementArrayToText($statements));
    }
}
