<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement;
use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Kafoso\DoctrineFirebirdDriver\Schema\FirebirdInterbaseSchemaManager;


class AlterTest extends AbstractIntegrationTestCase
{
    public function setUp(): void
    {
       parent::setUp();
    }
    public function testAlterTable()
    {
        $this->assertTrue(true);
        $connection = $this->_entityManager->getConnection();
        $tableName = strtoupper("TABLE_" . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $sql = "CREATE TABLE {$tableName} (foo INTEGER DEFAULT 0 NOT NULL)";
        $connection->executeStatement($sql);

        $tableDiff = new TableDiff($tableName);
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'foo',
            new Column(
                'bar',
                Type::getType('string')
            ),
            ['type']
        );
        $statements = $this->_platform->getAlterTableSQL($tableDiff);
        $this->assertCount(1, $statements);
        foreach ($statements as $statement) {
            $connection->executeStatement($statement);
        }

        $sql = (
            "SELECT 1
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'
            AND RF.RDB\$FIELD_NAME = 'FOO'
            AND F.RDB\$FIELD_TYPE = " . FirebirdInterbaseSchemaManager::META_FIELD_TYPE_VARCHAR
        );
        $result = $connection->executeQuery($sql);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(1, $result->fetchOne(), "Column change failed. SQL: " . self::statementArrayToText($statements));
    }
}
