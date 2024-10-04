<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table\Column;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

use function boolval;
use function count;
use function func_get_args;
use function intval;
use function is_null;
use function is_string;
use function json_encode;
use function md5;
use function str_replace;
use function strtoupper;
use function strval;
use function substr;
use function trim;

use const PHP_INT_MAX;

class CreateWithColumnsTest extends AbstractIntegrationTestCase
{
    public function setUp(): void
    {
        // no Database needed here.
        $this->_platform = $this->connection->getDatabasePlatform();
    }

    #[DataProvider('dataProvider_testCreateTableWithVariousColumnOptionCombinations')]
    public function testCreateTableWithVariousColumnOptionCombinations(
        $inputFieldType,
        $expectedFieldType,
        array $options,
    ): void {
        $connection     = $this->connection;
        $tableName      = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__ . json_encode(func_get_args())), 0, 12));
        $columnTypeName = FirebirdSchemaManager::getFieldTypeIdToColumnTypeMap()[$inputFieldType];

        $table = new Table($tableName);
        $table->addColumn('foo', $columnTypeName, $options);

        $statements = $this->_platform->getCreateTableSQL($table);
        self::assertCount(1, $statements);
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
        self::assertSame($expectedFieldType, $row['RDB$FIELD_TYPE'], 'Invalid field type.');

        if (isset($options['notnull'])) {
            self::assertSame($options['notnull'], boolval(intval($row['RDB$NULL_FLAG_01'])), 'Invalid notnull.');
        }

        if (isset($options['length'])) {
            self::assertSame($options['length'], intval($row['RDB$CHARACTER_LENGTH']), 'Invalid length.');
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
        self::assertSame($expected, $row['RDB$DEFAULT_SOURCE_01'], 'Invalid default.');
    }

    public static function dataProvider_testCreateTableWithVariousColumnOptionCombinations(): Iterator
    {

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_DATE,
            FirebirdSchemaManager::META_FIELD_TYPE_DATE,
            ['notnull' => true, 'default' => '2018-01-01'],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_CHAR,
            FirebirdSchemaManager::META_FIELD_TYPE_CHAR,
            ['length' => 11, 'fixed' => true],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            [],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => false],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => true],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['fixed' => false],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['default' => 'Lorem'],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
            ['notnull' => true, 'length' => 300, 'default' => "Lorem ''opsum''"],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_TIME,
            FirebirdSchemaManager::META_FIELD_TYPE_TIME,
            ['notnull' => true, 'default' => '13:37:00'],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_FLOAT,
            FirebirdSchemaManager::META_FIELD_TYPE_DOUBLE,
            ['notnull' => true, 'default' => 3.14],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT,
            FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT,
            ['notnull' => true, 'default' => 3],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_INTEGER,
            FirebirdSchemaManager::META_FIELD_TYPE_INTEGER,
            ['notnull' => true, 'default' => 3],
        ];

        yield [
            FirebirdSchemaManager::META_FIELD_TYPE_BIGINT,
            FirebirdSchemaManager::META_FIELD_TYPE_BIGINT,
            ['notnull' => true, 'default' => PHP_INT_MAX],
        ];
    }

    public function testCreateTableWithManyDifferentColumns(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));

        $table     = new Table($tableName);
        $columns   = [];
        $columns[] = $table->addColumn('char_a', 'string', ['length' => 11, 'fixed' => true]);
        $columns[] = $table->addColumn('smallint_a', 'smallint');
        $columns[] = $table->addColumn('smallint_b', 'smallint', ['notnull' => true]);
        $columns[] = $table->addColumn('smallint_c', 'smallint', ['default' => 3]);
        $columns[] = $table->addColumn('smallint_d', 'smallint', ['autoincrement' => true]);
        $columns[] = $table->addColumn('varchar_a', 'string');
        $columns[] = $table->addColumn('varchar_b', 'string', ['notnull' => true]);
        $columns[] = $table->addColumn('varchar_c', 'string', ['length' => 300]);
        $columns[] = $table->addColumn('varchar_d', 'string', ['default' => 'Lorem']);

        $statements = $this->_platform->getCreateTableSQL($table);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertCount(1, $statements);
        } else {
            self::assertCount(3, $statements);
        }

        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        $sql    = (
            "SELECT *
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'"
        );

        $result = $connection->query($sql);
        self::assertInstanceOf(Result::class, $result);
        $rows = $result->fetchAll();
        self::assertIsArray($rows);
        self::assertCount(count($columns), $rows, 'Row count does not match column count');

        $columnsIndexed = [];
        foreach ($columns as $column) {
            $columnsIndexed[strtoupper($column->getName())] = $column;
        }

        foreach ($rows as $row) {
            $fieldName = trim((string) $row['RDB$FIELD_NAME_01']);
            self::assertArrayHasKey($fieldName, $columnsIndexed);
            $column = $columnsIndexed[$fieldName];
            self::assertArrayHasKey('RDB$FIELD_TYPE', $row);
            $expectedType      = null;
            $expectedLength    = $column->getLength();
            $expectedPrecision = $column->getPrecision();
            $expectedFixed     = $column->getFixed();
            $expectedDefault   = $column->getDefault();
            switch ($column->getType()::class) {
                case SmallIntType::class:
                    $expectedType = FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT;
                    if ($expectedPrecision === 10) {
                        $expectedPrecision = 0;
                    }

                    if ($expectedDefault !== null) {
                        $expectedDefault = strval($expectedDefault);
                    }

                    break;
                case StringType::class:
                    $expectedType = FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR;
                    if ($column->getFixed()) {
                        $expectedType = FirebirdSchemaManager::META_FIELD_TYPE_CHAR;
                    }

                    if ($expectedLength === null) {
                        $expectedLength = 255;
                    }

                    if ($expectedPrecision === 10) {
                        $expectedPrecision = null;
                    }

                    break;
            }

            self::assertSame($expectedType, $row['RDB$FIELD_TYPE'], 'Invalid field type.');
            self::assertSame($expectedLength, $row['RDB$CHARACTER_LENGTH'], 'Invalid length');
            self::assertSame($expectedPrecision, $row['RDB$FIELD_PRECISION'], 'Invalid precision');
            self::assertSame($column->getScale(), $row['RDB$FIELD_SCALE'], 'Invalid scale');
            self::assertSame($expectedFixed, ($expectedType === FirebirdSchemaManager::META_FIELD_TYPE_CHAR), 'Invalid fixed');
            self::assertSame($column->getNotnull(), boolval($row['RDB$NULL_FLAG_01']), 'Invalid notnull');

            $expectedDefaultSource = $expectedDefault;
            if ($expectedDefaultSource !== null) {
                switch ($expectedType) {
                    case FirebirdSchemaManager::META_FIELD_TYPE_DOUBLE:
                    case FirebirdSchemaManager::META_FIELD_TYPE_FLOAT:
                        $expectedDefaultSource = strval($expectedDefaultSource);
                        break;
                    case FirebirdSchemaManager::META_FIELD_TYPE_BIGINT:
                    case FirebirdSchemaManager::META_FIELD_TYPE_INTEGER:
                    case FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT:
                        $expectedDefaultSource = intval($expectedDefaultSource);
                        break;
                }
            }

            if (is_null($expectedDefaultSource)) {
                // Do nothing
            } elseif (is_string($expectedDefaultSource)) {
                $expectedDefaultSource = "DEFAULT '{$expectedDefaultSource}'";
            } else {
                $expectedDefaultSource = "DEFAULT {$expectedDefaultSource}";
            }

            // Use RF.RDB$DEFAULT_SOURCE instead of RF.RDB$DEFAULT_VALUE becuase the latter is binary.
            self::assertSame($expectedDefaultSource, $row['RDB$DEFAULT_SOURCE_01'], 'Invalid default');
        }
    }
}
