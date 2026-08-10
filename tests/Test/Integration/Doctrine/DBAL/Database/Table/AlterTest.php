<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

use function md5;
use function strtoupper;
use function substr;

class AlterTest extends AbstractIntegrationTestCase
{
    public function setUp(): void
    {
        // no Database needed here.
        $this->_platform = $this->connection->getDatabasePlatform();
    }

    public function testAlterTable(): void
    {
        self::assertTrue(true);
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $this->dropTableIfExists($tableName);
        $sql        = "CREATE TABLE {$tableName} (foo INTEGER DEFAULT 0 NOT NULL)";
        $connection->executeStatement($sql);

        $oldTable = Table::editor()
            ->setUnquotedName($tableName)
            ->setColumns(
                Column::editor()->setUnquotedName('foo')->setTypeName(Types::INTEGER)->setDefaultValue(0)->setNotNull(true)->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $oldTable,
            changedColumns: [
                new ColumnDiff(
                    Column::editor()->setUnquotedName('foo')->setTypeName(Types::INTEGER)->setDefaultValue(0)->setNotNull(true)->create(),
                    Column::editor()->setUnquotedName('foo')->setTypeName('string')->create(),
                ),
            ],
        );
        $statements = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertGreaterThanOrEqual(1, count($statements));
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
