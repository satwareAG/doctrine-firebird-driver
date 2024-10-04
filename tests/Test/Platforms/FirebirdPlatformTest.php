<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

use function sprintf;
use function strtoupper;
use function uniqid;

/** @extends PlatformTestCase<FirebirdPlatform> */
class FirebirdPlatformTest extends PlatformTestCase
{
    #[DataProvider('dataValidIdentifiers')]
    public function testValidIdentifiers(string $identifier): void
    {
        $platform = $this->createPlatform();
        $platform->assertValidIdentifier($identifier);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('dataInvalidIdentifiers')]
    public function testInvalidIdentifiers(string $identifier): void
    {
        $this->expectException(Exception::class);

        $platform = $this->createPlatform();
        $platform->assertValidIdentifier($identifier);
    }

    public function getGenerateTableSql(): string
    {
        return 'CREATE TABLE test (id INTEGER NOT NULL, test VARCHAR(255) DEFAULT NULL, CONSTRAINT TEST_PK PRIMARY KEY (id))';
    }

    /**
     * {@inheritDoc}
     */
    public function getGenerateTableWithMultiColumnUniqueIndexSql(): array
    {
        return [
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)',
        ];
    }

    public function testRLike(): void
    {
        self::assertSame('SIMILAR TO', $this->platform->getRegexpExpression());
    }

    public function testGeneratesSqlSnippets(): void
    {
        self::assertSame('"', $this->platform->getIdentifierQuoteCharacter());
        self::assertSame('column1 || column2 || column3', $this->platform->getConcatExpression('column1', 'column2', 'column3'));
    }

    public function testCreateDatabaseSQL(): void
    {
        self::assertSame('CREATE DATABASE foobar', $this->platform->getCreateDatabaseSQL('foobar'));
    }

    public function testDropDatabaseSQL(): void
    {
        self::assertSame('DROP DATABASE foobar', $this->platform->getDropDatabaseSQL('foobar'));
    }

    public function testDropTable(): void
    {
        $expectsd =
            'EXECUTE BLOCK AS
BEGIN
  EXECUTE STATEMENT \'EXECUTE BLOCK AS BEGIN IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE (UPPER(RDB$TRIGGER_NAME) = UPPER(\'\'FOOBAR_D2IT\'\') AND RDB$SYSTEM_FLAG = 0))) THEN BEGIN EXECUTE STATEMENT \'\'DROP TRIGGER FOOBAR_D2IT\'\'; END END \';
  EXECUTE STATEMENT \'EXECUTE BLOCK AS DECLARE TMP_VIEW_NAME varchar(255);  BEGIN FOR SELECT TRIM(v.RDB$VIEW_NAME) FROM RDB$VIEW_RELATIONS v, RDB$RELATIONS r WHERE TRIM(UPPER(v.RDB$RELATION_NAME)) = TRIM(UPPER(\'\'foobar\'\')) AND v.RDB$RELATION_NAME = r.RDB$RELATION_NAME AND (r.RDB$SYSTEM_FLAG IS DISTINCT FROM 1) AND (r.RDB$RELATION_TYPE = 0) INTO :TMP_VIEW_NAME DO BEGIN EXECUTE STATEMENT \'\'DROP VIEW "\'\'||:TMP_VIEW_NAME||\'\'"\'\'; END END \';
  EXECUTE STATEMENT \'EXECUTE BLOCK AS BEGIN EXECUTE STATEMENT \'\'EXECUTE BLOCK AS BEGIN IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE (UPPER(RDB$TRIGGER_NAME) = UPPER(\'\'\'\'FOOBAR_D2IT\'\'\'\') AND RDB$SYSTEM_FLAG = 0))) THEN BEGIN EXECUTE STATEMENT \'\'\'\'DROP TRIGGER FOOBAR_D2IT\'\'\'\'; END END \'\'; EXECUTE STATEMENT \'\'EXECUTE BLOCK AS BEGIN IF (EXISTS(SELECT 1 FROM RDB$GENERATORS 
                              WHERE (UPPER(TRIM(RDB$GENERATOR_NAME)) = UPPER(\'\'\'\'FOOBAR_D2IS\'\'\'\') 
                                AND (RDB$SYSTEM_FLAG IS DISTINCT FROM 1))
                              )) THEN BEGIN EXECUTE STATEMENT \'\'\'\'DROP SEQUENCE FOOBAR_D2IS\'\'\'\'; END END \'\'; END \';
  EXECUTE STATEMENT \'EXECUTE BLOCK AS BEGIN EXECUTE STATEMENT \'\'DROP TABLE foobar\'\'; END \';
END
';
        self::assertSame($expectsd, $this->platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers(): void
    {
        self::assertSame('INTEGER', $this->platform->getIntegerTypeDeclarationSQL([]));
        self::assertSame('INTEGER', $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]));
        self::assertSame('INTEGER', $this->platform->getIntegerTypeDeclarationSQL(
            ['autoincrement' => true, 'primary' => true],
        ));
    }

    public function testGeneratesTypeDeclarationsForStrings(): void
    {
        self::assertSame('CHAR(10)', $this->platform->getStringTypeDeclarationSQL(
            ['length' => 10, 'fixed' => true],
        ));
        self::assertSame('VARCHAR(50)', $this->platform->getStringTypeDeclarationSQL(['length' => 50]));
        self::assertSame('VARCHAR(255)', $this->platform->getStringTypeDeclarationSQL([]));
    }

    public function testPrefersIdentityColumns(): void
    {
        self::assertFalse($this->platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns(): void
    {
        self::assertFalse($this->platform->supportsIdentityColumns());
    }

    public function testSupportsSavePoints(): void
    {
        self::assertTrue($this->platform->supportsSavepoints());
    }

    public function getGenerateIndexSql(): string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql(): string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    /** @param mixed[] $options */
    #[DataProvider('getGeneratesAdvancedForeignKeyOptionsSQLData')]
    public function testGeneratesAdvancedForeignKeyOptionsSQL(array $options, string $expectedSql): void
    {
        $foreignKey = new ForeignKeyConstraint(['foo'], 'foreign_table', ['bar'], null, $options);

        self::assertSame($expectedSql, $this->platform->getAdvancedForeignKeyOptionsSQL($foreignKey));
    }

    public function testModifyLimitQuery(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertSame('SELECT * FROM user ROWS 1 TO 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertSame('SELECT * FROM user ROWS 1 TO 10', $sql);
    }

    public function testModifyLimitQueryWithNonEmptyOffset(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 10);

        self::assertSame('SELECT * FROM user ROWS 11 TO 20', $sql);
    }

    public function testModifyLimitQueryWithEmptyLimit(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', null, 10);

        self::assertSame('SELECT * FROM user ROWS 11 TO 9223372036854775807', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        self::assertSame('SELECT * FROM user ORDER BY username ASC ROWS 1 TO 10', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        self::assertSame('SELECT * FROM user ORDER BY username DESC ROWS 1 TO 10', $sql);
    }

    public function testGenerateTableWithAutoincrement(): void
    {
        $columnName = strtoupper('id' . uniqid());
        $tableName  = strtoupper('table' . uniqid());
        $table      = new Table($tableName);
        $column     = $table->addColumn($columnName, Types::INTEGER);
        $column->setAutoincrement(true);

        $this->platform->getCreateTableSQL($table);
        self::assertSame([
            sprintf('CREATE TABLE %s (%s INTEGER NOT NULL)', $tableName, $columnName),
            sprintf('CREATE SEQUENCE %s_D2IS', $tableName),
            sprintf(
                <<<'SQL'
CREATE TRIGGER %s_D2IT FOR %s
            BEFORE INSERT
            AS
            BEGIN
                IF ((NEW.%s IS NULL) OR
                   (NEW.%s = 0)) THEN
                BEGIN
                    NEW.%s = NEXT VALUE FOR %s_D2IS;
                END
            END;
SQL
                ,
                $tableName,
                $tableName,
                $columnName,
                $columnName,
                $columnName,
                $tableName,
            ),
        ], $this->platform->getCreateTableSQL($table));
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTableColumnCommentsSQL(): array
    {
        return [
            'CREATE TABLE test (id INTEGER NOT NULL, CONSTRAINT TEST_PK PRIMARY KEY (id))',
            "COMMENT ON COLUMN test.id IS 'This is a comment'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTableColumnTypeCommentsSQL(): array
    {
        return [
            'CREATE TABLE test (id INTEGER NOT NULL, data BLOB SUB_TYPE TEXT NOT NULL, CONSTRAINT TEST_PK PRIMARY KEY (id))',
            "COMMENT ON COLUMN test.data IS '(DC2Type:array)'",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableColumnCommentsSQL(): array
    {
        return [
            'ALTER TABLE mytable ADD quota INTEGER NOT NULL',
            "COMMENT ON COLUMN mytable.quota IS 'A comment'",
            "COMMENT ON COLUMN mytable.foo IS ''",
            "COMMENT ON COLUMN mytable.baz IS 'B comment'",
        ];
    }

    public function testAlterTableNotNULL(): void
    {
        $tableDiff                          = new TableDiff('mytable');
        $tableDiff->changedColumns['foo']   = new ColumnDiff(
            'foo',
            new Column(
                'foo',
                Type::getType(Types::STRING),
                ['default' => 'bla', 'notnull' => true],
            ),
            ['type'],
        );
        $tableDiff->changedColumns['bar']   = new ColumnDiff(
            'bar',
            new Column(
                'baz',
                Type::getType(Types::STRING),
                ['default' => 'bla', 'notnull' => true],
            ),
            ['type', 'notnull'],
        );
        $tableDiff->changedColumns['metar'] = new ColumnDiff(
            'metar',
            new Column(
                'metar',
                Type::getType(Types::STRING),
                ['length' => 2000, 'notnull' => false],
            ),
            ['notnull'],
        );

        $expectedSql = [
            0 => 'ALTER TABLE mytable ALTER COLUMN foo TYPE VARCHAR(255)',
            1 => 'ALTER TABLE mytable ALTER COLUMN bar TYPE VARCHAR(255)',
            2 => 'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = 1 WHERE UPPER(RDB$FIELD_NAME) = UPPER(\'bar\') AND UPPER(RDB$RELATION_NAME) = UPPER(\'mytable\')',
            3 => 'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = NULL WHERE UPPER(RDB$FIELD_NAME) = UPPER(\'metar\') AND UPPER(RDB$RELATION_NAME) = UPPER(\'mytable\')',
        ];

        self::assertSame($expectedSql, $this->platform->getAlterTableSQL($tableDiff));
    }

    public function testInitializesDoctrineTypeMappings(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('boolean'));
        self::assertSame(Types::SMALLINT, $this->platform->getDoctrineTypeMapping('boolean'));
    }

    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL(): void
    {
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['length' => 8192]));
        self::assertSame('BLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 8192]));
    }

    public function testUsesSequenceEmulatedIdentityColumns(): void
    {
        self::assertTrue($this->platform->usesSequenceEmulatedIdentityColumns());
    }

    public function testReturnsIdentitySequenceName(): void
    {
        self::assertSame('MYTABLE_D2IS', $this->platform->getIdentitySequenceName('mytable', 'mycolumn'));
        self::assertSame('"mytable_D2IS"', $this->platform->getIdentitySequenceName('"mytable"', 'mycolumn'));
        self::assertSame('MYTABLE_D2IS', $this->platform->getIdentitySequenceName('mytable', '"mycolumn"'));
        self::assertSame('"mytable_D2IS"', $this->platform->getIdentitySequenceName('"mytable"', '"mycolumn"'));
    }

    #[DataProvider('dataCreateSequenceWithCache')]
    public function testCreateSequenceWithCache(int $cacheSize, string $expectedSql): void
    {
        if (! ($this->platform instanceof Firebird3Platform)) {
            $this->markTestSkipped('Firebird below 3 does not support sequence cache.');
        }

        $sequence = new Sequence('foo', 1, 1, $cacheSize);
        self::assertStringContainsString($expectedSql, $this->platform->getCreateSequenceSQL($sequence));
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('VARCHAR(36)', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableRenameColumnSQL(): array
    {
        return ['ALTER TABLE foo ALTER COLUMN bar TO baz'];
    }

    /** @param string|string[] $expectedSql */
    #[DataProvider('getReturnsDropAutoincrementSQL')]
    public function testReturnsDropAutoincrementSQL(string $table, string|array $expectedSql): void
    {
        $resultSql = $this->platform->getDropAutoincrementSql($table);
        self::assertSame($expectedSql, $resultSql);
    }

    public function testAltersTableColumnCommentWithExplicitlyQuotedIdentifiers(): void
    {
        $table1 = new Table('"foo"', [new Column('"bar"', Type::getType(Types::INTEGER))]);
        $table2 = new Table('"foo"', [new Column('"bar"', Type::getType(Types::INTEGER), ['comment' => 'baz'])]);

        $comparator = new Comparator();

        $tableDiff = $comparator->diffTable($table1, $table2);

        self::assertInstanceOf(TableDiff::class, $tableDiff);
        self::assertSame(['COMMENT ON COLUMN "foo"."bar" IS \'baz\''], $this->platform->getAlterTableSQL($tableDiff));
    }

    public function testQuotedTableNames(): void
    {
        $table = new Table('"test"');
        $table->addColumn('"id"', Types::INTEGER, ['autoincrement' => true]);

        // assert tabel
        self::assertTrue($table->isQuoted());
        self::assertSame('test', $table->getName());
        self::assertSame('"test"', $table->getQuotedName($this->platform));

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertSame('CREATE TABLE "test" ("id" INTEGER NOT NULL)', $sql[0]);
        self::assertSame('CREATE SEQUENCE TEST_D2IS', $sql[1]);
        $createTriggerStatement = <<<'EOD'
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
EOD;

        self::assertSame($createTriggerStatement, $sql[2]);
    }

    #[DataProvider('getReturnsGetListTableColumnsSQL')]
    public function testReturnsGetListTableColumnsSQL(string|null $database, string $expectedSql): void
    {
        // note: this assertion is a bit strict, as it compares a full SQL string.
        // Should this break in future, then please try to reduce the matching to substring matching while reworking
        // the tests
        self::assertSame($expectedSql, $this->platform->getListTableColumnsSQL('"test"', $database));
    }

    public function testQuotesTableNameInListTableIndexesSQL(): void
    {
        self::assertStringContainsStringIgnoringCase("'Foo''Bar\\'", $this->platform->getListTableIndexesSQL("Foo'Bar\\"));
    }

    public function testQuotesTableNameInListTableForeignKeysSQL(): void
    {
        self::assertStringContainsStringIgnoringCase("'Foo''Bar\\'", $this->platform->getListTableForeignKeysSQL("Foo'Bar\\"));
    }

    public function testQuotesTableNameInListTableConstraintsSQL(): void
    {
        self::assertStringContainsStringIgnoringCase("'Foo''Bar\\'", $this->platform->getListTableConstraintsSQL("Foo'Bar\\"));
    }

    public function testQuotesTableNameInListTableColumnsSQL(): void
    {
        self::assertStringContainsStringIgnoringCase("'Foo''Bar\\'", $this->platform->getListTableColumnsSQL("Foo'Bar\\"));
    }

    public static function createPlatform(): AbstractPlatform
    {
        return new FirebirdPlatform();
    }

    /** @return mixed[][] */
    public static function dataValidIdentifiers(): Iterator
    {
        yield ['a'];
        yield ['foo'];
        yield ['Foo'];
        yield ['Foo123'];
        yield ['Foo#bar_baz$'];
        yield ['"a"'];
        yield ['"1"'];
        yield ['"foo_bar"'];
        yield ['"@$%&!"'];
    }

    /** @return mixed[][] */
    public static function dataInvalidIdentifiers(): Iterator
    {
        yield ['1'];
        yield ['abc&'];
        yield ['abc-def'];
        yield ['"'];
        yield ['"foo"bar"'];
    }

    /** @return mixed[][] */
    public static function dataCreateSequenceWithCache(): Iterator
    {
        yield [1, 'NOCACHE'];
        yield [0, 'NOCACHE'];
        yield [3, 'CACHE 3'];
    }

    /** @return mixed[][] */
    public static function getReturnsDropAutoincrementSQL(): Iterator
    {
        yield [
            'myTable',
            <<<'SQL'
EXECUTE BLOCK AS BEGIN EXECUTE STATEMENT 'EXECUTE BLOCK AS BEGIN IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE (UPPER(RDB$TRIGGER_NAME) = UPPER(''MYTABLE_D2IT'') AND RDB$SYSTEM_FLAG = 0))) THEN BEGIN EXECUTE STATEMENT ''DROP TRIGGER MYTABLE_D2IT''; END END '; EXECUTE STATEMENT 'EXECUTE BLOCK AS BEGIN IF (EXISTS(SELECT 1 FROM RDB$GENERATORS 
                              WHERE (UPPER(TRIM(RDB$GENERATOR_NAME)) = UPPER(''MYTABLE_D2IS'') 
                                AND (RDB$SYSTEM_FLAG IS DISTINCT FROM 1))
                              )) THEN BEGIN EXECUTE STATEMENT ''DROP SEQUENCE MYTABLE_D2IS''; END END '; END 
SQL,
        ];

        yield [
            '"myTable"',
            <<<'SQL'
EXECUTE BLOCK AS BEGIN EXECUTE STATEMENT 'EXECUTE BLOCK AS BEGIN IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE (UPPER(RDB$TRIGGER_NAME) = UPPER(''myTable_D2IT'') AND RDB$SYSTEM_FLAG = 0))) THEN BEGIN EXECUTE STATEMENT ''DROP TRIGGER "myTable_D2IT"''; END END '; EXECUTE STATEMENT 'EXECUTE BLOCK AS BEGIN IF (EXISTS(SELECT 1 FROM RDB$GENERATORS 
                              WHERE (UPPER(TRIM(RDB$GENERATOR_NAME)) = UPPER(''myTable_D2IS'') 
                                AND (RDB$SYSTEM_FLAG IS DISTINCT FROM 1))
                              )) THEN BEGIN EXECUTE STATEMENT ''DROP SEQUENCE "myTable_D2IS"''; END END '; END 
SQL,
        ];

        yield [
            'table',
            <<<'SQL'
EXECUTE BLOCK AS BEGIN EXECUTE STATEMENT 'EXECUTE BLOCK AS BEGIN IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE (UPPER(RDB$TRIGGER_NAME) = UPPER(''TABLE_D2IT'') AND RDB$SYSTEM_FLAG = 0))) THEN BEGIN EXECUTE STATEMENT ''DROP TRIGGER TABLE_D2IT''; END END '; EXECUTE STATEMENT 'EXECUTE BLOCK AS BEGIN IF (EXISTS(SELECT 1 FROM RDB$GENERATORS 
                              WHERE (UPPER(TRIM(RDB$GENERATOR_NAME)) = UPPER(''TABLE_D2IS'') 
                                AND (RDB$SYSTEM_FLAG IS DISTINCT FROM 1))
                              )) THEN BEGIN EXECUTE STATEMENT ''DROP SEQUENCE TABLE_D2IS''; END END '; END 
SQL,
        ];
    }

    /** @return mixed[][] */
    public static function getReturnsGetListTableColumnsSQL(): Iterator
    {
        yield [
            null,
            <<<'SQL'
SELECT TRIM(r.RDB$FIELD_NAME) AS "FIELD_NAME",
TRIM(f.RDB$FIELD_NAME) AS "FIELD_DOMAIN",
TRIM(f.RDB$FIELD_TYPE) AS "FIELD_TYPE",
TRIM(typ.RDB$TYPE_NAME) AS "FIELD_TYPE_NAME",
f.RDB$FIELD_SUB_TYPE AS "FIELD_SUB_TYPE",
f.RDB$FIELD_LENGTH AS "FIELD_LENGTH",
f.RDB$CHARACTER_LENGTH AS "FIELD_CHAR_LENGTH",
f.RDB$FIELD_PRECISION AS "FIELD_PRECISION",
f.RDB$FIELD_SCALE AS "FIELD_SCALE",
MIN(TRIM(rc.RDB$CONSTRAINT_TYPE)) AS "FIELD_CONSTRAINT_TYPE",
MIN(TRIM(i.RDB$INDEX_NAME)) AS "FIELD_INDEX_NAME",
r.RDB$NULL_FLAG as "FIELD_NOT_NULL_FLAG",
r.RDB$DEFAULT_SOURCE AS "FIELD_DEFAULT_SOURCE",
r.RDB$FIELD_POSITION AS "FIELD_POSITION",
r.RDB$DESCRIPTION AS "FIELD_DESCRIPTION",
f.RDB$CHARACTER_SET_ID as "CHARACTER_SET_ID",
TRIM(cs.RDB$CHARACTER_SET_NAME) as "CHARACTER_SET_NAME",
f.RDB$COLLATION_ID as "COLLATION_ID",
TRIM(cl.RDB$COLLATION_NAME) as "COLLATION_NAME"
FROM RDB$RELATION_FIELDS r
LEFT OUTER JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
LEFT OUTER JOIN RDB$INDEX_SEGMENTS s ON s.RDB$FIELD_NAME=r.RDB$FIELD_NAME
LEFT OUTER JOIN RDB$INDICES i ON i.RDB$INDEX_NAME = s.RDB$INDEX_NAME 
                              AND i.RDB$RELATION_NAME = r.RDB$RELATION_NAME
LEFT OUTER JOIN RDB$RELATION_CONSTRAINTS rc ON rc.RDB$INDEX_NAME = s.RDB$INDEX_NAME 
                                            AND rc.RDB$INDEX_NAME = i.RDB$INDEX_NAME 
                                            AND rc.RDB$RELATION_NAME = i.RDB$RELATION_NAME
LEFT OUTER JOIN RDB$REF_CONSTRAINTS REFC ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
LEFT OUTER JOIN RDB$TYPES typ ON typ.RDB$FIELD_NAME = 'RDB$FIELD_TYPE' 
                              AND typ.RDB$TYPE = f.RDB$FIELD_TYPE
LEFT OUTER JOIN RDB$TYPES sub ON sub.RDB$FIELD_NAME = 'RDB$FIELD_SUB_TYPE' 
                              AND sub.RDB$TYPE = f.RDB$FIELD_SUB_TYPE
LEFT OUTER JOIN RDB$CHARACTER_SETS cs ON cs.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID
LEFT OUTER JOIN RDB$COLLATIONS cl ON cl.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID 
                                  AND cl.RDB$COLLATION_ID = f.RDB$COLLATION_ID
WHERE UPPER(r.RDB$RELATION_NAME) = UPPER('test')
GROUP BY "FIELD_NAME", "FIELD_DOMAIN", "FIELD_TYPE", "FIELD_TYPE_NAME", "FIELD_SUB_TYPE",  "FIELD_LENGTH",
         "FIELD_CHAR_LENGTH", "FIELD_PRECISION", "FIELD_SCALE", "FIELD_NOT_NULL_FLAG", "FIELD_DEFAULT_SOURCE",
         "FIELD_POSITION","FIELD_DESCRIPTION", 
         "CHARACTER_SET_ID", "CHARACTER_SET_NAME", "COLLATION_ID", "COLLATION_NAME"
ORDER BY "FIELD_POSITION"
SQL
,
        ];
    }

    /** @return array<int, array{string, array<string, mixed>}> */
    public static function asciiStringSqlDeclarationDataProvider(): Iterator
    {
        yield ['VARCHAR(12)', ['length' => 12]];
        yield ['CHAR(12)', ['length' => 12, 'fixed' => true]];
    }

    protected function supportsCommentOnStatement(): bool
    {
        return true;
    }

    protected function getGenerateForeignKeySql(): string
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInPrimaryKeySQL(): array
    {
        return ['CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, CONSTRAINT "quoted_PK" PRIMARY KEY ("create"))'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInIndexSQL(): array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedNameInIndexSQL(): array
    {
        return [
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedColumnInForeignKeySQL(): array
    {
        return [
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, '
                . '"bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES "foreign" ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES foo ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar")'
                . ' REFERENCES "foo-bar" ("create", bar, "foo-bar")',
            'CREATE INDEX IDX_22660D028FD6E0FB8C736521D79164E3 ON "quoted" ("create", foo, "bar")',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL(): array
    {
        return [
            'ALTER TABLE mytable ALTER COLUMN unquoted1 TO unquoted',
            'ALTER TABLE mytable ALTER COLUMN unquoted2 TO "where"',
            'ALTER TABLE mytable ALTER COLUMN unquoted3 TO "foo"',
            'ALTER TABLE mytable ALTER COLUMN "create" TO reserved_keyword',
            'ALTER TABLE mytable ALTER COLUMN "table" TO "from"',
            'ALTER TABLE mytable ALTER COLUMN "select" TO "bar"',
            'ALTER TABLE mytable ALTER COLUMN quoted1 TO quoted',
            'ALTER TABLE mytable ALTER COLUMN quoted2 TO "and"',
            'ALTER TABLE mytable ALTER COLUMN quoted3 TO "baz"',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL(): array
    {
        self::markTestIncomplete('Not implemented yet');
    }

    protected function getQuotesDropForeignKeySQL(): string
    {
        return 'ALTER TABLE "table" DROP CONSTRAINT "select"';
    }

    /**
     * {@inheritDoc}
     */
    protected function getCommentOnColumnSQL(): array
    {
        return [
            'COMMENT ON COLUMN foo.bar IS \'comment\'',
            'COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'',
            'COMMENT ON COLUMN "select"."from" IS \'comment\'',
        ];
    }

    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): string
    {
        return 'CONSTRAINT "select" UNIQUE (foo)';
    }

    protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string
    {
        return 'INDEX "select" (foo)';
    }

    protected function getQuotesReservedKeywordInTruncateTableSQL(): string
    {
        return 'DELETE FROM "select"';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterStringToFixedStringSQL(): array
    {
        return ['ALTER TABLE mytable ALTER COLUMN name TYPE CHAR(2)'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array
    {
        return [
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
        ];
    }

    protected function getLimitOffsetCastToIntExpectedQuery(): string
    {
        return 'SELECT * FROM user ROWS 3 TO 3';
    }
}
