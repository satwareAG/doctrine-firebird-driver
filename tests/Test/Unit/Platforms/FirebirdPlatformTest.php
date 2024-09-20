<?php
namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Type;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\FirebirdKeywords;
use Test\Integration\AbstractIntegrationTestCase;

/**
 * Tests primarily functional aspects of the platform class. For SQL tests, see FirebirdPlatformSQLTest.
 **/

class FirebirdPlatformTest extends AbstractFirebirdPlatformTestCase
{


    public function testGetName()
    {
        $this->assertIsString($this->_platform->getName());
        if ($this->_platform instanceof Firebird3Platform) {
            $this->assertSame("Firebird3", $this->_platform->getName());
        } else {
            $this->assertSame("Firebird", $this->_platform->getName());
        }

    }

    /**
     * FROM: @link https://github.com/ISTDK/doctrine-dbal/blob/master/tests/Doctrine/Tests/DBAL/Platforms/AbstractPlatformTestCase.php
     */

    public function testGetMaxIdentifierLength()
    {
        $this->assertIsInt($this->_platform->getMaxIdentifierLength());
        $this->assertSame(31, $this->_platform->getMaxIdentifierLength());
    }

    public function testGetMaxConstraintIdentifierLength()
    {
        $this->assertIsInt($this->_platform->getMaxConstraintIdentifierLength());
        $this->assertSame(27, $this->_platform->getMaxConstraintIdentifierLength());
    }

    public function testCheckIdentifierLengthThrowsExceptionWhenArgumentNameIsTooLong()
    {
        $this->expectExceptionMessage("Operation 'Identifier kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk is too long for firebird platform. Maximum identifier length is 31' is not supported by platform");
        $this->expectException(Exception::class);
        $this->_platform->checkIdentifierLength(str_repeat("k", 32), null);
    }

    public function testQuoteSql()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('quoteSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        $this->assertIsString($found);
        $this->assertSame("'foo'", $found);
    }

    public function testQuoteIdentifier()
    {
        $c = $this->_platform->getIdentifierQuoteCharacter();
        $this->assertEquals($c."test".$c, $this->_platform->quoteIdentifier("test"));
        $this->assertEquals($c."test".$c.".".$c."test".$c, $this->_platform->quoteIdentifier("test.test"));
        $this->assertEquals(str_repeat((string) $c, 4), $this->_platform->quoteIdentifier($c));
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteSingleIdentifier()
    {
        $c = $this->_platform->getIdentifierQuoteCharacter();
        $this->assertEquals($c."test".$c, $this->_platform->quoteSingleIdentifier("test"));
        $this->assertEquals($c."test.test".$c, $this->_platform->quoteSingleIdentifier("test.test"));
        $this->assertEquals(str_repeat((string) $c, 4), $this->_platform->quoteSingleIdentifier($c));
    }

    public function testGetInvalidForeignKeyReferentialActionSQLThrowsException()
    {
        $this->expectException('InvalidArgumentException');
        $this->_platform->getForeignKeyReferentialActionSQL('unknown');
    }

    public function testGetUnknownDoctrineMappingTypeThrowsException()
    {
        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->_platform->getDoctrineTypeMapping('foobar');
    }

    public function testRegisterDoctrineMappingType()
    {
        $this->_platform->registerDoctrineTypeMapping('foo', 'integer');
        $this->assertEquals('integer', $this->_platform->getDoctrineTypeMapping('foo'));
    }

    public function testRegisterUnknownDoctrineMappingTypeThrowsException()
    {
        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->_platform->registerDoctrineTypeMapping('foo', 'bar');
    }

    public function testCreateWithNoColumnsThrowsException()
    {
        $table = new \Doctrine\DBAL\Schema\Table('test');
        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->_platform->getCreateTableSQL($table);
    }

    /**
     * @group DBAL-45
     */
    public function testKeywordList()
    {
        $keywordList = $this->_platform->getReservedKeywordsList();
        $this->assertInstanceOf(FirebirdKeywords::class, $keywordList);
        $this->assertInstanceOf(\Doctrine\DBAL\Platforms\Keywords\KeywordList::class, $keywordList);
        $this->assertTrue($keywordList->isKeyword('table'));
    }

    /**
     * CUSTOM
     */

    public function testGeneratePrimaryKeyConstraintName()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('generatePrimaryKeyConstraintName');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'id');
        $this->assertIsString($found);
        $this->assertSame("ID_PK", $found);
    }

    public function testSupportsForeignKeyConstraints()
    {
        $found = $this->_platform->supportsForeignKeyConstraints();
        $this->assertIsBool($found);
        $this->assertTrue($found);
    }

    public function testSupportsSequences()
    {
        $found = $this->_platform->supportsSequences();
        $this->assertIsBool($found);
        $this->assertTrue($found);
    }

    public function testUsesSequenceEmulatedIdentityColumns()
    {
        $found = $this->_platform->usesSequenceEmulatedIdentityColumns();
        $this->assertIsBool($found);
        if ($this->_platform instanceof Firebird3Platform) {
            $this->assertFalse($found);
        } else {
            $this->assertTrue($found);
        }

    }

    public function testGetIdentitySequenceName()
    {
        $found = $this->_platform->getIdentitySequenceName('foo', 'bar');
        $this->assertIsString($found);
        if ($this->_platform instanceof Firebird3Platform) {
            $this->assertSame("foo.bar", $found);
        } else {
            $this->assertSame("FOO_D2IS", $found);
        }

    }

    public function testGetIdentitySequenceTriggerName()
    {
        $found = $this->_platform->getIdentitySequenceTriggerName('foo', 'bar');
        $this->assertIsString($found);
        $this->assertSame("FOO_D2IT", $found);
    }

    public function testSupportsViews()
    {
        $found = $this->_platform->supportsViews();
        $this->assertIsBool($found);
        $this->assertTrue($found);
    }

    public function testSupportsSchemas()
    {
        $found = $this->_platform->supportsSchemas();
        $this->assertIsBool($found);
        $this->assertFalse($found);
    }

    public function testSupportsIdentityColumns()
    {
        $found = $this->_platform->supportsIdentityColumns();
        $this->assertIsBool($found);
        if($this->_platform instanceof Firebird3Platform) {
            $this->assertTrue($found);
        } else {
            $this->assertFalse($found);
        }
    }

    public function testSupportsInlineColumnComments()
    {
        $found = $this->_platform->supportsInlineColumnComments();
        $this->assertIsBool($found);
        $this->assertFalse($found);
    }

    public function testSupportsCommentOnStatement()
    {
        $found = $this->_platform->supportsCommentOnStatement();
        $this->assertIsBool($found);
        $this->assertTrue($found);
    }

    public function testSupportsCreateDropDatabase()
    {
        $found = $this->_platform->supportsCreateDropDatabase();
        $this->assertIsBool($found);
        $this->assertTrue($found);
    }

    public function testSupportsSavepoints()
    {
        $found = $this->_platform->supportsSavepoints();
        $this->assertIsBool($found);
        $this->assertTrue($found);
    }

    public function testSupportsLimitOffset()
    {
        $found = $this->_platform->supportsLimitOffset();
        $this->assertIsBool($found);
        $this->assertTrue($found);
    }


    public function testPrefersIdentityColumns()
    {
        $found = $this->_platform->prefersIdentityColumns();
        $this->assertIsBool($found);
        if($this->_platform instanceof Firebird3Platform) {
            $this->assertTrue($found);
        } else {
            $this->assertFalse($found);
        }

    }

    /**
     * @dataProvider dataProvider_testDoModifyLimitQuery
     */
    public function testDoModifyLimitQuery($expected, $query, $limit, $offset)
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('doModifyLimitQuery');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $query, $limit, $offset);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testDoModifyLimitQuery()
    {
        return [
            ["foo", "foo", null, null],
            ["foo ROWS 1 TO 3", "foo", 3, null],
            ["foo ROWS 4 TO " . PHP_INT_MAX, "foo", null, 3],
            ["foo ROWS 4 TO 6", "foo", 3, 3],
        ];
    }

    public function testGetListTablesSQL()
    {
        $found = $this->_platform->getListTablesSQL();
        $this->assertIsString($found);
    }

    public function testGetListViewsSQL()
    {
        $found = $this->_platform->getListViewsSQL('foo');
        $this->assertIsString($found);
    }

    /**
     * @dataProvider dataProvider_testMakeSimpleMetadataSelectExpression
     */
    public function testMakeSimpleMetadataSelectExpression($expected, $expressions)
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('makeSimpleMetadataSelectExpression');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $expressions);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testMakeSimpleMetadataSelectExpression()
    {
        return [
            ["(UPPER(foo) = UPPER('bar'))", ["foo" => "bar"]],
            ["(foo IS NULL)", ["foo" => null]],
            ["(foo = 42)", ["foo" => 42]],
        ];
    }

    public function testGetDummySelectSQL()
    {
        $found = $this->_platform->getDummySelectSQL('foo');
        $this->assertIsString($found);
    }

    /**
     * @dataProvider dataProvider_testGetExecuteBlockSql
     */
    public function testGetExecuteBlockSql($expected, $params)
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getExecuteBlockSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $params);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetExecuteBlockSql()
    {
        return [
            ["EXECUTE BLOCK AS\nBEGIN\nEND\n", []],
            ["EXECUTE BLOCK AS BEGIN END ", ['formatLineBreak' => false]],
            ["EXECUTE BLOCK (foo bar) \nAS\nBEGIN\nEND\n", ['blockParams' => ['foo' => 'bar']]],
            ["EXECUTE BLOCK AS\n  DECLARE foo bar; \nBEGIN\nEND\n", ['blockVars' => ['foo' => 'bar']]],
            ["EXECUTE BLOCK AS\nBEGIN\n  foo\n  bar\nEND\n", ['statements' => ['foo', 'bar']]],
        ];
    }

    /**
     * @dataProvider dataProvider_testGetExecuteBlockWithExecuteStatementsSql
     */
    public function testGetExecuteBlockWithExecuteStatementsSql($expected, $params)
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getExecuteBlockWithExecuteStatementsSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $params);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetExecuteBlockWithExecuteStatementsSql()
    {
        return [
            ["EXECUTE BLOCK AS\nBEGIN\nEND\n", []],
            ["EXECUTE BLOCK AS BEGIN END ", ['formatLineBreak' => false]],
            ["EXECUTE BLOCK (foo bar) \nAS\nBEGIN\nEND\n", ['blockParams' => ['foo' => 'bar']]],
            ["EXECUTE BLOCK AS\n  DECLARE foo bar; \nBEGIN\nEND\n", ['blockVars' => ['foo' => 'bar']]],
            ["EXECUTE BLOCK AS\nBEGIN\n  EXECUTE STATEMENT 'foo';\nEND\n", ['statements' => ['foo']]],
        ];
    }

    public function testGetDropAllViewsOfTablePSqlSnippet()
    {
        $found = $this->_platform->getDropAllViewsOfTablePSqlSnippet('foo');
        $this->assertIsString($found);
    }

    public function testGetCreateSequenceSQL()
    {
        $sequence = $this
            ->getMockBuilder(Sequence::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sequence
            ->expects($this->atLeast(2))
            ->method('getQuotedName')
            ->with($this->_platform)
            ->willReturn('foo');
        $found = $this->_platform->getCreateSequenceSQL($sequence);
        $this->assertIsString($found);
        $this->assertStringContainsString("CREATE SEQUENCE foo", $found);
    }

    public function testGetAlterSequenceSQL()
    {
        $sequence = $this
            ->getMockBuilder(Sequence::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sequence
            ->expects($this->atLeastOnce())
            ->method('getInitialValue')
            ->willReturn(3);
        $sequence
            ->expects($this->once())
            ->method('getQuotedName')
            ->with($this->_platform)
            ->willReturn('foo');
        $found = $this->_platform->getAlterSequenceSQL($sequence);
        $this->assertIsString($found);
        if ($this->_platform instanceof Firebird3Platform) {
            $this->assertSame('EXECUTE BLOCK AS
BEGIN
  EXECUTE STATEMENT \'ALTER SEQUENCE foo RESTART WITH 3 INCREMENT BY \';
  EXECUTE STATEMENT \'{"name":null,"initialValue":3,"allocationSize":null,"cache":null}\';
END
', $found);

        } else {
            $this->assertSame("ALTER SEQUENCE foo RESTART WITH 2", $found);
        }

    }

    public function testGetExecuteStatementPSql()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getExecuteStatementPSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        $this->assertIsString($found);
        $this->assertSame("EXECUTE STATEMENT 'foo'", $found);
    }

    public function testGetDropTriggerSql()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getDropTriggerSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        $this->assertIsString($found);
        $this->assertSame("DROP TRIGGER foo", $found);
    }

    /**
     * @dataProvider dataProvider_testGetDropTriggerIfExistsPSql
     */
    public function testGetDropTriggerIfExistsPSql(
        $expectedStartsWith,
        $expectedEndsWith,
        $aTrigger,
        $inBlock
    )
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getDropTriggerIfExistsPSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $aTrigger, $inBlock);
        $this->assertIsString($found);
        $this->assertStringStartsWith($expectedStartsWith, $found);
        $this->assertStringContainsString("IF (EXISTS (SELECT 1 FROM RDB\$TRIGGERS WHERE", $found);
        $this->assertStringEndsWith($expectedEndsWith, $found);
    }

    public function dataProvider_testGetDropTriggerIfExistsPSql()
    {
        return [
            [
                "IF (EXISTS (SELECT 1 FROM RDB\$TRIGGERS WHERE",
                "EXECUTE STATEMENT 'DROP TRIGGER foo'; END",
                'foo',
                false
            ],
            [
                "EXECUTE BLOCK AS BEGIN IF (EXISTS (SELECT 1 FROM RDB\$TRIGGERS WHERE",
                "EXECUTE STATEMENT 'DROP TRIGGER bar'; END END ",
                'bar',
                true
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testGetCombinedSqlStatements
     */
    public function testGetCombinedSqlStatements($expected, $sql, $aSeparator)
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getCombinedSqlStatements');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $sql, $aSeparator);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetCombinedSqlStatements()
    {
        return [
            ["foo;", 'foo', ';'],
            ["bar;baz;", ['bar', 'baz'], ';'],
        ];
    }

    public function testGetDropSequenceSQLWithNormalString()
    {
        $found = $this->_platform->getDropSequenceSQL('foo');
        $this->assertIsString($found);
        $this->assertSame('DROP SEQUENCE foo', $found);
    }

    public function testGetDropSequenceSQLWithD2IS()
    {
        $found = $this->_platform->getDropSequenceSQL('bar_D2IS');
        $this->assertIsString($found);
        $this->assertStringStartsWith('EXECUTE BLOCK AS', $found);
        $this->assertStringContainsString('RDB$TRIGGERS', $found);
        $this->assertStringContainsString('RDB$TRIGGER_NAME', $found);
        $this->assertStringContainsString('DROP TRIGGER bar_D2IT', $found);
        $this->assertStringContainsString('DROP SEQUENCE bar_D2IS', $found);
    }

    public function testGetDropSequenceSQLWithSequence()
    {
        $sequence = $this
            ->getMockBuilder(Sequence::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sequence
            ->expects($this->atMost(2))
            ->method('getQuotedName')
            ->willReturn('foo');
        $found = $this->_platform->getDropSequenceSQL($sequence);
        $this->assertIsString($found);
        $this->assertSame('DROP SEQUENCE foo', $found);
    }

    public function testGetDropForeignKeySQL()
    {
        $found = $this->_platform->getDropForeignKeySQL('foo', 'bar');
        $this->assertIsString($found);
        $this->assertSame('ALTER TABLE bar DROP CONSTRAINT foo', $found);
    }

    public function testGetSequenceNextValFunctionSQL()
    {
        $found = $this->_platform->getSequenceNextValFunctionSQL('foo');
        $this->assertIsString($found);
        $this->assertSame('NEXT VALUE FOR foo', $found);
    }

    public function testGetSequenceNextValSQL()
    {
        $found = $this->_platform->getSequenceNextValSQL('foo');
        $this->assertIsString($found);
        $this->assertSame('SELECT NEXT VALUE FOR foo FROM RDB$DATABASE', $found);
    }

    public function testGetSetTransactionIsolationSQLThrowsException()
    {
        $this->expectException(Exception::class);
        $this->_platform->getSetTransactionIsolationSQL(null);
    }

    public function testGetBooleanTypeDeclarationSQL()
    {
        $found = $this->_platform->getBooleanTypeDeclarationSQL([]);
        $this->assertIsString($found);
        if ($this->_platform instanceof Firebird3Platform) {
            $this->assertSame('BOOLEAN', $found);
        } else {
            $this->assertSame('SMALLINT', $found);
        }

    }

    public function testGetIntegerTypeDeclarationSQL()
    {
        $found = $this->_platform->getIntegerTypeDeclarationSQL([]);
        $this->assertIsString($found);
        $this->assertSame('INTEGER', $found);
    }

    public function testGetBigIntTypeDeclarationSQL()
    {
        $found = $this->_platform->getBigIntTypeDeclarationSQL([]);
        $this->assertIsString($found);
        $this->assertSame('BIGINT', $found);
    }

    /**
     * @dataProvider dataProvider_testGetTruncateTableSQL
     */
    public function testGetTruncateTableSQL($cascade)
    {
        $found = $this->_platform->getTruncateTableSQL('foo', $cascade);
        $this->assertIsString($found);
        $this->assertSame('DELETE FROM FOO', $found);
    }

    public function dataProvider_testGetTruncateTableSQL()
    {
        return [
            [false],
            [true],
        ];
    }

    public function testGetDateTimeFormatString()
    {
        $found = $this->_platform->getDateTimeFormatString();
        $this->assertIsString($found);
    }

    public function testGetAlterTableSQLWorksWithNoChanges()
    {
        $diff = $this
            ->getMockBuilder(TableDiff::class)
            ->disableOriginalConstructor()
            ->getMock();
        $name = $this
            ->getMockBuilder(Identifier::class)
            ->disableOriginalConstructor()
            ->getMock();
        $diff
            ->expects($this->any())
            ->method('getName')
            ->willReturn($name);
        $diff
            ->expects($this->any())
            ->method('getNewName')
            ->willReturn(false);
        $name
            ->expects($this->any())
            ->method('getQuotedName')
            ->willReturn("'foo'");
        $diff->addedColumns = [];
        $diff->removedColumns = [];
        $diff->changedColumns = [];
        $diff->renamedColumns = [];
        $found = $this->_platform->getAlterTableSQL($diff);
        $this->assertIsArray($found);
        $this->assertSame([], $found);
    }

    public function testGetAlterTableSQLWorksWithAddedColumn()
    {
        $table = new Table("'foo'");
        $table2 = clone $table;
        $table2->dropColumn("'bar'");
        Type::addType('baz', IntegerType::class);
        $table2->addColumn("'bar'", 'baz', ['default' => false]);

        $diff = $this->connection->createSchemaManager()->createComparator()->compareTables($table, $table2);
        $found = $this->_platform->getAlterTableSQL($diff);
        $this->assertIsArray($found);
        $this->assertCount(1, $found);
        $this->assertSame([0 => "ALTER TABLE 'foo' ADD 'bar' INTEGER DEFAULT  NOT NULL"], $found);
    }

    public function testGetAlterTableSQLWorksWithRemovedColumn()
    {

        $table = new Table("'foo'");
        $table->addColumn("'bar'", 'bigint');

        $table2 = clone ($table);
        $table2->dropColumn("'bar'");

        $sm = $this->connection->createSchemaManager();
        $comparator = $sm->createComparator();

        $diff = $comparator->compareTables($table, $table2);

        $found = $this->_platform->getAlterTableSQL($diff);
        $this->assertIsArray($found);
        $this->assertCount(1, $found);
        $this->assertSame([0 => "ALTER TABLE 'foo' DROP 'bar'"], $found);
    }

    public function testGetAlterTableSQLWorksWithChangedColumn()
    {
        $table = new Table("foo");
        $table->addColumn("bar", 'string', ['notnull' => true, 'length' => 255, 'default' => 'myDefault']);

        $table2 = clone $table;

        $table2->dropColumn("bar");
        $table2->addColumn("bar", 'bigint', ['notnull' => false, 'autoincrement' => true, 'default' => null, 'comment' => '']);

        $diff = $this->connection->createSchemaManager()->createComparator()->compareTables($table, $table2);
        $found = $this->connection->getDatabasePlatform()->getAlterTableSQL($diff);
        $this->assertIsArray($found);
        if($this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            $this->assertCount(8, $found);
            $expected = [
                0 => 'ALTER TABLE foo ALTER COLUMN bar TYPE BIGINT',
                1 => 'ALTER TABLE foo ALTER bar DROP DEFAULT',
                2 => 'ALTER TABLE foo ALTER bar DROP NOT NULL',
                3 => 'ALTER TABLE foo ADD tmp_bar BIGINT GENERATED BY DEFAULT AS IDENTITY DEFAULT NULL',
                4 => 'UPDATE foo SET tmp_bar=bar )',
                5 => 'ALTER TABLE foo DROP bar',
                6 => 'ALTER TABLE foo ALTER COLUMN tmp_bar TO bar',
                7 => "COMMENT ON COLUMN foo.bar IS ''",
            ];

        } else {
            $this->assertCount(7, $found);
            $expected = [
                0 => 'ALTER TABLE foo ALTER COLUMN bar TYPE BIGINT',
                1 => 'ALTER TABLE foo ALTER bar DROP DEFAULT',
                2 => "UPDATE RDB\$RELATION_FIELDS SET RDB\$NULL_FLAG = NULL WHERE UPPER(RDB\$FIELD_NAME) = UPPER('bar') AND UPPER(RDB\$RELATION_NAME) = UPPER('foo')",
                3 => 'CREATE SEQUENCE FOO_D2IS',
                4 => "SELECT setval('FOO_D2IS', (SELECT MAX(bar) FROM foo))",
                5 => "ALTER TABLE foo ALTER bar SET DEFAULT nextval('FOO_D2IS')",
                6 => "COMMENT ON COLUMN foo.bar IS ''",
            ];
        }



        $this->assertSame($expected, $found);
    }

    public function testGetAlterTableSQLWorksWithRenamedColumn()
    {

        $sm = $this->connection->createSchemaManager();

        $table = new Table("'foo'");
        $table->addColumn("0", 'bigint');

        $table2 = clone $table;
        $table2->dropColumn("0");
        $table2->addColumn("'bar'", 'bigint');
        $diff = $sm->createComparator()->compareTables($table, $table2);
        $found = $this->_platform->getAlterTableSQL($diff);
        $this->assertIsArray($found);
        $this->assertCount(1, $found);
        $this->assertSame([0 => "ALTER TABLE 'foo' ALTER COLUMN 0 TO 'bar'"], $found);
    }

    public function testGetVarcharMaxLength()
    {
        $found = $this->_platform->getVarcharMaxLength();
        $this->assertIsInt($found);
        $this->assertGreaterThan(0, $found);
    }

    public function testGetBinaryMaxLength()
    {
        $found = $this->_platform->getBinaryMaxLength();
        $this->assertIsInt($found);
        $this->assertGreaterThan(0, $found);
    }

    public function testGetReservedKeywordsClass()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getReservedKeywordsClass');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform);
        $this->assertIsString($found);
        $this->assertTrue(class_exists($found));
        $this->assertTrue(is_subclass_of($found, \Doctrine\DBAL\Platforms\Keywords\KeywordList::class));
    }

    public function testGetSmallIntTypeDeclarationSQL()
    {
        $found = $this->_platform->getSmallIntTypeDeclarationSQL([]);
        $this->assertIsString($found);
        $this->assertSame("SMALLINT", $found);
    }

    public function testGetCommonIntegerTypeDeclarationSQL()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('_getCommonIntegerTypeDeclarationSQL');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, []);
        $this->assertIsString($found);
        $this->assertSame("", $found);
    }

    /**
     * @dataProvider dataProvider_testGetClobTypeDeclarationSQL
     */
    public function testGetClobTypeDeclarationSQL($expected, $field)
    {
        $found = $this->_platform->getClobTypeDeclarationSQL($field);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetClobTypeDeclarationSQL()
    {
        return [
            ["BLOB SUB_TYPE TEXT", []],
            ["VARCHAR(255)", ['length' => 255]],
        ];
    }

    public function testGetBlobTypeDeclarationSQL()
    {
        $found = $this->_platform->getBlobTypeDeclarationSQL([]);
        $this->assertIsString($found);
        $this->assertSame("BLOB", $found);
    }

    public function testGetDateTimeTypeDeclarationSQL()
    {
        $found = $this->_platform->getDateTimeTypeDeclarationSQL([]);
        $this->assertIsString($found);
        $this->assertSame("TIMESTAMP", $found);
    }

    public function testGetTimeTypeDeclarationSQL()
    {
        $found = $this->_platform->getTimeTypeDeclarationSQL([]);
        $this->assertIsString($found);
        $this->assertSame("TIME", $found);
    }

    public function testGetDateTypeDeclarationSQL()
    {
        $found = $this->_platform->getDateTypeDeclarationSQL([]);
        $this->assertIsString($found);
        $this->assertSame("DATE", $found);
    }

    /**
     * @dataProvider dataProvider_testGetVarcharTypeDeclarationSQLSnippet
     */
    public function testGetVarcharTypeDeclarationSQLSnippet($expected, $length, $fixed)
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getVarcharTypeDeclarationSQLSnippet');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $length, $fixed);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetVarcharTypeDeclarationSQLSnippet()
    {
        return [
            ["CHAR(32)", 32, true],
            ["CHAR(255)", 0, true],
            ["VARCHAR(32)", 32, false],
            ["VARCHAR(255)", 0, false],
        ];
    }

    /**
     * @dataProvider dataProvider_testGetColumnCharsetDeclarationSQL
     */
    public function testGetColumnCharsetDeclarationSQL($expected, $charset)
    {
        $found = $this->_platform->getColumnCharsetDeclarationSQL($charset);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetColumnCharsetDeclarationSQL()
    {
        return [
            ["", ""],
            [" CHARACTER SET foo", "foo"],
        ];
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        $found = $this->_platform->getBinaryTypeDeclarationSQL([]);
        $this->assertSame("VARCHAR(255)", $found);
    }

    /**
     * @dataProvider dataProvider_testGetBinaryTypeDeclarationSQLSnippet
     */
    public function testGetBinaryTypeDeclarationSQLSnippet($expected, $length, $fixed)
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getBinaryTypeDeclarationSQLSnippet');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $length, $fixed);
        $this->assertIsString($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetBinaryTypeDeclarationSQLSnippet()
    {
        return [
            ["CHAR(32)", 32, true],
            ["CHAR(8191)", 0, true],
            ["VARCHAR(32)", 32, false],
            ["VARCHAR(8191)", 0, false],
            ["VARCHAR(8190)", 8190, false],
            ["BLOB", 8192, false],
        ];
    }

    public function testGetColumnDeclarationSQL()
    {

        $type = $this
            ->getMockBuilder(\Doctrine\DBAL\Types\StringType::class)
            ->disableOriginalConstructor()
            ->getMock();
        $type
            ->expects($this->any())
            ->method('getSQLDeclaration')
            ->willReturn("baz");
        $type
            ->expects($this->any())
            ->method('getName')
            ->willReturn("binary");
        $found = $this->_platform->getColumnDeclarationSQL("foo", ['type' => $type]);
        $this->assertIsString($found);
        $this->assertSame("foo baz  CHARACTER SET octets DEFAULT NULL", $found);
    }

    public function testGetCreateTemporaryTableSnippetSQL()
    {
        $found = $this->_platform->getCreateTemporaryTableSnippetSQL();
        $this->assertIsString($found);
        $this->assertSame("CREATE GLOBAL TEMPORARY TABLE", $found);
    }

    public function testGetTemporaryTableSQLgetTemporaryTableSQL()
    {
        $found = $this->_platform->getTemporaryTableSQL();
        $this->assertIsString($found);
        $this->assertSame("GLOBAL TEMPORARY", $found);
    }

    /**
     * @dataProvider dataProvider_testGetCreateTableSQL
     */
    public function testGetCreateTableSQL($expected, $options)
    {
        $type = $this
            ->getMockBuilder(\Doctrine\DBAL\Types\StringType::class)
            ->disableOriginalConstructor()
            ->getMock();
        $type
            ->expects($this->any())
            ->method('getSQLDeclaration')
            ->willReturn("baz");
        $columns = [
            0 => ['type' => $type],
        ];
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('_getCreateTableSQL');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo', $columns, $options);
        $this->assertIsArray($found);
        $this->assertSame($expected, $found);
    }

    public function dataProvider_testGetCreateTableSQL()
    {
        return [
            [
                [
                    0 => "CREATE TABLE foo (0 baz DEFAULT NULL)",
                ],
                []
            ],
            [
                [
                    0 => "CREATE GLOBAL TEMPORARY TABLE foo (0 baz DEFAULT NULL) ON COMMIT PRESERVE ROWS",
                ],
                ['temporary' => true]
            ],
        ];
    }

    public function testGetListSequencesSQL()
    {
        $found = $this->_platform->getListSequencesSQL('');
        $this->assertIsString($found);
        $this->assertStringStartsWith("select trim(rdb\$generator_name)", $found);
    }

    public function testGetListTableColumnsSQL()
    {
        $found = $this->_platform->getListTableColumnsSQL('foo');
        $this->assertIsString($found);
        $this->assertStringStartsWith("SELECT TRIM(r.RDB\$FIELD_NAME) AS \"FIELD_NAME\",\n", $found);
        $this->assertStringContainsString("FROM RDB\$RELATION_FIELDS r", $found);

    }

    public function testGetListTableForeignKeysSQL()
    {
        $found = $this->_platform->getListTableForeignKeysSQL('foo');
        $this->assertIsString($found);
        $foundNormalized = preg_replace('/\r\n|\r/', "\n", ltrim($found));
        $this->assertStringStartsWith("SELECT TRIM(rc.RDB\$CONSTRAINT_NAME) AS constraint_name,\n", $foundNormalized);
        $this->assertStringContainsString(" FROM RDB\$INDEX_SEGMENTS s\n", $foundNormalized);
    }

    public function testGetListTableIndexesSQL()
    {
        $found = $this->_platform->getListTableIndexesSQL('foo');
        $this->assertIsString($found);
        $foundNormalized = preg_replace('/\r\n|\r/', "\n", ltrim($found));
        $this->assertStringStartsWith("SELECT\n", $foundNormalized);
        $this->assertStringContainsString("FROM RDB\$INDEX_SEGMENTS\n", $foundNormalized);
    }

    public function testGetSQLResultCasing()
    {
        $found = $this->_platform->getSQLResultCasing('foo');
        $this->assertIsString($found);
        $this->assertStringStartsWith("FOO", $found);
    }

    public function testUnquotedIdentifierName()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('unquotedIdentifierName');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        $this->assertIsString($found);
        $this->assertSame("foo", $found);
    }

    public function testGetQuotedNameOf()
    {
        $reflection = new \ReflectionObject($this->_platform);
        $method = $reflection->getMethod('getQuotedNameOf');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        $this->assertIsString($found);
        $this->assertSame("foo", $found);
    }

    public function testGetCreateDatabaseSQL()
    {
        $sql = $this->_platform->getCreateDatabaseSQL('foo');
        $this->assertIsString($sql);
            $this->assertStringStartsWith("CREATE DATABASE", $sql);
            $this->assertStringEndsWith("foo", $sql);
    }
    /**
     * @group DBAL-553
     */
    public function testHasNativeJsonType()
    {
        $this->assertFalse($this->_platform->hasNativeJsonType());
    }

    /**
     * @group DBAL-553
     */
    public function testReturnsJsonTypeDeclarationSQL()
    {
        $column = ['length'  => 666, 'notnull' => true, 'type'    => \Doctrine\DBAL\Types\Type::getType('json')];
        $this->assertSame(
            $this->_platform->getClobTypeDeclarationSQL($column),
            $this->_platform->getJsonTypeDeclarationSQL($column)
        );
    }

    public function testGetStringLiteralQuoteCharacter()
    {
        $this->assertSame("'", $this->_platform->getStringLiteralQuoteCharacter());
    }
}
