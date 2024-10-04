<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Iterator;
use ReflectionObject;
use Satag\DoctrineFirebirdDriver\ORM\Mapping\FirebirdQuoteStrategy;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\FirebirdKeywords;

use function class_exists;
use function is_subclass_of;
use function ltrim;
use function preg_replace;
use function str_repeat;

use const PHP_INT_MAX;

/**
 * Tests primarily functional aspects of the platform class. For SQL tests, see FirebirdPlatformSQLTest.
 **/

class FirebirdPlatformTest extends AbstractFirebirdPlatformTestCase
{
    public function testGetName(): void
    {
        self::assertIsString($this->_platform->getName());
        if ($this->_platform instanceof Firebird5Platform) {
            self::assertSame('Firebird5', $this->_platform->getName());
        } elseif ($this->_platform instanceof Firebird4Platform) {
            self::assertSame('Firebird4', $this->_platform->getName());
        } elseif ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('Firebird3', $this->_platform->getName());
        } else {
            self::assertSame('Firebird', $this->_platform->getName());
        }
    }

    /**
     * FROM: @link https://github.com/ISTDK/doctrine-dbal/blob/master/tests/Doctrine/Tests/DBAL/Platforms/AbstractPlatformTestCase.php
     */
    public function testGetMaxIdentifierLength(): void
    {
        self::assertIsInt($this->_platform->getMaxIdentifierLength());
        self::assertSame(31, $this->_platform->getMaxIdentifierLength());
    }

    public function testGetMaxConstraintIdentifierLength(): void
    {
        self::assertIsInt($this->_platform->getMaxConstraintIdentifierLength());
        self::assertSame(27, $this->_platform->getMaxConstraintIdentifierLength());
    }

    public function testCheckIdentifierLengthThrowsExceptionWhenArgumentNameIsTooLong(): void
    {
        $this->expectExceptionMessage("Operation 'Identifier kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk is too long for firebird platform. Maximum identifier length is 31' is not supported by platform");
        $this->expectException(Exception::class);
        $this->_platform->checkIdentifierLength(str_repeat('k', 32), null);
    }

    public function testQuoteSql(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('quoteSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        self::assertIsString($found);
        self::assertSame("'foo'", $found);
    }

    public function testQuoteIdentifier(): void
    {
        $c = $this->_platform->getIdentifierQuoteCharacter();
        self::assertSame($c . 'test' . $c, $this->_platform->quoteIdentifier('test'));
        self::assertSame($c . 'test' . $c . '.' . $c . 'test' . $c, $this->_platform->quoteIdentifier('test.test'));
        self::assertSame(str_repeat((string) $c, 4), $this->_platform->quoteIdentifier($c));
    }

    #[\PHPUnit\Framework\Attributes\Group('DDC-1360')]
    public function testQuoteSingleIdentifier(): void
    {
        $c = $this->_platform->getIdentifierQuoteCharacter();
        self::assertSame($c . 'test' . $c, $this->_platform->quoteSingleIdentifier('test'));
        self::assertSame($c . 'test.test' . $c, $this->_platform->quoteSingleIdentifier('test.test'));
        self::assertSame(str_repeat((string) $c, 4), $this->_platform->quoteSingleIdentifier($c));
    }

    public function testGetInvalidForeignKeyReferentialActionSQLThrowsException(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->_platform->getForeignKeyReferentialActionSQL('unknown');
    }

    public function testGetUnknownDoctrineMappingTypeThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->_platform->getDoctrineTypeMapping('foobar');
    }

    public function testRegisterDoctrineMappingType(): void
    {
        $this->_platform->registerDoctrineTypeMapping('foo', 'integer');
        self::assertSame('integer', $this->_platform->getDoctrineTypeMapping('foo'));
    }

    public function testRegisterUnknownDoctrineMappingTypeThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->_platform->registerDoctrineTypeMapping('foo', 'bar');
    }

    public function testCreateWithNoColumnsThrowsException(): void
    {
        $table = new Table('test');
        $this->expectException(Exception::class);
        $this->_platform->getCreateTableSQL($table);
    }

    #[\PHPUnit\Framework\Attributes\Group('DBAL-45')]
    public function testKeywordList(): void
    {
        $keywordList = $this->_platform->getReservedKeywordsList();
        self::assertInstanceOf(FirebirdKeywords::class, $keywordList);
        self::assertInstanceOf(KeywordList::class, $keywordList);
        self::assertTrue($keywordList->isKeyword('table'));
    }

    /**
     * CUSTOM
     */
    public function testGeneratePrimaryKeyConstraintName(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('generatePrimaryKeyConstraintName');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'id');
        self::assertIsString($found);
        self::assertSame('ID_PK', $found);
    }

    public function testSupportsForeignKeyConstraints(): void
    {
        $found = $this->_platform->supportsForeignKeyConstraints();
        self::assertIsBool($found);
        self::assertTrue($found);
    }

    public function testSupportsSequences(): void
    {
        $found = $this->_platform->supportsSequences();
        self::assertIsBool($found);
        self::assertTrue($found);
    }

    public function testUsesSequenceEmulatedIdentityColumns(): void
    {
        $found = $this->_platform->usesSequenceEmulatedIdentityColumns();
        self::assertIsBool($found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertFalse($found);
        } else {
            self::assertTrue($found);
        }
    }

    public function testGetIdentitySequenceName(): void
    {
        $found = $this->_platform->getIdentitySequenceName('foo', 'bar');
        self::assertIsString($found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('FOO.BAR', $found);
        } else {
            self::assertSame('FOO_D2IS', $found);
        }
    }

    public function testGetIdentitySequenceTriggerName(): void
    {
        $found = $this->_platform->getIdentitySequenceTriggerName('foo', 'bar');
        self::assertIsString($found);
        self::assertSame('FOO_D2IT', $found);
    }

    public function testSupportsViews(): void
    {
        $found = $this->_platform->supportsViews();
        self::assertIsBool($found);
        self::assertTrue($found);
    }

    public function testSupportsSchemas(): void
    {
        $found = $this->_platform->supportsSchemas();
        self::assertIsBool($found);
        self::assertFalse($found);
    }

    public function testSupportsIdentityColumns(): void
    {
        $found = $this->_platform->supportsIdentityColumns();
        self::assertIsBool($found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertTrue($found);
        } else {
            self::assertFalse($found);
        }
    }

    public function testSupportsInlineColumnComments(): void
    {
        $found = $this->_platform->supportsInlineColumnComments();
        self::assertIsBool($found);
        self::assertFalse($found);
    }

    public function testSupportsCommentOnStatement(): void
    {
        $found = $this->_platform->supportsCommentOnStatement();
        self::assertIsBool($found);
        self::assertTrue($found);
    }

    public function testSupportsCreateDropDatabase(): void
    {
        $found = $this->_platform->supportsCreateDropDatabase();
        self::assertIsBool($found);
        self::assertTrue($found);
    }

    public function testSupportsSavepoints(): void
    {
        $found = $this->_platform->supportsSavepoints();
        self::assertIsBool($found);
        self::assertTrue($found);
    }

    public function testSupportsLimitOffset(): void
    {
        $found = $this->_platform->supportsLimitOffset();
        self::assertIsBool($found);
        self::assertTrue($found);
    }

    public function testPrefersIdentityColumns(): void
    {
        $found = $this->_platform->prefersIdentityColumns();
        self::assertIsBool($found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertTrue($found);
        } else {
            self::assertFalse($found);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testDoModifyLimitQuery')]
    public function testDoModifyLimitQuery($expected, $query, $limit, $offset): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('doModifyLimitQuery');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $query, $limit, $offset);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testDoModifyLimitQuery(): Iterator
    {
        yield ['foo', 'foo', null, null];
        yield ['foo ROWS 1 TO 3', 'foo', 3, null];
        yield ['foo ROWS 4 TO ' . PHP_INT_MAX, 'foo', null, 3];
        yield ['foo ROWS 4 TO 6', 'foo', 3, 3];
    }

    public function testGetListTablesSQL(): void
    {
        $found = $this->_platform->getListTablesSQL();
        self::assertIsString($found);
    }

    public function testGetListViewsSQL(): void
    {
        $found = $this->_platform->getListViewsSQL('foo');
        self::assertIsString($found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testMakeSimpleMetadataSelectExpression')]
    public function testMakeSimpleMetadataSelectExpression($expected, $expressions): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('makeSimpleMetadataSelectExpression');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $expressions);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testMakeSimpleMetadataSelectExpression(): Iterator
    {
        yield ["(UPPER(foo) = UPPER('bar'))", ['foo' => 'bar']];
        yield ['(foo IS NULL)', ['foo' => null]];
        yield ['(foo = 42)', ['foo' => 42]];
    }

    public function testGetDummySelectSQL(): void
    {
        $found = $this->_platform->getDummySelectSQL('foo');
        self::assertIsString($found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetExecuteBlockSql')]
    public function testGetExecuteBlockSql($expected, $params): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getExecuteBlockSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $params);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetExecuteBlockSql(): Iterator
    {
        yield ["EXECUTE BLOCK AS\nBEGIN\nEND\n", []];
        yield ['EXECUTE BLOCK AS BEGIN END ', ['formatLineBreak' => false]];
        yield ["EXECUTE BLOCK (foo bar) \nAS\nBEGIN\nEND\n", ['blockParams' => ['foo' => 'bar']]];
        yield ["EXECUTE BLOCK AS\n  DECLARE foo bar; \nBEGIN\nEND\n", ['blockVars' => ['foo' => 'bar']]];
        yield ["EXECUTE BLOCK AS\nBEGIN\n  foo\n  bar\nEND\n", ['statements' => ['foo', 'bar']]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetExecuteBlockWithExecuteStatementsSql')]
    public function testGetExecuteBlockWithExecuteStatementsSql($expected, $params): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getExecuteBlockWithExecuteStatementsSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $params);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetExecuteBlockWithExecuteStatementsSql(): Iterator
    {
        yield ["EXECUTE BLOCK AS\nBEGIN\nEND\n", []];
        yield ['EXECUTE BLOCK AS BEGIN END ', ['formatLineBreak' => false]];
        yield ["EXECUTE BLOCK (foo bar) \nAS\nBEGIN\nEND\n", ['blockParams' => ['foo' => 'bar']]];
        yield ["EXECUTE BLOCK AS\n  DECLARE foo bar; \nBEGIN\nEND\n", ['blockVars' => ['foo' => 'bar']]];
        yield ["EXECUTE BLOCK AS\nBEGIN\n  EXECUTE STATEMENT 'foo';\nEND\n", ['statements' => ['foo']]];
    }

    public function testGetDropAllViewsOfTablePSqlSnippet(): void
    {
        $found = $this->_platform->getDropAllViewsOfTablePSqlSnippet('foo');
        self::assertIsString($found);
    }

    public function testGetCreateSequenceSQL(): void
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
        self::assertIsString($found);
        self::assertStringContainsString('CREATE SEQUENCE foo', $found);
    }

    public function testGetAlterSequenceSQL(): void
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
        self::assertIsString($found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('EXECUTE BLOCK AS
BEGIN
  EXECUTE STATEMENT \'ALTER SEQUENCE foo RESTART WITH 3 INCREMENT BY \';
  EXECUTE STATEMENT \'{"name":null,"initialValue":3,"allocationSize":null,"cache":null}\';
END
', $found);
        } else {
            self::assertSame('ALTER SEQUENCE foo RESTART WITH 2', $found);
        }
    }

    public function testGetExecuteStatementPSql(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getExecuteStatementPSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        self::assertIsString($found);
        self::assertSame("EXECUTE STATEMENT 'foo'", $found);
    }

    public function testGetDropTriggerSql(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getDropTriggerSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        self::assertIsString($found);
        self::assertSame('DROP TRIGGER foo', $found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetDropTriggerIfExistsPSql')]
    public function testGetDropTriggerIfExistsPSql(
        $expectedStartsWith,
        $expectedEndsWith,
        $aTrigger,
        $inBlock,
    ): void {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getDropTriggerIfExistsPSql');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $aTrigger, $inBlock);
        self::assertIsString($found);
        self::assertStringStartsWith($expectedStartsWith, $found);
        self::assertStringContainsString('IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE', $found);
        self::assertStringEndsWith($expectedEndsWith, $found);
    }

    public static function dataProvider_testGetDropTriggerIfExistsPSql(): Iterator
    {
        yield [
            'IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE',
            "EXECUTE STATEMENT 'DROP TRIGGER foo'; END",
            'foo',
            false,
        ];

        yield [
            'EXECUTE BLOCK AS BEGIN IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE',
            "EXECUTE STATEMENT 'DROP TRIGGER bar'; END END ",
            'bar',
            true,
        ];
    }

    public function testGetDropSequenceSQLWithNormalString(): void
    {
        $found = $this->_platform->getDropSequenceSQL('foo');
        self::assertIsString($found);
        self::assertSame('DROP SEQUENCE foo', $found);
    }

    public function testGetDropSequenceSQLWithD2IS(): void
    {
        $found = $this->_platform->getDropSequenceSQL('bar_D2IS');
        self::assertIsString($found);
        self::assertStringStartsWith('EXECUTE BLOCK AS', $found);
        self::assertStringContainsString('RDB$TRIGGERS', $found);
        self::assertStringContainsString('RDB$TRIGGER_NAME', $found);
        self::assertStringContainsString('DROP TRIGGER bar_D2IT', $found);
        self::assertStringContainsString('DROP SEQUENCE bar_D2IS', $found);
    }

    public function testGetDropSequenceSQLWithSequence(): void
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
        self::assertIsString($found);
        self::assertSame('DROP SEQUENCE foo', $found);
    }

    public function testGetDropForeignKeySQL(): void
    {
        $found = $this->_platform->getDropForeignKeySQL('foo', 'bar');
        self::assertIsString($found);
        self::assertSame('ALTER TABLE bar DROP CONSTRAINT foo', $found);
    }

    public function testGetSequenceNextValFunctionSQL(): void
    {
        $found = $this->_platform->getSequenceNextValFunctionSQL('foo');
        self::assertIsString($found);
        self::assertSame('NEXT VALUE FOR foo', $found);
    }

    public function testGetSequenceNextValSQL(): void
    {
        $found = $this->_platform->getSequenceNextValSQL('foo');
        self::assertIsString($found);
        self::assertSame('SELECT NEXT VALUE FOR foo FROM RDB$DATABASE', $found);
    }

    public function testGetSetTransactionIsolationSQLThrowsException(): void
    {
        $this->expectException(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class);
        $this->_platform->getSetTransactionIsolationSQL(null);
    }

    public function testGetBooleanTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getBooleanTypeDeclarationSQL([]);
        self::assertIsString($found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('BOOLEAN', $found);
        } else {
            self::assertSame('SMALLINT', $found);
        }
    }

    public function testGetIntegerTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getIntegerTypeDeclarationSQL([]);
        self::assertIsString($found);
        self::assertSame('INTEGER', $found);
    }

    public function testGetBigIntTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getBigIntTypeDeclarationSQL([]);
        self::assertIsString($found);
        self::assertSame('BIGINT', $found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetTruncateTableSQL')]
    public function testGetTruncateTableSQL($cascade): void
    {
        $found = $this->_platform->getTruncateTableSQL('foo', $cascade);
        self::assertIsString($found);
        self::assertSame('DELETE FROM FOO', $found);
    }

    public static function dataProvider_testGetTruncateTableSQL(): Iterator
    {
        yield [false];
        yield [true];
    }

    public function testGetDateTimeFormatString(): void
    {
        $found = $this->_platform->getDateTimeFormatString();
        self::assertIsString($found);
    }

    public function testGetAlterTableSQLWorksWithNoChanges(): void
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
            ->method('getName')
            ->willReturn($name);
        $diff
            ->method('getNewName')
            ->willReturn(false);
        $name
            ->method('getQuotedName')
            ->willReturn("'foo'");
        $diff->addedColumns   = [];
        $diff->removedColumns = [];
        $diff->changedColumns = [];
        $diff->renamedColumns = [];
        $found                = $this->_platform->getAlterTableSQL($diff);
        self::assertIsArray($found);
        self::assertSame([], $found);
    }

    public function testGetAlterTableSQLWorksWithAddedColumn(): void
    {
        $table  = new Table("'foo'");
        $table2 = clone $table;
        $table2->dropColumn("'bar'");
        Type::addType('baz', IntegerType::class);
        $table2->addColumn("'bar'", 'baz', ['default' => false]);

        $diff  = $this->connection->createSchemaManager()->createComparator()->compareTables($table, $table2);
        $found = $this->_platform->getAlterTableSQL($diff);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertSame([0 => "ALTER TABLE 'foo' ADD 'bar' INTEGER DEFAULT  NOT NULL"], $found);
    }

    public function testGetAlterTableSQLWorksWithRemovedColumn(): void
    {
        $table = new Table("'foo'");
        $table->addColumn("'bar'", 'bigint');

        $table2 = clone $table;
        $table2->dropColumn("'bar'");

        $sm         = $this->connection->createSchemaManager();
        $comparator = $sm->createComparator();

        $diff = $comparator->compareTables($table, $table2);

        $found = $this->_platform->getAlterTableSQL($diff);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertSame([0 => "ALTER TABLE 'foo' DROP 'bar'"], $found);
    }

    public function testGetAlterTableSQLWorksWithChangedColumn(): void
    {
        $table = new Table('foo');
        $table->addColumn('bar', 'string', ['notnull' => true, 'length' => 255, 'default' => 'myDefault']);

        $table2 = clone $table;

        $table2->dropColumn('bar');
        $table2->addColumn('bar', 'bigint', ['notnull' => false, 'autoincrement' => true, 'default' => null, 'comment' => '']);

        $diff  = $this->connection->createSchemaManager()->createComparator()->compareTables($table, $table2);
        $found = $this->connection->getDatabasePlatform()->getAlterTableSQL($diff);
        self::assertIsArray($found);
        if ($this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            self::assertCount(8, $found);
            $expected = [
                0 => 'ALTER TABLE foo ALTER COLUMN bar TYPE BIGINT',
                1 => 'ALTER TABLE foo ALTER bar DROP DEFAULT',
                2 => 'ALTER TABLE foo ALTER bar DROP NOT NULL',
                3 => 'ALTER TABLE foo ADD TMP_bar BIGINT GENERATED BY DEFAULT AS IDENTITY DEFAULT NULL',
                4 => 'UPDATE foo SET TMP_bar=bar )',
                5 => 'ALTER TABLE foo DROP bar',
                6 => 'ALTER TABLE foo ALTER COLUMN TMP_bar TO bar',
                7 => "COMMENT ON COLUMN foo.bar IS ''",
            ];
        } else {
            self::assertCount(7, $found);
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

        self::assertSame($expected, $found);
    }

    public function testGetAlterTableSQLWorksWithRenamedColumn(): void
    {
        $sm = $this->connection->createSchemaManager();

        $table = new Table("'foo'");
        $table->addColumn('0', 'bigint');

        $table2 = clone $table;
        $table2->dropColumn('0');
        $table2->addColumn("'bar'", 'bigint');
        $diff  = $sm->createComparator()->compareTables($table, $table2);
        $found = $this->_platform->getAlterTableSQL($diff);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertSame([0 => "ALTER TABLE 'foo' ALTER COLUMN 0 TO 'bar'"], $found);
    }

    public function testGetVarcharMaxLength(): void
    {
        $found = $this->_platform->getVarcharMaxLength();
        self::assertIsInt($found);
        self::assertGreaterThan(0, $found);
    }

    public function testGetBinaryMaxLength(): void
    {
        $found = $this->_platform->getBinaryMaxLength();
        self::assertIsInt($found);
        self::assertGreaterThan(0, $found);
    }

    public function testGetReservedKeywordsClass(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getReservedKeywordsClass');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform);
        self::assertIsString($found);
        self::assertTrue(class_exists($found));
        self::assertTrue(is_subclass_of($found, KeywordList::class));
    }

    public function testGetSmallIntTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getSmallIntTypeDeclarationSQL([]);
        self::assertIsString($found);
        self::assertSame('SMALLINT', $found);
    }

    public function testGetCommonIntegerTypeDeclarationSQL(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('_getCommonIntegerTypeDeclarationSQL');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, []);
        self::assertIsString($found);
        self::assertSame('', $found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetClobTypeDeclarationSQL')]
    public function testGetClobTypeDeclarationSQL($expected, $field): void
    {
        $found = $this->_platform->getClobTypeDeclarationSQL($field);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetClobTypeDeclarationSQL(): Iterator
    {
        yield ['BLOB SUB_TYPE TEXT', []];
        yield ['VARCHAR(255)', ['length' => 255]];
    }

    public function testGetBlobTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getBlobTypeDeclarationSQL([]);
        self::assertIsString($found);
        self::assertSame('BLOB', $found);
    }

    public function testGetDateTimeTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getDateTimeTypeDeclarationSQL([]);
        self::assertIsString($found);
        self::assertSame('TIMESTAMP', $found);
    }

    public function testGetTimeTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getTimeTypeDeclarationSQL([]);
        self::assertIsString($found);
        self::assertSame('TIME', $found);
    }

    public function testGetDateTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getDateTypeDeclarationSQL([]);
        self::assertIsString($found);
        self::assertSame('DATE', $found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetVarcharTypeDeclarationSQLSnippet')]
    public function testGetVarcharTypeDeclarationSQLSnippet($expected, $length, $fixed): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getVarcharTypeDeclarationSQLSnippet');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $length, $fixed);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetVarcharTypeDeclarationSQLSnippet(): Iterator
    {
        yield ['CHAR(32)', 32, true];
        yield ['CHAR(255)', 0, true];
        yield ['VARCHAR(32)', 32, false];
        yield ['VARCHAR(255)', 0, false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetColumnCharsetDeclarationSQL')]
    public function testGetColumnCharsetDeclarationSQL($expected, $charset): void
    {
        $found = $this->_platform->getColumnCharsetDeclarationSQL($charset);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetColumnCharsetDeclarationSQL(): Iterator
    {
        yield ['', ''];
        yield [' CHARACTER SET foo', 'foo'];
    }

    public function testReturnsBinaryTypeDeclarationSQL(): void
    {
        $found = $this->_platform->getBinaryTypeDeclarationSQL([]);
        self::assertSame('VARCHAR(255)', $found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetBinaryTypeDeclarationSQLSnippet')]
    public function testGetBinaryTypeDeclarationSQLSnippet($expected, $length, $fixed): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getBinaryTypeDeclarationSQLSnippet');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, $length, $fixed);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetBinaryTypeDeclarationSQLSnippet(): Iterator
    {
        yield ['CHAR(32)', 32, true];
        yield ['CHAR(8191)', 0, true];
        yield ['VARCHAR(32)', 32, false];
        yield ['VARCHAR(8191)', 0, false];
        yield ['VARCHAR(8190)', 8190, false];
        yield ['BLOB', 8192, false];
    }

    public function testGetColumnDeclarationSQL(): void
    {
        $type = $this
            ->getMockBuilder(StringType::class)
            ->disableOriginalConstructor()
            ->getMock();
        $type
            ->method('getSQLDeclaration')
            ->willReturn('baz');
        $type
            ->method('getName')
            ->willReturn('binary');
        $found = $this->_platform->getColumnDeclarationSQL('foo', ['type' => $type]);
        self::assertIsString($found);
        self::assertSame('foo baz  CHARACTER SET octets DEFAULT NULL', $found);
    }

    public function testGetCreateTemporaryTableSnippetSQL(): void
    {
        $found = $this->_platform->getCreateTemporaryTableSnippetSQL();
        self::assertIsString($found);
        self::assertSame('CREATE GLOBAL TEMPORARY TABLE', $found);
    }

    public function testGetTemporaryTableSQLgetTemporaryTableSQL(): void
    {
        $found = $this->_platform->getTemporaryTableSQL();
        self::assertIsString($found);
        self::assertSame('GLOBAL TEMPORARY', $found);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider_testGetCreateTableSQL')]
    public function testGetCreateTableSQL($expected, $options): void
    {
        $type = $this
            ->getMockBuilder(StringType::class)
            ->disableOriginalConstructor()
            ->getMock();
        $type
            ->method('getSQLDeclaration')
            ->willReturn('baz');
        $columns    = [
            0 => ['type' => $type],
        ];
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('_getCreateTableSQL');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo', $columns, $options);
        self::assertIsArray($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetCreateTableSQL(): Iterator
    {
        yield [
            [0 => 'CREATE TABLE foo (0 baz DEFAULT NULL)'],
            [],
        ];

        yield [
            [0 => 'CREATE GLOBAL TEMPORARY TABLE foo (0 baz DEFAULT NULL) ON COMMIT PRESERVE ROWS'],
            ['temporary' => true],
        ];
    }

    public function testGetListSequencesSQL(): void
    {
        $found = $this->_platform->getListSequencesSQL('');
        self::assertIsString($found);
        self::assertStringStartsWith('select trim(rdb$generator_name)', $found);
    }

    public function testGetListTableColumnsSQL(): void
    {
        $found = $this->_platform->getListTableColumnsSQL('foo');
        self::assertIsString($found);
        self::assertStringStartsWith("SELECT TRIM(r.RDB\$FIELD_NAME) AS \"FIELD_NAME\",\n", $found);
        self::assertStringContainsString('FROM RDB$RELATION_FIELDS r', $found);
    }

    public function testGetListTableForeignKeysSQL(): void
    {
        $found = $this->_platform->getListTableForeignKeysSQL('foo');
        self::assertIsString($found);
        $foundNormalized = preg_replace('/\r\n|\r/', "\n", ltrim($found));
        self::assertStringStartsWith("SELECT TRIM(rc.RDB\$CONSTRAINT_NAME) AS constraint_name,\n", $foundNormalized);
        self::assertStringContainsString(" FROM RDB\$INDEX_SEGMENTS s\n", $foundNormalized);
    }

    public function testGetListTableIndexesSQL(): void
    {
        $found = $this->_platform->getListTableIndexesSQL('foo');
        self::assertIsString($found);
        $foundNormalized = preg_replace('/\r\n|\r/', "\n", ltrim($found));
        self::assertStringStartsWith("SELECT\n", $foundNormalized);
        self::assertStringContainsString("FROM RDB\$INDEX_SEGMENTS\n", $foundNormalized);
    }

    public function testGetSQLResultCasing(): void
    {
        $strategy = new FirebirdQuoteStrategy();
        $found    = $strategy->getColumnAlias('foo', 1, $this->_platform);

        self::assertIsString($found);
        self::assertStringStartsWith('FOO_1', $found);
    }

    public function testUnquotedIdentifierName(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('unquotedIdentifierName');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        self::assertIsString($found);
        self::assertSame('foo', $found);
    }

    public function testGetQuotedNameOf(): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getQuotedNameOf');
        $method->setAccessible(true);
        $found = $method->invoke($this->_platform, 'foo');
        self::assertIsString($found);
        self::assertSame('foo', $found);
    }

    public function testGetCreateDatabaseSQL(): void
    {
        $sql = $this->_platform->getCreateDatabaseSQL('foo');
        self::assertIsString($sql);
            self::assertStringStartsWith('CREATE DATABASE', $sql);
            self::assertStringEndsWith('foo', $sql);
    }

    #[\PHPUnit\Framework\Attributes\Group('DBAL-553')]
    public function testHasNativeJsonType(): void
    {
        self::assertFalse($this->_platform->hasNativeJsonType());
    }

    #[\PHPUnit\Framework\Attributes\Group('DBAL-553')]
    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        $column = ['length' => 666, 'notnull' => true, 'type' => Type::getType('json')];
        self::assertSame($this->_platform->getClobTypeDeclarationSQL($column), $this->_platform->getJsonTypeDeclarationSQL($column));
    }

    public function testGetStringLiteralQuoteCharacter(): void
    {
        self::assertSame("'", $this->_platform->getStringLiteralQuoteCharacter());
    }
}
