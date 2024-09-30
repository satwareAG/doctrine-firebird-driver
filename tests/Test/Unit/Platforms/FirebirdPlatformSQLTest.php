<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DateIntervalUnit;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Iterator;
use ReflectionObject;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

use function array_walk;
use function implode;
use function preg_replace;
use function strtoupper;
use function trim;
use function uniqid;

use const PHP_INT_MAX;

/**
 * Tests SQL generation. For functional tests, see FirebirdPlatformTest.
 * Inspired by:
 *
 * @link https://github.com/ISTDK/doctrine-dbal/blob/master/tests/Doctrine/Tests/DBAL/Platforms/OraclePlatformTest.php
 */
class FirebirdPlatformSQLTest extends AbstractFirebirdPlatformTestCase
{
    public function testGetBitAndComparisonExpression(): void
    {
        $found = $this->_platform->getBitAndComparisonExpression(0, 1);
        self::assertIsString($found);
        self::assertSame('BIN_AND (0, 1)', $found);
    }

    public function testGetBitOrComparisonExpression(): void
    {
        $found = $this->_platform->getBitOrComparisonExpression(0, 1);
        self::assertIsString($found);
        self::assertSame('BIN_OR (0, 1)', $found);
    }

    public function testGetDateAddDaysExpression(): void
    {
        $found = $this->_platform->getDateAddDaysExpression('2018-01-01', 1);
        self::assertIsString($found);
        self::assertSame('DATEADD(1 DAY TO 2018-01-01)', $found);
    }

    public function testGetDateAddMonthExpression(): void
    {
        $found = $this->_platform->getDateAddMonthExpression('2018-01-01', 1);
        self::assertIsString($found);
        self::assertSame('DATEADD(1 MONTH TO 2018-01-01)', $found);
    }

    /** @dataProvider dataProvider_testGetDateArithmeticIntervalExpression */
    public function testGetDateArithmeticIntervalExpression($expected, $operator, $interval, $unit): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getDateArithmeticIntervalExpression');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, '2018-01-01', $operator, $interval, $unit);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public function dataProvider_testGetDateArithmeticIntervalExpression(): Iterator
    {
        yield ['DATEADD(DAY, 1, 2018-01-01)', '', 1, DateIntervalUnit::DAY];
        yield ['DATEADD(DAY, -1, 2018-01-01)', '-', 1, DateIntervalUnit::DAY];
        yield ['DATEADD(MONTH, 1, 2018-01-01)', '', 1, DateIntervalUnit::MONTH];
        yield ['DATEADD(MONTH, 3, 2018-01-01)', '', 1, DateIntervalUnit::QUARTER];
        yield ['DATEADD(MONTH, -3, 2018-01-01)', '-', 1, DateIntervalUnit::QUARTER];
    }

    public function testGetDateDiffExpression(): void
    {
        $found = $this->_platform->getDateDiffExpression('2018-01-01', '2017-01-01');
        self::assertIsString($found);
        self::assertSame('DATEDIFF(day, 2017-01-01,2018-01-01)', $found);
    }

    public function testGetDateSubDaysExpression(): void
    {
        $found = $this->_platform->getDateSubDaysExpression('2018-01-01', 1);
        self::assertIsString($found);
        self::assertSame('DATEADD(-1 DAY TO 2018-01-01)', $found);
    }

    public function testGetDateSubMonthExpression(): void
    {
        $found = $this->_platform->getDateSubMonthExpression('2018-01-01', 1);
        self::assertIsString($found);
        self::assertSame('DATEADD(-1 MONTH TO 2018-01-01)', $found);
    }

    /** @dataProvider dataProvider_testGetLocateExpression */
    public function testGetLocateExpression($expected, $startPos): void
    {
        $found = $this->_platform->getLocateExpression('foo', 'o', $startPos);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public function dataProvider_testGetLocateExpression(): Iterator
    {
        yield ['POSITION (o in foo)', false];
        yield ['POSITION (o, foo, 1)', 1];
    }

    public function testGetRegexpExpression(): void
    {
        self::assertIsString($this->_platform->getRegexpExpression());
        self::assertSame('SIMILAR TO', $this->_platform->getRegexpExpression());
    }

    public function testGetCreateViewSQL(): void
    {
        $found = $this->_platform->getCreateViewSQL('foo', 'bar');
        self::assertIsString($found);
        self::assertSame('CREATE VIEW foo AS bar', $found);
    }

    public function testGetDropViewSQL(): void
    {
        $found = $this->_platform->getDropViewSQL('foo');
        self::assertIsString($found);
        self::assertSame('DROP VIEW foo', $found);
    }

    public function testGeneratesSqlSnippets(): void
    {
        self::assertSame('"', $this->_platform->getIdentifierQuoteCharacter());
        self::assertSame('column1 || column2 || column3', $this->_platform->getConcatExpression('column1', 'column2', 'column3'));
    }

    public function testGetDropTableSQL(): void
    {
        $found = $this->_platform->getDropTableSQL('foo');
        self::assertIsString($found);
        self::assertStringStartsWith('EXECUTE BLOCK AS', $found);
        if (! ($this->_platform instanceof Firebird3Platform)) {
            self::assertStringContainsString('DROP TRIGGER FOO_D2IT', $found);
        }

        self::assertStringContainsString('DROP TABLE foo', $found);
    }

    public function testGeneratesTypeDeclarationForIntegers(): void
    {
        self::assertSame('INTEGER', $this->_platform->getIntegerTypeDeclarationSQL([]));

        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('INTEGER GENERATED BY DEFAULT AS IDENTITY', $this->_platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]));
        } else {
            self::assertSame('INTEGER', $this->_platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]));
        }
    }

    public function testGeneratesTypeDeclarationsForStrings(): void
    {
        self::assertSame('CHAR(10)', $this->_platform->getVarcharTypeDeclarationSQL([
            'length' => 10,
            'fixed' => true,
        ]));
        self::assertSame('VARCHAR(50)', $this->_platform->getVarcharTypeDeclarationSQL(['length' => 50]));
        self::assertSame('VARCHAR(255)', $this->_platform->getVarcharTypeDeclarationSQL([]));
    }

    /**
     * @group DBAL-1097
     * @dataProvider dataProvider_testGeneratesAdvancedForeignKeyOptionsSQL
     */
    public function testGeneratesAdvancedForeignKeyOptionsSQL($expected, array $options): void
    {
        $foreignKey = new ForeignKeyConstraint(
            ['foo'],
            'foreign_table',
            ['bar'],
            null,
            $options,
        );
        self::assertSame($expected, $this->_platform->getAdvancedForeignKeyOptionsSQL($foreignKey));
    }

    /** @return array */
    public function dataProvider_testGeneratesAdvancedForeignKeyOptionsSQL(): Iterator
    {
        yield ['', []];
        yield [' ON UPDATE CASCADE', ['onUpdate' => 'CASCADE']];
        yield [' ON DELETE CASCADE', ['onDelete' => 'CASCADE']];
        yield [' ON DELETE NO ACTION', ['onDelete' => 'NO ACTION']];
        yield [' ON DELETE RESTRICT', ['onDelete' => 'RESTRICT']];
        yield [' ON UPDATE SET NULL ON DELETE SET NULL', ['onUpdate' => 'SET NULL', 'onDelete' => 'SET NULL']];
    }

    public function testModifyLimitQuery(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertSame('SELECT * FROM user ROWS 1 TO 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertSame('SELECT * FROM user ROWS 1 TO 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyLimit(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', null, 10);
        self::assertEquals('SELECT * FROM user ROWS 11 TO ' . PHP_INT_MAX, $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        self::assertSame('SELECT * FROM user ORDER BY username ASC ROWS 1 TO 10', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        self::assertSame('SELECT * FROM user ORDER BY username DESC ROWS 1 TO 10', $sql);
    }

    public function testGenerateTableWithAutoincrement(): void
    {
        $columnName = strtoupper('id' . uniqid());
        $tableName  = strtoupper('table' . uniqid());
        $table      = new Table($tableName);
        $column     = $table->addColumn($columnName, 'integer');
        $column->setAutoincrement(true);
        $statements = $this->_platform->getCreateTableSQL($table);
        //strip all the whitespace from the statements
        array_walk($statements, static function (&$value): void {
            $value = preg_replace('/\s+/', ' ', $value);
        });
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertCount(1, $statements);
            self::assertArrayHasKey(0, $statements);
            self::assertSame("CREATE TABLE {$tableName} ({$columnName} INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL)", $statements[0]);
        } else {
            self::assertCount(3, $statements);
            self::assertArrayHasKey(0, $statements);
            self::assertSame("CREATE TABLE {$tableName} ({$columnName} INTEGER NOT NULL)", $statements[0]);
            self::assertArrayHasKey(1, $statements);
            self::assertMatchesRegularExpression('/^CREATE SEQUENCE TABLE[0-9A-Z]+_D2IS$/', $statements[1]);
            self::assertArrayHasKey(2, $statements);
            $regex  = '/^';
            $regex .= 'CREATE TRIGGER TABLE([0-9A-Z]+)_D2IT FOR TABLE\1';
            $regex .= ' BEFORE INSERT AS BEGIN IF \(\(NEW.ID([0-9A-Z]+) IS NULL\) OR \(NEW.ID\2 = 0\)\) THEN';
            $regex .= ' BEGIN NEW.ID\2 = NEXT VALUE FOR TABLE\1_D2IS; END END;';
            $regex .= '$/';
            self::assertMatchesRegularExpression($regex, $statements[2]);
        }
    }

    public function testGenerateTableWithMultiColumnUniqueIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('foo', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('bar', 'string', ['notnull' => false, 'length' => 255]);
        $table->addUniqueIndex(['foo', 'bar']);
        $statements = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $statements);
        self::assertArrayHasKey(0, $statements);
        self::assertSame('CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)', $statements[0]);
        self::assertArrayHasKey(1, $statements);
        self::assertMatchesRegularExpression('/^CREATE UNIQUE INDEX UNIQ_[0-9A-Z]+ ON test \(foo, bar\)$/', $statements[1]);
    }

    public function testGeneratesIndexCreationSql(): void
    {
        $indexDef = new Index('my_idx', ['user_name', 'last_login']);
        $found    = $this->_platform->getCreateIndexSQL($indexDef, 'mytable');
        $expected = 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
        self::assertSame($expected, $found);
    }

    public function testGeneratesUniqueIndexCreationSql(): void
    {
        $indexDef = new Index('index_name', ['test', 'test2'], true);
        $found    = $this->_platform->getCreateIndexSQL($indexDef, 'test');
        $expected = 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
        self::assertSame($expected, $found);
    }

    /**
     * @group DBAL-472
     * @group DBAL-1001
     */
    public function testAlterTableNotNULL(): void
    {
        $sm        = $this->connection->createSchemaManager();
        $fromTable = new Table('mytable');
        $fromTable->addColumn('foo', Types::TEXT, ['length' => 255, 'notnull' => false]);
        $fromTable->addColumn('bar', Types::STRING, ['length' => 10, 'notnull' => false]);
        $fromTable->addColumn('metar', 'string', ['notnull' => true]);

        $toTable = clone $fromTable;
        $toTable->dropColumn('foo');
        $toTable->addColumn('foo', Types::STRING, ['length' => 255, 'notnull' => true, 'default' => 'bla']);
        $toTable->dropColumn('bar');

        $toTable->addColumn('bar', Types::STRING, ['length' => 255, 'default' => 'bla', 'notnull' => true]);
        $toTable->dropColumn('metar');

         $toTable->addColumn('metar', 'string', ['notnull' => false, 'length' => 255]);

        $tableDiff = $sm->createComparator()->compareTables($fromTable, $toTable);

        $found = $this->connection->getDatabasePlatform()->getAlterTableSQL($tableDiff);
        self::assertCount(7, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN foo TYPE VARCHAR(255)', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame("ALTER TABLE mytable ALTER foo SET DEFAULT 'bla'", $found[1]);
        self::assertArrayHasKey(5, $found);
        self::assertSame('ALTER TABLE mytable ALTER bar TYPE VARCHAR(255)', $found[5]);
        self::assertArrayHasKey(3, $found);
        self::assertSame("ALTER TABLE mytable ALTER bar SET DEFAULT 'bla'", $found[3]);
        self::assertArrayHasKey(4, $found);
        self::assertArrayHasKey(6, $found);
        if ($this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::assertSame('ALTER TABLE mytable ALTER bar SET NOT NULL', $found[4]);
            self::assertSame('ALTER TABLE mytable ALTER metar DROP NOT NULL', $found[6]);
        } else {
            self::assertSame("UPDATE RDB\$RELATION_FIELDS SET RDB\$NULL_FLAG = 1 WHERE UPPER(RDB\$FIELD_NAME) = UPPER('bar') AND UPPER(RDB\$RELATION_NAME) = UPPER('mytable')", $found[4]);
            self::assertSame("UPDATE RDB\$RELATION_FIELDS SET RDB\$NULL_FLAG = NULL WHERE UPPER(RDB\$FIELD_NAME) = UPPER('metar') AND UPPER(RDB\$RELATION_NAME) = UPPER('mytable')", $found[6]);
        }
    }

    public function testReturnsBinaryTypeDeclarationSQL(): void
    {
        self::assertSame('VARCHAR(255)', $this->_platform->getBinaryTypeDeclarationSQL([]));
        self::assertSame('VARCHAR(8191)', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 0]));
        self::assertSame('VARCHAR(8190)', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 8190]));
        self::assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(['length' => 8192]));
        self::assertSame('CHAR(255)', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true]));
        self::assertSame('CHAR(8191)', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 0]));
        self::assertSame('CHAR(8190)', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 8190]));
        self::assertSame('BLOB', $this->_platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 8192]));
    }

    public function testGetCreateAutoincrementSql(): void
    {
        $found = $this->_platform->getCreateAutoincrementSql('bar', 'foo');
        self::assertIsArray($found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame([], $found);
        } else {
            self::assertArrayHasKey(0, $found);
            self::assertSame('CREATE SEQUENCE FOO_D2IS', $found[0]);
            self::assertArrayHasKey(1, $found);
            self::assertStringStartsWith('CREATE TRIGGER FOO_D2IT FOR FOO', $found[1]);
            self::assertStringContainsString('NEW.BAR = NEXT VALUE FOR FOO_D2IS;', $found[1]);
        }
    }

    public function testGetDropAutoincrementSql(): void
    {
        $found = $this->_platform->getDropAutoincrementSql('foo');
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('', $found);
        } else {
            self::assertStringStartsWith('EXECUTE BLOCK', $found);
            self::assertStringContainsString('DROP TRIGGER FOO_D2IT', $found);
            self::assertStringContainsString('DROP SEQUENCE FOO_D2IS', $found);
        }
    }

    /** @group DBAL-1004 */
    public function testAltersTableColumnCommentWithExplicitlyQuotedIdentifiers(): void
    {
        $table1     = new Table(
            '"foo"',
            [
                new Column(
                    '"bar"',
                    Type::getType('integer'),
                ),
            ],
        );
        $table2     = new Table(
            '"foo"',
            [
                new Column(
                    '"bar"',
                    Type::getType('integer'),
                    ['comment' => 'baz'],
                ),
            ],
        );
        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($table1, $table2);
        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['COMMENT ON COLUMN "foo"."bar" IS \'baz\''], $this->_platform->getAlterTableSQL($tableDiff));
    }

    public function testQuotedTableNames(): void
    {
        $table = new Table('"test"');
        $table->addColumn('"id"', 'integer', ['autoincrement' => true]);
        self::assertTrue($table->isQuoted());
        self::assertSame('test', $table->getName());
        self::assertSame('"test"', $table->getQuotedName($this->_platform));
        $sql = $this->_platform->getCreateTableSQL($table);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertCount(1, $sql);
            self::assertArrayHasKey(0, $sql);
            self::assertSame('CREATE TABLE "test" ("id" INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL)', $sql[0]);
        } else {
            self::assertCount(3, $sql);
            self::assertArrayHasKey(0, $sql);
            self::assertSame('CREATE TABLE "test" ("id" INTEGER NOT NULL)', $sql[0]);
            self::assertArrayHasKey(1, $sql);
            self::assertSame('CREATE SEQUENCE TEST_D2IS', $sql[1]);
            self::assertArrayHasKey(2, $sql);
            $expectedCreateTrigger = preg_replace('/\s+/', ' ', trim('
            CREATE TRIGGER "test_D2IT" FOR "test"
                BEFORE INSERT
                AS
                BEGIN
                    IF ((NEW."id" IS NULL) OR
                        (NEW."id" = 0)) THEN
                    BEGIN
                        NEW."id" = NEXT VALUE FOR TEST_D2IS;
                    END
                END;
        '));
            self::assertEquals($expectedCreateTrigger, preg_replace('/\s+/', ' ', trim((string) $sql[2])));
        }
    }

    public function testGeneratesPartialIndexesSqlOnlyWhenSupportingPartialIndexes(): void
    {
        $where    = 'test IS NULL AND test2 IS NOT NULL';
        $indexDef = new Index('name', ['test', 'test2'], false, false, [], ['where' => $where]);
        // $uniqueIndex = new Index('name', ['test', 'test2'], true, false, [], ['where' => $where]);
        $uniqueIndex = new UniqueConstraint('name', ['test', 'test2']);

        $expected  = ' WHERE ' . $where;
        $actuals   = [];
        $actuals[] = $this->_platform->getIndexDeclarationSQL('name', $indexDef);
        $actuals[] = $this->_platform->getUniqueConstraintDeclarationSQL('name', $uniqueIndex);
        $actuals[] = $this->_platform->getCreateIndexSQL($indexDef, 'table');
        foreach ($actuals as $actual) {
            if ($this->_platform->supportsPartialIndexes()) {
                self::assertStringEndsWith($expected, $actual, 'WHERE clause should be present');
            } else {
                self::assertStringEndsNotWith($expected, $actual, 'WHERE clause should NOT be present');
            }
        }
    }

    public function testGeneratesForeignKeyCreationSql(): void
    {
        $fk       = new ForeignKeyConstraint(['fk_name_id'], 'other_table', ['id'], '');
        $found    = $this->_platform->getCreateForeignKeySQL($fk, 'test');
        $expected = 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
        self::assertSame($expected, $found);
    }

    public function testGeneratesConstraintCreationSql(): void
    {
        $idx      = new Index('constraint_name', ['test'], true, false);
        $found    = $this->_platform->getCreateConstraintSQL($idx, 'test');
        $expected = 'ALTER TABLE test ADD CONSTRAINT constraint_name UNIQUE (test)';
        self::assertSame($expected, $found);
        $pk       = new Index('constraint_name', ['test'], true, true);
        $found    = $this->_platform->getCreateConstraintSQL($pk, 'test');
        $expected = 'ALTER TABLE test ADD CONSTRAINT constraint_name PRIMARY KEY (test)';
        self::assertSame($expected, $found);
        $fk                 = new ForeignKeyConstraint(['fk_name'], 'foreign', ['id'], 'constraint_fk');
        $found              = $this->_platform->getCreateConstraintSQL($fk, 'test');
        $quotedForeignTable = $fk->getQuotedForeignTableName($this->_platform);
        $expected           = "ALTER TABLE test ADD CONSTRAINT constraint_fk FOREIGN KEY (fk_name) REFERENCES {$quotedForeignTable} (id)";
        self::assertSame($expected, $found);
    }

    public function testGeneratesTableAlterationSqlThrowsException(): void
    {
        $this->expectExceptionMessageMatches('/.*firebird does not support it.*/i');
        $this->expectException(Exception::class);
        $table = new Table('mytable');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'string');
        $table->addColumn('bloo', 'boolean');
        $table->setPrimaryKey(['id']);
        $tableDiff                         = new TableDiff('mytable');
        $tableDiff->fromTable              = $table;
        $tableDiff->newName                = 'userlist';
        $tableDiff->addedColumns['quota']  = new Column(
            'quota',
            Type::getType('integer'),
            ['notnull' => false],
        );
        $tableDiff->removedColumns['foo']  = new Column(
            'foo',
            Type::getType('integer'),
        );
        $tableDiff->changedColumns['bar']  = new ColumnDiff(
            'bar',
            new Column(
                'baz',
                Type::getType('string'),
                ['default' => 'def'],
            ),
            ['type', 'notnull', 'default'],
        );
        $tableDiff->changedColumns['bloo'] = new ColumnDiff(
            'bloo',
            new Column(
                'bloo',
                Type::getType('boolean'),
                ['default' => false],
            ),
            ['type', 'notnull', 'default'],
        );
        $sm                                = $this->connection->createSchemaManager();
        $sm->renameTable('old', 'new');
        $this->_platform->getAlterTableSQL($tableDiff);
    }

    public function testGetCustomColumnDeclarationSql(): void
    {
        $field = ['columnDefinition' => 'bar'];
        self::assertSame('foo bar', $this->_platform->getColumnDeclarationSQL('foo', $field));
    }

    public function testGetCreateTableSqlDispatchEvent(): void
    {
        $listenerMock = $this
            ->getMockBuilder('GetCreateTableSqlDispatchEvenListener')
            ->disableOriginalConstructor()
            ->setMethods([
                'onSchemaCreateTable',
                'onSchemaCreateTableColumn',
            ])
            ->getMock();
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaCreateTable');
        $listenerMock
            ->expects($this->exactly(2))
            ->method('onSchemaCreateTableColumn');
        $eventManager = new EventManager();
        $eventManager->addEventListener(
            [
                Events::onSchemaCreateTable,
                Events::onSchemaCreateTableColumn,
            ],
            $listenerMock,
        );
        $this->_platform->setEventManager($eventManager);
        $table = new Table('test');
        $table->addColumn('foo', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('bar', 'string', ['notnull' => false, 'length' => 255]);
        $this->_platform->getCreateTableSQL($table);
    }

    public function testGetDropTableSqlDispatchEvent(): void
    {
        $listenerMock = $this
            ->getMockBuilder('GetDropTableSqlDispatchEventListener')
            ->disableOriginalConstructor()
            ->setMethods(['onSchemaDropTable'])
            ->getMock();
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaDropTable');
        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaDropTable], $listenerMock);
        $this->_platform->setEventManager($eventManager);
        $this->_platform->getDropTableSQL('TABLE');
    }

    public function testGetAlterTableSqlDispatchEvent(): void
    {
        $listenerMock = $this
            ->getMockBuilder('GetAlterTableSqlDispatchEvenListener')
            ->disableOriginalConstructor()
            ->setMethods([
                'onSchemaAlterTable',
                'onSchemaAlterTableAddColumn',
                'onSchemaAlterTableRemoveColumn',
                'onSchemaAlterTableChangeColumn',
                'onSchemaAlterTableRenameColumn',
            ])
            ->getMock();
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTable');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableAddColumn');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableRemoveColumn');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableChangeColumn');
        $listenerMock
            ->expects($this->once())
            ->method('onSchemaAlterTableRenameColumn');
        $eventManager = new EventManager();
        $events       = [
            Events::onSchemaAlterTable,
            Events::onSchemaAlterTableAddColumn,
            Events::onSchemaAlterTableRemoveColumn,
            Events::onSchemaAlterTableChangeColumn,
            Events::onSchemaAlterTableRenameColumn,
        ];
        $eventManager->addEventListener($events, $listenerMock);
        $this->_platform->setEventManager($eventManager);
        $table = new Table('mytable');
        $table->addColumn('removed', 'integer');
        $table->addColumn('changed', 'integer');
        $table->addColumn('renamed', 'integer');
        $tableDiff                            = new TableDiff('mytable');
        $tableDiff->fromTable                 = $table;
        $tableDiff->addedColumns['added']     = new Column(
            'added',
            Type::getType('integer'),
            [],
        );
        $tableDiff->removedColumns['removed'] = new Column(
            'removed',
            Type::getType('integer'),
            [],
        );
        $tableDiff->changedColumns['changed'] = new ColumnDiff(
            'changed',
            new Column(
                'changed2',
                Type::getType('string'),
                [],
            ),
            [],
        );
        $tableDiff->renamedColumns['renamed'] = new Column(
            'renamed2',
            Type::getType('integer'),
            [],
        );
        $this->_platform->getAlterTableSQL($tableDiff);
    }

    /** @group DBAL-42 */
    public function testCreateTableColumnComments(): void
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer', ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);
        $found = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('CREATE TABLE test (id INTEGER NOT NULL, CONSTRAINT TEST_PK PRIMARY KEY (id))', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame("COMMENT ON COLUMN test.id IS 'This is a comment'", $found[1]);
    }

    /** @group DBAL-42 */
    public function testAlterTableColumnComments(): void
    {
        $tableDiff                        = new TableDiff('mytable');
        $tableDiff->addedColumns['quota'] = new Column(
            'quota',
            Type::getType('integer'),
            ['comment' => 'A comment'],
        );
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'foo',
            new Column(
                'foo',
                Type::getType('string'),
            ),
            ['comment'],
        );
        $tableDiff->changedColumns['bar'] = new ColumnDiff(
            'bar',
            new Column(
                'baz',
                Type::getType('string'),
                ['comment' => 'B comment'],
            ),
            ['comment'],
        );
        $found                            = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertCount(4, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE mytable ADD quota INTEGER NOT NULL', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame("COMMENT ON COLUMN mytable.quota IS 'A comment'", $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame("COMMENT ON COLUMN mytable.foo IS ''", $found[2]);
        self::assertArrayHasKey(3, $found);
        self::assertSame("COMMENT ON COLUMN mytable.baz IS 'B comment'", $found[3]);
    }

    public function testCreateTableColumnTypeComments(): void
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('data', 'array');
        $table->setPrimaryKey(['id']);
        $found = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('CREATE TABLE test (id INTEGER NOT NULL, data BLOB SUB_TYPE TEXT NOT NULL, CONSTRAINT TEST_PK PRIMARY KEY (id))', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame("COMMENT ON COLUMN test.data IS '(DC2Type:array)'", $found[1]);
    }

    public function testGetDefaultValueDeclarationSQL(): void
    {
        // non-timestamp value will get single quotes
        $field = [
            'type' => 'string',
            'default' => 'non_timestamp',
        ];
        self::assertSame(" DEFAULT 'non_timestamp'", $this->_platform->getDefaultValueDeclarationSQL($field));
    }

    public function testGetDefaultValueDeclarationSQLDateTime(): void
    {
        // timestamps on datetime types should not be quoted
        foreach (['datetime', 'datetimetz'] as $type) {
            $field = [
                'type' => Type::getType($type),
                'default' => $this->_platform->getCurrentTimestampSQL(),
            ];
            self::assertSame(' DEFAULT ' . $this->_platform->getCurrentTimestampSQL(), $this->_platform->getDefaultValueDeclarationSQL($field));
        }
    }

    public function testGetDefaultValueDeclarationSQLForIntegerTypes(): void
    {
        foreach (['bigint', 'integer', 'smallint'] as $type) {
            $field = [
                'type'    => Type::getType($type),
                'default' => 1,
            ];
            self::assertSame(' DEFAULT 1', $this->_platform->getDefaultValueDeclarationSQL($field));
        }
    }

    /** @group DBAL-374 */
    public function testQuotedColumnInPrimaryKeyPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', 'string');
        $table->setPrimaryKey(['create']);
        $found = $this->_platform->getCreateTableSQL($table);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertArrayHasKey(0, $found);
        $expected = 'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, CONSTRAINT "quoted_PK" PRIMARY KEY ("create"))';
        self::assertSame($expected, $found[0]);
    }

    /** @group DBAL-374 */
    public function testQuotedColumnInIndexPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', 'string');
        $table->addIndex(['create']);
        $found = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertMatchesRegularExpression('/^CREATE INDEX IDX_[0-9A-F]+ ON "quoted" \("create"\)$/', $found[1]);
    }

    public function testQuotedNameInIndexSQL(): void
    {
        $table = new Table('test');
        $table->addColumn('column1', 'string');
        $table->addIndex(['column1'], '`key`');
        $found    = $this->_platform->getCreateTableSQL($table);
        $expected = [
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        ];
        self::assertSame($expected, $found);
    }

    /** @group DBAL-374 */
    public function testQuotedColumnInForeignKeyPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', 'string');
        $table->addColumn('foo', 'string');
        $table->addColumn('`bar`', 'string');
        // Foreign table with reserved keyword as name (needs quotation).
        $foreignTable = new Table('foreign');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $table->addForeignKeyConstraint(
            $foreignTable,
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_RESERVED_KEYWORD',
        );
        // Foreign table with non-reserved keyword as name (does not need quotation).
        $foreignTable = new Table('foo');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $table->addForeignKeyConstraint(
            $foreignTable,
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_NON_RESERVED_KEYWORD',
        );
        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable = new Table('`foo-bar`');
        $foreignTable->addColumn('create', 'string');    // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $table->addForeignKeyConstraint(
            $foreignTable,
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_INTENDED_QUOTATION',
        );
        $found = $this->_platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS);
        self::assertCount(4, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar")', $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame('ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar")', $found[2]);
        self::assertArrayHasKey(3, $found);
        self::assertSame('ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar")', $found[3]);
    }

    /** @group DBAL-1051 */
    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): void
    {
        $index = new UniqueConstraint('select', ['foo']);
        $found = $this->_platform->getUniqueConstraintDeclarationSQL('select', $index);
        self::assertSame('CONSTRAINT "select" UNIQUE (foo)', $found);
    }

    /** @group DBAL-1051 */
    public function testQuotesReservedKeywordInIndexDeclarationSQL(): void
    {
        $index = new Index('select', ['foo']);
        $found = $this->_platform->getIndexDeclarationSQL('select', $index);
        self::assertSame('INDEX "select" (foo)', $found);
    }

    /** @group DBAL-585 */
    public function testAlterTableChangeQuotedColumn(): void
    {
        $tableDiff                        = new TableDiff('mytable');
        $tableDiff->fromTable             = new Table('mytable');
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'select',
            new Column(
                'select',
                Type::getType('string'),
            ),
            ['type'],
        );
        self::assertStringContainsString($this->_platform->quoteIdentifier('select'), implode(';', $this->_platform->getAlterTableSQL($tableDiff)));
    }

    /** @group DBAL-234 */
    public function testAlterTableRenameIndex(): void
    {
        $tableDiff            = new TableDiff('mytable');
        $tableDiff->fromTable = new Table('mytable');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'idx_foo' => new Index('idx_bar', ['id']),
        ];
        $found                     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX idx_foo', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX idx_bar ON mytable (id)', $found[1]);
    }

    /** @group DBAL-234 */
    public function testQuotesAlterTableRenameIndex(): void
    {
        $tableDiff            = new TableDiff('table');
        $tableDiff->fromTable = new Table('table');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'create' => new Index('select', ['id']),
            '`foo`'  => new Index('`bar`', ['id']),
        ];
        $found                     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(4, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX "create"', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX "select" ON "table" (id)', $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame('DROP INDEX "foo"', $found[2]);
        self::assertArrayHasKey(3, $found);
        self::assertSame('CREATE INDEX "bar" ON "table" (id)', $found[3]);
    }

    /** @group DBAL-835 */
    public function testQuotesAlterTableRenameColumn(): void
    {
        $fromTable = new Table('mytable');
        $fromTable->addColumn('unquoted1', 'integer', ['comment' => 'Unquoted 1']);
        $fromTable->addColumn('unquoted2', 'integer', ['comment' => 'Unquoted 2']);
        $fromTable->addColumn('unquoted3', 'integer', ['comment' => 'Unquoted 3']);
        $fromTable->addColumn('create', 'integer', ['comment' => 'Reserved keyword 1']);
        $fromTable->addColumn('table', 'integer', ['comment' => 'Reserved keyword 2']);
        $fromTable->addColumn('select', 'integer', ['comment' => 'Reserved keyword 3']);
        $fromTable->addColumn('`quoted1`', 'integer', ['comment' => 'Quoted 1']);
        $fromTable->addColumn('`quoted2`', 'integer', ['comment' => 'Quoted 2']);
        $fromTable->addColumn('`quoted3`', 'integer', ['comment' => 'Quoted 3']);
        $toTable = new Table('mytable');
        $toTable->addColumn('unquoted', 'integer', ['comment' => 'Unquoted 1']); // unquoted -> unquoted
        $toTable->addColumn('where', 'integer', ['comment' => 'Unquoted 2']); // unquoted -> reserved keyword
        $toTable->addColumn('`foo`', 'integer', ['comment' => 'Unquoted 3']); // unquoted -> quoted
        $toTable->addColumn('reserved_keyword', 'integer', ['comment' => 'Reserved keyword 1']); // reserved keyword -> unquoted
        $toTable->addColumn('from', 'integer', ['comment' => 'Reserved keyword 2']); // reserved keyword -> reserved keyword
        $toTable->addColumn('`bar`', 'integer', ['comment' => 'Reserved keyword 3']); // reserved keyword -> quoted
        $toTable->addColumn('quoted', 'integer', ['comment' => 'Quoted 1']); // quoted -> unquoted
        $toTable->addColumn('and', 'integer', ['comment' => 'Quoted 2']); // quoted -> reserved keyword
        $toTable->addColumn('`baz`', 'integer', ['comment' => 'Quoted 3']); // quoted -> quoted
        $comparator = new Comparator();
        $found      = $this->_platform->getAlterTableSQL($comparator->diffTable($fromTable, $toTable));
        self::assertIsArray($found);
        self::assertCount(9, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN unquoted1 TO unquoted', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN unquoted2 TO "where"', $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN unquoted3 TO "foo"', $found[2]);
        self::assertArrayHasKey(3, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN "create" TO reserved_keyword', $found[3]);
        self::assertArrayHasKey(4, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN "table" TO "from"', $found[4]);
        self::assertArrayHasKey(5, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN "select" TO "bar"', $found[5]);
        self::assertArrayHasKey(6, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN quoted1 TO quoted', $found[6]);
        self::assertArrayHasKey(7, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN quoted2 TO "and"', $found[7]);
        self::assertArrayHasKey(8, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN quoted3 TO "baz"', $found[8]);
    }

    /** @group DBAL-807 */
    public function testAlterTableRenameIndexInSchema(): void
    {
        $tableDiff            = new TableDiff('myschema.mytable');
        $tableDiff->fromTable = new Table('myschema.mytable');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'idx_foo' => new Index('idx_bar', ['id']),
        ];
        $found                     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX idx_foo', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX idx_bar ON myschema.mytable (id)', $found[1]);
    }

    /** @group DBAL-807 */
    public function testQuotesAlterTableRenameIndexInSchema(): void
    {
        $tableDiff            = new TableDiff('`schema`.table');
        $tableDiff->fromTable = new Table('`schema`.table');
        $tableDiff->fromTable->addColumn('id', 'integer');
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'create' => new Index('select', ['id']),
            '`foo`'  => new Index('`bar`', ['id']),
        ];
        $found                     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(4, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX "create"', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX "select" ON "schema"."table" (id)', $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame('DROP INDEX "foo"', $found[2]);
        self::assertArrayHasKey(3, $found);
        self::assertSame('CREATE INDEX "bar" ON "schema"."table" (id)', $found[3]);
    }

    public function testGetCommentOnColumnSQLWithoutQuoteCharacter(): void
    {
        $found = $this->_platform->getCommentOnColumnSQL('mytable', 'id', 'This is a comment');
        self::assertSame("COMMENT ON COLUMN mytable.id IS 'This is a comment'", $found);
    }

    public function testGetCommentOnColumnSQLWithQuoteCharacter(): void
    {
        $found = $this->_platform->getCommentOnColumnSQL('mytable', 'id', "It's a quote !");
        self::assertSame("COMMENT ON COLUMN mytable.id IS 'It''s a quote !'", $found);
    }

    /** @group DBAL-1004 */
    public function testGetCommentOnColumnSQL(): void
    {
        $found = $this->_platform->getCommentOnColumnSQL('foo', 'bar', 'comment'); // regular identifiers
        self::assertSame('COMMENT ON COLUMN foo.bar IS \'comment\'', $found);
        $found = $this->_platform->getCommentOnColumnSQL('`Foo`', '`BAR`', 'comment'); // explicitly quoted identifiers
        self::assertSame('COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'', $found);
        $found = $this->_platform->getCommentOnColumnSQL('select', 'from', 'comment'); // reserved keyword identifiers
        self::assertSame('COMMENT ON COLUMN "select"."from" IS \'comment\'', $found);
    }

    public function testQuoteStringLiteral(): void
    {
        $found = $this->_platform->quoteStringLiteral('No quote');
        self::assertSame("'No quote'", $found);
        $found = $this->_platform->quoteStringLiteral('It\'s a quote');
        self::assertSame("'It''s a quote'", $found);
        $found = $this->_platform->quoteStringLiteral('\'');
        self::assertSame("''''", $found);
    }

    /** @group DBAL-1010 */
    public function testGeneratesAlterTableRenameColumnSQL(): void
    {
        $table = new Table('foo');
        $table->addColumn(
            'bar',
            'integer',
            ['notnull' => true, 'default' => 666, 'comment' => 'rename test'],
        );
        $tableDiff                        = new TableDiff('foo');
        $tableDiff->fromTable             = $table;
        $tableDiff->renamedColumns['bar'] = new Column(
            'baz',
            Type::getType('integer'),
            ['notnull' => true, 'default' => 666, 'comment' => 'rename test'],
        );
        $found                            = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE foo ALTER COLUMN bar TO baz', $found[0]);
    }

    /** @group DBAL-1016 */
    public function testQuotesTableIdentifiersInAlterTableSQL(): void
    {
        $table = new Table('"foo"');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk', 'integer');
        $table->addColumn('fk2', 'integer');
        $table->addColumn('fk3', 'integer');
        $table->addColumn('bar', 'integer');
        $table->addColumn('baz', 'integer');
        $table->addForeignKeyConstraint('fk_table', ['fk'], ['id'], [], 'fk1');
        $table->addForeignKeyConstraint('fk_table', ['fk2'], ['id'], [], 'fk2');

        $table2 = clone $table;

        $table2->addColumn('bloo', 'integer');
        $table2->dropColumn('bar');
        $table2->addColumn('bar', 'integer', ['notnull' => false]);
        $table2->dropColumn('id');
        $table2->addColumn('war', 'integer');
        $table2->dropColumn('baz');

        $table2->addForeignKeyConstraint('fk_table', ['fk3'], ['id'], [], 'fk_add');
        $table2->removeForeignKey('fk2');
        $table2->addForeignKeyConstraint('fk_table2', ['fk2'], ['id'], [], 'fk2');
        $table2->removeForeignKey('fk1');

        $tableDiff = $this->connection->createSchemaManager()->createComparator()->compareTables($table, $table2);

        $found = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(10, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE "foo" DROP CONSTRAINT fk1', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('ALTER TABLE "foo" DROP CONSTRAINT fk2', $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame('ALTER TABLE "foo" ADD bloo INTEGER NOT NULL', $found[2]);
        self::assertArrayHasKey(5, $found);
        self::assertSame('ALTER TABLE "foo" DROP baz', $found[5]);
        self::assertArrayHasKey(6, $found);
        /**
         * Firebird 3
         */
        if ($this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::assertSame('ALTER TABLE "foo" ALTER bar DROP NOT NULL', $found[6]);
        } else {
            self::assertSame('UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = NULL WHERE UPPER(RDB$FIELD_NAME) = UPPER(\'bar\') '
            . 'AND UPPER(RDB$RELATION_NAME) = UPPER(\'foo\')', $found[6]);
        }

        self::assertArrayHasKey(7, $found);
        self::assertSame('ALTER TABLE "foo" ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)', $found[7]);
        self::assertArrayHasKey(8, $found);
        self::assertSame('ALTER TABLE "foo" ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)', $found[8]);
    }

    /** @group DBAL-1090 */
    public function testAlterStringToFixedString(): void
    {
        $table = new Table('mytable');
        $table->addColumn('name', 'string', ['length' => 2]);
        $tableDiff                         = new TableDiff('mytable');
        $tableDiff->fromTable              = $table;
        $tableDiff->changedColumns['name'] = new ColumnDiff(
            'name',
            new Column(
                'name',
                Type::getType('string'),
                ['fixed' => true, 'length' => 2],
            ),
            ['fixed'],
        );
        $found                             = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN name TYPE CHAR(2)', $found[0]);
    }

    /** @group DBAL-1062 */
    public function testGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): void
    {
        $foreignTable = new Table('foreign_table');
        $foreignTable->addColumn('id', 'integer');
        $foreignTable->setPrimaryKey(['id']);
        $primaryTable = new Table('mytable');
        $primaryTable->addColumn('foo', 'integer');
        $primaryTable->addColumn('bar', 'integer');
        $primaryTable->addColumn('baz', 'integer');
        $primaryTable->addIndex(['foo'], 'idx_foo');
        $primaryTable->addIndex(['bar'], 'idx_bar');
        $primaryTable->addForeignKeyConstraint($foreignTable, ['foo'], ['id'], [], 'fk_foo');
        $primaryTable->addForeignKeyConstraint($foreignTable, ['bar'], ['id'], [], 'fk_bar');
        $tableDiff                            = new TableDiff('mytable');
        $tableDiff->fromTable                 = $primaryTable;
        $tableDiff->renamedIndexes['idx_foo'] = new Index('idx_foo_renamed', ['foo']);
        $found                                = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX idx_foo', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX idx_foo_renamed ON mytable (foo)', $found[1]);
    }

    /**
     * @group DBAL-1082
     * @dataProvider getGeneratesDecimalTypeDeclarationSQL
     */
    public function testGeneratesDecimalTypeDeclarationSQL(array $column, $expectedSql): void
    {
        self::assertSame($expectedSql, $this->_platform->getDecimalTypeDeclarationSQL($column));
    }

    /** @return array */
    public function getGeneratesDecimalTypeDeclarationSQL(): Iterator
    {
        yield [[], 'NUMERIC(10, 0)'];
        yield [['unsigned' => true], 'NUMERIC(10, 0)'];
        yield [['unsigned' => false], 'NUMERIC(10, 0)'];
        yield [['precision' => 5], 'NUMERIC(5, 0)'];
        yield [['scale' => 5], 'NUMERIC(10, 5)'];
        yield [['precision' => 8, 'scale' => 2], 'NUMERIC(8, 2)'];
    }

    /**
     * @group DBAL-1082
     * @dataProvider getGeneratesFloatDeclarationSQL
     */
    public function testGeneratesFloatDeclarationSQL(array $column, $expectedSql): void
    {
        self::assertSame($expectedSql, $this->_platform->getFloatDeclarationSQL($column));
    }

    /** @return array */
    public function getGeneratesFloatDeclarationSQL(): Iterator
    {
        yield [[], 'DOUBLE PRECISION'];
        yield [['unsigned' => true], 'DOUBLE PRECISION'];
        yield [['unsigned' => false], 'DOUBLE PRECISION'];
        yield [['precision' => 5], 'DOUBLE PRECISION'];
        yield [['scale' => 5], 'DOUBLE PRECISION'];
        yield [['precision' => 8, 'scale' => 2], 'DOUBLE PRECISION'];
    }
}
