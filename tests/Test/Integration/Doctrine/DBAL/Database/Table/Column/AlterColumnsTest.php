<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table\Column;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Table;
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
use PHPUnit\Framework\Attributes\DataProvider;

class AlterColumnsTest extends AbstractIntegrationTestCase
{
    public function setUp(): void
    {
        // no Database needed here.
        $this->_platform = $this->connection->getDatabasePlatform();
    }

    #[DataProvider('dataProvider_testAlterTableWithVariousColumnOptionCombinations')]
    public function testAlterTableWithVariousColumnOptionCombinations(
        $expectedFieldType,
        array $options,
        $createColumnSql,
    ): void {
        $connection     = $this->connection;
        $sm             = $connection->createSchemaManager();
        $tableName      = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__ . json_encode(func_get_args())), 0, 12));
        $columnTypeName = FirebirdSchemaManager::getFieldTypeIdToColumnTypeMap()[$expectedFieldType];
        $this->dropTableIfExists($tableName);
        $sql            = "CREATE TABLE {$tableName} ({$createColumnSql})";
        $connection->executeStatement($sql);
        $oldTable = $sm->introspectTable($tableName);
        $columns  = $oldTable->getColumns();
        self::assertIsArray($columns);
        self::assertCount(1, $columns);
        self::assertTrue($oldTable->hasColumn('foo'), 'Column foo not found in table');
        $previousColumn  = $oldTable->getColumn('foo');
        $columnEditor    = Column::editor()
            ->setUnquotedName('foo')
            ->setTypeName($columnTypeName);

        if (isset($options['notnull'])) {
            $columnEditor = $columnEditor->setNotNull($options['notnull']);
        }

        if (isset($options['length'])) {
            $columnEditor = $columnEditor->setLength($options['length']);
        }

        if (array_key_exists('default', $options)) {
            $columnEditor = $columnEditor->setDefaultValue($options['default']);
        }

        if (isset($options['fixed'])) {
            $columnEditor = $columnEditor->setFixed($options['fixed']);
        }

        $replacingColumn = $columnEditor->create();

        $tableDiff  = new TableDiff(
            $oldTable,
            changedColumns: [new ColumnDiff($previousColumn, $replacingColumn)],
        );
        $statements = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertGreaterThanOrEqual(2, $statements);
        foreach ($statements as $statement) {
            $connection->executeStatement($statement);
        }

        $sql    = (
            "SELECT *
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'
            AND RF.RDB\$FIELD_NAME = 'FOO'"
        );
        $result = $connection->executeQuery($sql);
        self::assertInstanceOf(Result::class, $result);
        $row = $result->fetchAssociative();
        self::assertIsArray($row);
        self::assertArrayHasKey('RDB$FIELD_TYPE', $row);
        self::assertSame($expectedFieldType, $row['RDB$FIELD_TYPE'], 'Invalid field type. SQL: ' . self::statementArrayToText($statements));

        if (isset($options['notnull'])) {
            $nullFlag = $row['RDB$NULL_FLAG_01'] ?? $row['RDB$NULL_FLAG'];
            self::assertSame($options['notnull'], boolval(intval($nullFlag)), 'Invalid notnull. SQL: ' . self::statementArrayToText($statements));
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
        $defaultSource = $row['RDB$DEFAULT_SOURCE_01'] ?? $row['RDB$DEFAULT_SOURCE'];
        self::assertSame($expected, $defaultSource, 'Invalid default. SQL: ' . self::statementArrayToText($statements));
    }

    public static function dataProvider_testAlterTableWithVariousColumnOptionCombinations(): Iterator
    {
        /**
         * XXX
         * Missing:
         * FirebirdSchemaManager::META_FIELD_TYPE_CSTRING
         * FirebirdSchemaManager::META_FIELD_TYPE_BLOB
         * FirebirdSchemaManager::META_FIELD_TYPE_FLOAT (DBAL4: Doctrine 'float' maps to DOUBLE PRECISION)
         * FirebirdSchemaManager::META_FIELD_TYPE_DOUBLE ('double' is not a standard Doctrine type name)
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
