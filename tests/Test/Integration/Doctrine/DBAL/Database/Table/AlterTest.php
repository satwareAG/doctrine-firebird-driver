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
        $sql        = "CREATE TABLE {$tableName} (foo INTEGER DEFAULT 0 NOT NULL)";
        $connection->executeStatement($sql);

        $oldTable = new Table($tableName);
        $oldTable->addColumn('foo', Types::INTEGER, ['default' => 0, 'notnull' => true]);
        $tableDiff = new TableDiff(
            $oldTable,
            changedColumns: [
                new ColumnDiff(
                    new Column('foo', Type::getType(Types::INTEGER), ['default' => 0, 'notnull' => true]),
                    new Column('foo', Type::getType('string')),
                ),
            ],
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
