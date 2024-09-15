<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table\Column;

use Doctrine\DBAL\Result;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;

/**
 *
 */
class CreateWithColumnsTest extends AbstractIntegrationTestCase
{
    /**
     * @dataProvider dataProvider_testCreateTableWithVariousColumnOptionCombinations
     */
    public function testCreateTableWithVariousColumnOptionCombinations(
        $inputFieldType,
        $expectedFieldType,
        array $options
    )
    {
        $connection = $this->_entityManager->getConnection();
        $tableName = strtoupper("TABLE_" . substr(md5(self::class . ':' . __FUNCTION__ . json_encode(func_get_args())), 0, 12));
        $columnTypeName = FirebirdSchemaManager::getFieldTypeIdToColumnTypeMap()[$inputFieldType];

        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $table->addColumn('foo', $columnTypeName, $options);

        $statements = $this->_platform->getCreateTableSQL($table);
        $this->assertCount(1, $statements);
        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        $sql = (
            "SELECT *
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'
            AND RF.RDB\$FIELD_NAME = 'FOO'"
        );
        $result = $connection->query($sql);
        $this->assertInstanceOf(Result::class, $result);
        $row = $result->fetch();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('RDB$FIELD_TYPE', $row);
        $this->assertSame($expectedFieldType, $row['RDB$FIELD_TYPE'], "Invalid field type.");

        if (isset($options['notnull'])) {
            $this->assertSame($options['notnull'], boolval(intval($row['RDB$NULL_FLAG_01'])), "Invalid notnull.");
        }
        if (isset($options['length'])) {
            $this->assertSame($options['length'], intval($row['RDB$CHARACTER_LENGTH']), "Invalid length.");
        }
        if (isset($options['default'])) {
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
            $this->assertSame($expected, $row['RDB$DEFAULT_SOURCE_01'], "Invalid default.");
        }
    }

    public function dataProvider_testCreateTableWithVariousColumnOptionCombinations()
    {
        return [
            [
                FirebirdSchemaManager::META_FIELD_TYPE_CHAR,
                FirebirdSchemaManager::META_FIELD_TYPE_CHAR,
                ['length' => 11, 'fixed' => true],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                [],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                ['notnull' => false],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                ['notnull' => true],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                ['fixed' => false],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                ['default' => 'Lorem'],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR,
                ['notnull' => true, 'length' => 300, 'default' => "Lorem ''opsum''"],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_DATE,
                FirebirdSchemaManager::META_FIELD_TYPE_DATE,
                ['notnull' => true, 'default' => '2018-01-01'],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_TIME,
                FirebirdSchemaManager::META_FIELD_TYPE_TIME,
                ['notnull' => true, 'default' => '13:37:00'],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_FLOAT,
                FirebirdSchemaManager::META_FIELD_TYPE_DOUBLE,
                ['notnull' => true, 'default' => 3.14],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT,
                FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT,
                ['notnull' => true, 'default' => 3],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_INTEGER,
                FirebirdSchemaManager::META_FIELD_TYPE_INTEGER,
                ['notnull' => true, 'default' => 3],
            ],
            [
                FirebirdSchemaManager::META_FIELD_TYPE_BIGINT,
                FirebirdSchemaManager::META_FIELD_TYPE_BIGINT,
                ['notnull' => true, 'default' => PHP_INT_MAX]
            ],
        ];
    }

    public function testCreateTableWithManyDifferentColumns()
    {
        $connection = $this->_entityManager->getConnection();
        $tableName = strtoupper("TABLE_" . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));

        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $columns = [];
        $columns[] = $table->addColumn('char_a', 'string', ['length' => 11, 'fixed' => true]);
        $columns[] = $table->addColumn('smallint_a', 'smallint');
        $columns[] = $table->addColumn('smallint_b', 'smallint', ['notnull' => true]);
        $columns[] = $table->addColumn('smallint_c', 'smallint', ['default' => 3]);
        $columns[] = $table->addColumn('smallint_d', 'smallint', ['autoincrement' => true]);
        $columns[] = $table->addColumn('varchar_a', 'string');
        $columns[] = $table->addColumn('varchar_b', 'string', ['notnull' => true,]);
        $columns[] = $table->addColumn('varchar_c', 'string', ['length' => 300]);
        $columns[] = $table->addColumn('varchar_d', 'string', ['default' => 'Lorem']);

        $statements = $this->_platform->getCreateTableSQL($table);
        if ($this->_platform instanceof Firebird3Platform) {
            $this->assertCount(1, $statements);
        } else {
            $this->assertCount(3, $statements);
        }

        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        $sql = (
            "SELECT *
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'"
        );
        $result = $connection->query($sql);
        $this->assertInstanceOf(Result::class, $result);
        $rows = $result->fetchAll();
        $this->assertIsArray($rows);
        $this->assertCount(count($columns), $rows, 'Row count does not match column count');

        $columnsIndexed = [];
        foreach ($columns as $column) {
            $columnsIndexed[strtoupper($column->getName())] = $column;
        }

        foreach ($rows as $row) {
            $fieldName = trim((string) $row['RDB$FIELD_NAME_01']);
            $this->assertArrayHasKey($fieldName, $columnsIndexed);
            $column = $columnsIndexed[$fieldName];
            $this->assertArrayHasKey('RDB$FIELD_TYPE', $row);
            $expectedType = null;
            $expectedLength = $column->getLength();
            $expectedPrecision = $column->getPrecision();
            $expectedFixed = $column->getFixed();
            $expectedDefault = $column->getDefault();
            switch ($column->getType()::class) {
                case \Doctrine\DBAL\Types\SmallIntType::class:
                    $expectedType = FirebirdSchemaManager::META_FIELD_TYPE_SMALLINT;
                    if (10 === $expectedPrecision) {
                        $expectedPrecision = 0;
                    }
                    if (null !== $expectedDefault) {
                        $expectedDefault = strval($expectedDefault);
                    }
                    break;
                case \Doctrine\DBAL\Types\StringType::class:
                    $expectedType = FirebirdSchemaManager::META_FIELD_TYPE_VARCHAR;
                    if ($column->getFixed()) {
                        $expectedType = FirebirdSchemaManager::META_FIELD_TYPE_CHAR;
                    }
                    if (null === $expectedLength) {
                        $expectedLength = 255;
                    }
                    if (10 === $expectedPrecision) {
                        $expectedPrecision = null;
                    }
                    break;
            }
            $this->assertSame($expectedType, $row['RDB$FIELD_TYPE'], "Invalid field type.");
            $this->assertSame($expectedLength, $row['RDB$CHARACTER_LENGTH'], 'Invalid length');
            $this->assertSame($expectedPrecision, $row['RDB$FIELD_PRECISION'], 'Invalid precision');
            $this->assertSame($column->getScale(), $row['RDB$FIELD_SCALE'], 'Invalid scale');
            $this->assertSame($expectedFixed, ($expectedType == FirebirdSchemaManager::META_FIELD_TYPE_CHAR), 'Invalid fixed');
            $this->assertSame($column->getNotnull(), boolval($row['RDB$NULL_FLAG_01']), 'Invalid notnull');

            $expectedDefaultSource = $expectedDefault;
            if (null !== $expectedDefaultSource) {
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
            $this->assertSame($expectedDefaultSource, $row['RDB$DEFAULT_SOURCE_01'], 'Invalid default');
        }
    }
}
