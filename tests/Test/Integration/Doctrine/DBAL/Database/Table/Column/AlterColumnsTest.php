<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table\Column;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Iterator;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

use function boolval;
use function func_get_args;
use function intval;
use function is_string;
use function json_encode;
use function md5;
use function str_replace;
use function strtoupper;
use function strval;
use function substr;

class AlterColumnsTest extends AbstractIntegrationTestCase
{
    /** @dataProvider dataProvider_testAlterTableWithVariousColumnOptionCombinations */
    public function testAlterTableWithVariousColumnOptionCombinations(
        $expectedFieldType,
        array $options,
        $createColumnSql,
    ): void {
        $connection     = $this->_entityManager->getConnection();
        $sm             = $connection->getSchemaManager();
        $tableName      = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__ . json_encode(func_get_args())), 0, 12));
        $columnTypeName = FirebirdSchemaManager::getFieldTypeIdToColumnTypeMap()[$expectedFieldType];
        $sql            = "CREATE TABLE {$tableName} ({$createColumnSql})";
        $connection->exec($sql);
        $columns = $sm->listTableColumns($tableName);
        self::assertIsArray($columns);
        self::assertCount(1, $columns);
        self::assertArrayHasKey('foo', $columns);
        $previousColumn    = $columns['foo'];
        $replacingColumn   = new Column(
            'bar',
            Type::getType($columnTypeName),
            $options,
        );
        $comparator        = new Comparator();
        $changedProperties = $comparator->diffColumn($previousColumn, $replacingColumn);

        $tableDiff                        = new TableDiff($tableName);
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'foo',
            $replacingColumn,
            $changedProperties,
            $previousColumn,
        );
        $statements                       = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertGreaterThanOrEqual(2, $statements);
        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        $sql    = (
            "SELECT *
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'
            AND RF.RDB\$FIELD_NAME = 'FOO'"
        );
        $result = $connection->query($sql);
        self::assertInstanceOf(Result::class, $result);
        $row = $result->fetch();
        self::assertIsArray($row);
        self::assertArrayHasKey('RDB$FIELD_TYPE', $row);
        self::assertSame($expectedFieldType, $row['RDB$FIELD_TYPE'], 'Invalid field type. SQL: ' . self::statementArrayToText($statements));

        if (isset($options['notnull'])) {
            self::assertSame($options['notnull'], boolval(intval($row['RDB$NULL_FLAG_01'])), 'Invalid notnull. SQL: ' . self::statementArrayToText($statements));
        }

        if (isset($options['length'])) {
            self::assertSame($options['length'], intval($row['RDB$CHARACTER_LENGTH']), 'Invalid length. SQL: ' . self::statementArrayToText($statements));
        }

        if (! isset($options['default'])) {
            return;
        }

        /**
         * Use RF.RDB$DEFAULT_SOURCE instead of RF.RDB$DEFAULT_VALUE becuase the latter is binary.
         */
        $default = $options['default'];
        switch ($expectedFieldType) {
            case FirebirdSchemaManager::META_FIELD_TYPE_DOUBLE:
            case FirebirdSchemaManager::META_FIELD_TYPE_FLOAT:
                $default = strval($default);
                break;
        }

        if (is_string($default)) {
            $default = "'" . str_replace("'", "''", $default) . "'";
        }

        $expected = "DEFAULT {$default}";
        self::assertSame($expected, $row['RDB$DEFAULT_SOURCE_01'], 'Invalid default. SQL: ' . self::statementArrayToText($statements));
    }

    public function dataProvider_testAlterTableWithVariousColumnOptionCombinations(): Iterator
    {
        /**
         * XXX
         * Missing:
         * FirebirdSchemaManager::META_FIELD_TYPE_CSTRING
         * FirebirdSchemaManager::META_FIELD_TYPE_BLOB
         * FirebirdSchemaManager::META_FIELD_TYPE_DOUBLE
         * FirebirdSchemaManager::META_FIELD_TYPE_INT64
         */
        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_CHAR,
            ['length' => 11, 'fixed' => true],
            'foo INTEGER DEFAULT 0 NOT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            [],
            'foo INTEGER DEFAULT 0 NOT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => false],
            'foo INTEGER DEFAULT 0 NOT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => false],
            'foo INTEGER DEFAULT 0',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => true],
            'foo INTEGER DEFAULT 0 NOT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => true],
            'foo INTEGER DEFAULT 0',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['fixed' => false],
            'foo INTEGER DEFAULT 0',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['default' => 'Lorem'],
            'foo INTEGER DEFAULT 0 NOT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => true, 'length' => 300, 'default' => "Lorem ''opsum''"],
            'foo INTEGER DEFAULT 0 NOT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_DATE,
            ['notnull' => true, 'default' => '2018-01-01'],
            'foo DATE DEFAULT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_TIME,
            ['notnull' => true, 'default' => '13:37:00'],
            'foo TIME DEFAULT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_FLOAT,
            ['notnull' => true, 'default' => 3.14],
            'foo FLOAT DEFAULT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT,
            ['notnull' => true, 'default' => 3],
            'foo SMALLINT DEFAULT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_INTEGER,
            ['notnull' => true, 'default' => 3],
            'foo INTEGER DEFAULT NULL',
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_BIGINT,
            ['notnull' => true, 'default' => 3],
            'foo BIGINT DEFAULT NULL',
        ];
    }
}
