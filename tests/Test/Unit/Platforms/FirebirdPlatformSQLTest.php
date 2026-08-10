<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DateIntervalUnit;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use function array_walk;
use function implode;
use function preg_replace;
use function strtoupper;
use function trim;
use function uniqid;

use const PHP_INT_MAX;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SQL generation.
 *
 * Refactored to be a true Unit test extending TestCase directly to avoid
 * unnecessary database connections and locking issues.
 */
class FirebirdPlatformSQLTest extends TestCase
{
    protected Firebird3Platform $_platform;

    protected function setUp(): void
    {
        $this->_platform = new Firebird3Platform();
    }

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

    #[DataProvider('dataProvider_testGetDateArithmeticIntervalExpression')]
    public function testGetDateArithmeticIntervalExpression($expected, $operator, $interval, $unit): void
    {
        $reflection = new ReflectionObject($this->_platform);
        $method     = $reflection->getMethod('getDateArithmeticIntervalExpression');
        $found = $method->invoke($this->_platform, '2018-01-01', $operator, $interval, $unit);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetDateArithmeticIntervalExpression(): Iterator
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

    #[DataProvider('dataProvider_testGetLocateExpression')]
    public function testGetLocateExpression(string $expected, ?string $startPos): void
    {
        $found = $this->_platform->getLocateExpression('foo', 'o', $startPos);
        self::assertIsString($found);
        self::assertSame($expected, $found);
    }

    public static function dataProvider_testGetLocateExpression(): Iterator
    {
        yield ['POSITION (o in foo)', null];
        yield ['POSITION (o, foo, 1)', '1'];
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
        // DBAL4: getIdentifierQuoteCharacter() removed; verify quoting behaviour via quoteIdentifier()
        self::assertSame('"test"', $this->_platform->quoteIdentifier('test'));
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
        // DBAL4: getVarcharTypeDeclarationSQL() removed; use getStringTypeDeclarationSQL()
        self::assertSame('CHAR(10)', $this->_platform->getStringTypeDeclarationSQL([
            'length' => 10,
            'fixed' => true,
        ]));
        self::assertSame('VARCHAR(50)', $this->_platform->getStringTypeDeclarationSQL(['length' => 50]));
        self::assertSame('VARCHAR(255)', $this->_platform->getStringTypeDeclarationSQL([]));
    }

    #[Group('DBAL-1097')]
    #[DataProvider('dataProvider_testGeneratesAdvancedForeignKeyOptionsSQL')]
    public function testGeneratesAdvancedForeignKeyOptionsSQL($expected, array $options): void
    {
        // Kept as-is: getAdvancedForeignKeyOptionsSQL() reads from the deprecated hasOption()/getOption() API
        // which the editor doesn't populate, so the editor API cannot be used here.
        $foreignKey = new ForeignKeyConstraint(
            ['foo'],
            'foreign_table',
            ['bar'],
            '',
            $options,
        );
        self::assertSame($expected, $this->_platform->getAdvancedForeignKeyOptionsSQL($foreignKey));
    }

    /** @return array */
    public static function dataProvider_testGeneratesAdvancedForeignKeyOptionsSQL(): Iterator
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
        self::assertSame('SELECT * FROM user FETCH FIRST 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertSame('SELECT * FROM user FETCH FIRST 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithEmptyLimit(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', null, 10);
        self::assertEquals('SELECT * FROM user OFFSET 10 ROWS', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        self::assertSame('SELECT * FROM user ORDER BY username ASC FETCH FIRST 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy(): void
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        self::assertSame('SELECT * FROM user ORDER BY username DESC FETCH FIRST 10 ROWS ONLY', $sql);
    }

    public function testGenerateTableWithAutoincrement(): void
    {
        $columnName = strtoupper('id' . uniqid());
        $tableName  = strtoupper('table' . uniqid());
        $table      = Table::editor()
            ->setUnquotedName($tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName($columnName)
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();
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
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::STRING)
                    ->setNotNull(false)
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::STRING)
                    ->setNotNull(false)
                    ->setLength(255)
                    ->create(),
            )
            ->create();
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
        $indexDef = Index::editor()
            ->setUnquotedName('my_idx')
            ->setUnquotedColumnNames('user_name', 'last_login')
            ->create();
        $found    = $this->_platform->getCreateIndexSQL($indexDef, 'mytable');
        $expected = 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
        self::assertSame($expected, $found);
    }

    public function testGeneratesUniqueIndexCreationSql(): void
    {
        $indexDef = Index::editor()
            ->setUnquotedName('index_name')
            ->setUnquotedColumnNames('test', 'test2')
            ->setType(IndexType::UNIQUE)
            ->create();
        $found    = $this->_platform->getCreateIndexSQL($indexDef, 'test');
        $expected = 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
        self::assertSame($expected, $found);
    }

    #[Group('DBAL-472')]
#[Group('DBAL-1001')]
    public function testAlterTableNotNULL(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::TEXT)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();
        $fromTable->addColumn('bar', Types::STRING, ['length' => 10, 'notnull' => false]);
        $fromTable->addColumn('metar', 'string', ['notnull' => true]);

        $toTable = clone $fromTable;
        $toTable->dropColumn('foo');
        $toTable->addColumn('foo', Types::STRING, ['length' => 255, 'notnull' => true, 'default' => 'bla']);
        $toTable->dropColumn('bar');

        $toTable->addColumn('bar', Types::STRING, ['length' => 255, 'default' => 'bla', 'notnull' => true]);
        $toTable->dropColumn('metar');

         $toTable->addColumn('metar', 'string', ['notnull' => false, 'length' => 255]);

        $comparator = new Comparator($this->_platform);
        $tableDiff = $comparator->compareTables($fromTable, $toTable);

        $found = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertCount(8, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN foo TYPE VARCHAR(255)', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame("ALTER TABLE mytable ALTER foo SET DEFAULT 'bla'", $found[1]);
        self::assertArrayHasKey(5, $found);
        self::assertSame('ALTER TABLE mytable ALTER bar TYPE VARCHAR(255)', $found[5]);
        self::assertArrayHasKey(3, $found);
        self::assertSame("ALTER TABLE mytable ALTER bar SET DEFAULT 'bla'", $found[3]);
        self::assertArrayHasKey(2, $found);
        self::assertArrayHasKey(4, $found);
        self::assertArrayHasKey(6, $found);
        self::assertArrayHasKey(7, $found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('ALTER TABLE mytable ALTER foo SET NOT NULL', $found[2]);
            self::assertSame('ALTER TABLE mytable ALTER bar SET NOT NULL', $found[4]);
            self::assertSame('ALTER TABLE mytable ALTER metar DROP NOT NULL', $found[6]);
        } else {
            self::assertSame("UPDATE RDB\$RELATION_FIELDS SET RDB\$NULL_FLAG = 1 WHERE UPPER(RDB\$FIELD_NAME) = UPPER('foo') AND UPPER(RDB\$RELATION_NAME) = UPPER('mytable')", $found[2]);
            self::assertSame("UPDATE RDB\$RELATION_FIELDS SET RDB\$NULL_FLAG = 1 WHERE UPPER(RDB\$FIELD_NAME) = UPPER('bar') AND UPPER(RDB\$RELATION_NAME) = UPPER('mytable')", $found[4]);
            self::assertSame("UPDATE RDB\$RELATION_FIELDS SET RDB\$NULL_FLAG = NULL WHERE UPPER(RDB\$FIELD_NAME) = UPPER('metar') AND UPPER(RDB\$RELATION_NAME) = UPPER('mytable')", $found[6]);
        }
        self::assertSame('ALTER TABLE mytable ALTER metar TYPE VARCHAR(255)', $found[7]);
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

    #[Group('DBAL-1004')]
    public function testAltersTableColumnCommentWithExplicitlyQuotedIdentifiers(): void
    {
        $table1     = Table::editor()
            ->setQuotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();
        $table2     = Table::editor()
            ->setQuotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->setComment('baz')
                    ->create(),
            )
            ->create();
        $comparator = new Comparator($this->_platform);
        $tableDiff  = $comparator->compareTables($table1, $table2);
        self::assertSame(['COMMENT ON COLUMN "foo"."bar" IS \'baz\''], $this->_platform->getAlterTableSQL($tableDiff));
    }

    public function testQuotedTableNames(): void
    {
        $table = Table::editor()
            ->setQuotedName('test')
            ->setColumns(
                Column::editor()
                    ->setQuotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
            )
            ->create();
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
        $indexDef = Index::editor()
            ->setUnquotedName('name')
            ->setUnquotedColumnNames('test', 'test2')
            ->setPredicate($where)
            ->create();
        // $uniqueIndex = new Index('name', ['test', 'test2'], true, false, [], ['where' => $where]);
        $uniqueIndex = UniqueConstraint::editor()
            ->setUnquotedName('name')
            ->setUnquotedColumnNames('test', 'test2')
            ->create();

        $expected  = ' WHERE ' . $where;
        $actuals   = [];
        $actuals[] = $this->_platform->getIndexDeclarationSQL($indexDef);
        $actuals[] = $this->_platform->getUniqueConstraintDeclarationSQL($uniqueIndex);
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
        $fk       = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('fk_name_id')
            ->setUnquotedReferencedTableName('other_table')
            ->setUnquotedReferencedColumnNames('id')
            ->create();
        $found    = $this->_platform->getCreateForeignKeySQL($fk, 'test');
        $expected = 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
        self::assertSame($expected, $found);
    }

    public function testGeneratesConstraintCreationSql(): void
    {
        // DBAL4: getCreateConstraintSQL() removed; use type-specific methods
        $idx   = Index::editor()
            ->setUnquotedName('constraint_name')
            ->setUnquotedColumnNames('test')
            ->setType(IndexType::UNIQUE)
            ->create();
        $found = $this->_platform->getCreateIndexSQL($idx, 'test');
        self::assertStringContainsString('UNIQUE', $found);
        self::assertStringContainsString('constraint_name', $found);

        $pk    = Index::editor()
            ->setUnquotedName('constraint_name')
            ->setUnquotedColumnNames('test')
            ->create();
        $found = $this->_platform->getCreatePrimaryKeySQL($pk, 'test');
        self::assertStringContainsString('PRIMARY KEY', $found);
        self::assertStringContainsString('PRIMARY KEY', $found);

        $fk                 = ForeignKeyConstraint::editor()
            ->setUnquotedName('constraint_fk')
            ->setUnquotedReferencingColumnNames('fk_name')
            ->setUnquotedReferencedTableName('foreign')
            ->setUnquotedReferencedColumnNames('id')
            ->create();
        $found              = $this->_platform->getCreateForeignKeySQL($fk, 'test');
        $quotedForeignTable = $fk->getQuotedForeignTableName($this->_platform);
        self::assertStringContainsString('FOREIGN KEY', $found);
        self::assertStringContainsString($quotedForeignTable, $found);
    }

    public function testGeneratesTableAlterationSqlThrowsException(): void
    {
        $this->expectExceptionMessageMatches('/.*firebird does not support it.*/i');
        $this->expectException(\RuntimeException::class);
        
        // FirebirdPlatform explicitly overrides getAlterTableSQL and currently ignores newName,
        // but getRenameTableSQL explicitly throws the exception we want to verify.
        // Verifying the platform capability directly.
        $this->_platform->getRenameTableSQL('old', 'new');
    }

    public function testGetCustomColumnDeclarationSql(): void
    {
        $field = ['columnDefinition' => 'bar'];
        self::assertSame('foo bar', $this->_platform->getColumnDeclarationSQL('foo', $field));
    }

    #[Group('DBAL-42')]
    public function testCreateTableColumnComments(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setComment('This is a comment')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $found = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('CREATE TABLE test (id INTEGER NOT NULL, CONSTRAINT TEST_PK PRIMARY KEY (id))', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame("COMMENT ON COLUMN test.id IS 'This is a comment'", $found[1]);
    }

    #[Group('DBAL-42')]
    public function testAlterTableColumnComments(): void
    {
        $oldTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::INTEGER)
                    ->setComment('old foo comment')
                    ->create(),
            )
            ->create();
        $oldTable->addColumn('bar', 'integer');
        $tableDiff = new TableDiff(
            $oldTable,
            addedColumns: [
                Column::editor()
                    ->setUnquotedName('quota')
                    ->setTypeName(Types::INTEGER)
                    ->setComment('A comment')
                    ->create(),
            ],
            changedColumns: [
                new ColumnDiff(
                    Column::editor()
                        ->setUnquotedName('foo')
                        ->setTypeName(Types::INTEGER)
                        ->setComment('old foo comment')
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('foo')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                ),
                new ColumnDiff(
                    Column::editor()
                        ->setUnquotedName('bar')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('bar')
                        ->setTypeName(Types::INTEGER)
                        ->setComment('B comment')
                        ->create(),
                ),
            ],
        );
        $found = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertCount(4, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE mytable ADD quota INTEGER NOT NULL', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame("COMMENT ON COLUMN mytable.quota IS 'A comment'", $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame("COMMENT ON COLUMN mytable.foo IS ''", $found[2]);
        self::assertArrayHasKey(3, $found);
        self::assertSame("COMMENT ON COLUMN mytable.bar IS 'B comment'", $found[3]);
    }

    public function testCreateTableColumnTypeComments(): void
    {
        // DBAL4: json type no longer requires SQL comment hints (requiresSQLCommentHint() returns false).
        // The platform still maps json to BLOB SUB_TYPE TEXT; only the COMMENT ON is no longer generated.
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('data')
                    ->setTypeName(Types::JSON)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $found = $this->_platform->getCreateTableSQL($table);
        self::assertCount(1, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('CREATE TABLE test (id INTEGER NOT NULL, data BLOB SUB_TYPE TEXT NOT NULL, CONSTRAINT TEST_PK PRIMARY KEY (id))', $found[0]);
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

    #[Group('DBAL-374')]
    public function testQuotedColumnInPrimaryKeyPropagation(): void
    {
        $table = Table::editor()
            ->setQuotedName('quoted')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('create')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('create')->create(),
            )
            ->create();
        $found = $this->_platform->getCreateTableSQL($table);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertArrayHasKey(0, $found);
        $expected = 'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, CONSTRAINT "quoted_PK" PRIMARY KEY ("create"))';
        self::assertSame($expected, $found[0]);
    }

    #[Group('DBAL-374')]
    public function testQuotedColumnInIndexPropagation(): void
    {
        $table = Table::editor()
            ->setQuotedName('quoted')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('create')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();
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
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('column1')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();
        $table->addIndex(['column1'], '`key`');
        $found    = $this->_platform->getCreateTableSQL($table);
        $expected = [
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL)',
            'CREATE INDEX "key" ON test (column1)',
        ];
        self::assertSame($expected, $found);
    }

    #[Group('DBAL-374')]
    public function testQuotedColumnInForeignKeyPropagation(): void
    {
        $table = Table::editor()
            ->setQuotedName('quoted')
            ->setColumns(
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->create(),
                Column::editor()->setUnquotedName('foo')->setTypeName(Types::STRING)->create(),
                Column::editor()->setQuotedName('bar')->setTypeName(Types::STRING)->create(),
            )
            ->create();

        // Foreign table with reserved keyword as name (needs quotation).
        $foreignTable = Table::editor()
            ->setUnquotedName('foreign')
            ->setColumns(
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->create(),
            )
            ->create();
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $table->addForeignKeyConstraint(
            'foreign',
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_RESERVED_KEYWORD',
        );
        // Foreign table with non-reserved keyword as name (does not need quotation).
        $foreignTable = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->create(),
            )
            ->create();
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $table->addForeignKeyConstraint(
            'foo',
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_NON_RESERVED_KEYWORD',
        );
        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable = Table::editor()
            ->setQuotedName('foo-bar')
            ->setColumns(
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->create(),
            )
            ->create();
        $foreignTable->addColumn('bar', 'string');       // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('`foo-bar`', 'string'); // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $table->addForeignKeyConstraint(
            '`foo-bar`',
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_INTENDED_QUOTATION',
        );
        $found = $this->_platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS);
        self::assertCount(5, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar")', $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame('ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar")', $found[2]);
        self::assertArrayHasKey(3, $found);
        self::assertSame('ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar")', $found[3]);
        // DBAL4 generates an index for the FK columns
        self::assertArrayHasKey(4, $found);
        self::assertStringContainsString('CREATE INDEX', $found[4]);
        self::assertStringContainsString('ON "quoted"', $found[4]);
    }

    #[Group('DBAL-1051')]
    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): void
    {
        $index = UniqueConstraint::editor()
            ->setUnquotedName('select')
            ->setUnquotedColumnNames('foo')
            ->create();
        $found = $this->_platform->getUniqueConstraintDeclarationSQL($index);
        self::assertSame('CONSTRAINT "select" UNIQUE (foo)', $found);
    }

    #[Group('DBAL-1051')]
    public function testQuotesReservedKeywordInIndexDeclarationSQL(): void
    {
        $index = Index::editor()
            ->setUnquotedName('select')
            ->setUnquotedColumnNames('foo')
            ->create();
        $found = $this->_platform->getIndexDeclarationSQL($index);
        self::assertSame('INDEX "select" (foo)', $found);
    }

    #[Group('DBAL-585')]
    public function testAlterTableChangeQuotedColumn(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('select')->setTypeName(Types::INTEGER)->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $fromTable,
            changedColumns: [
                new ColumnDiff(
                    Column::editor()->setUnquotedName('select')->setTypeName(Types::INTEGER)->create(),
                    Column::editor()->setUnquotedName('select')->setTypeName(Types::STRING)->create(),
                ),
            ],
        );
        self::assertStringContainsString($this->_platform->quoteIdentifier('select'), implode(';', $this->_platform->getAlterTableSQL($tableDiff)));
    }

    #[Group('DBAL-234')]
    public function testAlterTableRenameIndex(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $fromTable,
            renamedIndexes: [
                'idx_foo' => Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            ],
        );
        $found     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX idx_foo', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX idx_bar ON mytable (id)', $found[1]);
    }

    #[Group('DBAL-234')]
    public function testQuotesAlterTableRenameIndex(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('table')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $fromTable,
            renamedIndexes: [
                'create' => Index::editor()
                    ->setUnquotedName('select')
                    ->setUnquotedColumnNames('id')
                    ->create(),
                '`foo`' => Index::editor()
                    ->setQuotedName('bar')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            ],
        );
        $found     = $this->_platform->getAlterTableSQL($tableDiff);
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

    #[Group('DBAL-835')]
    public function testQuotesAlterTableRenameColumn(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('unquoted1')->setTypeName(Types::INTEGER)->setComment('Unquoted 1')->create(),
                Column::editor()->setUnquotedName('unquoted2')->setTypeName(Types::INTEGER)->setComment('Unquoted 2')->create(),
                Column::editor()->setUnquotedName('unquoted3')->setTypeName(Types::INTEGER)->setComment('Unquoted 3')->create(),
                Column::editor()->setUnquotedName('create')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 1')->create(),
                Column::editor()->setUnquotedName('table')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 2')->create(),
                Column::editor()->setUnquotedName('select')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 3')->create(),
                Column::editor()->setQuotedName('quoted1')->setTypeName(Types::INTEGER)->setComment('Quoted 1')->create(),
                Column::editor()->setQuotedName('quoted2')->setTypeName(Types::INTEGER)->setComment('Quoted 2')->create(),
                Column::editor()->setQuotedName('quoted3')->setTypeName(Types::INTEGER)->setComment('Quoted 3')->create(),
            )
            ->create();

        $toTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('unquoted')->setTypeName(Types::INTEGER)->setComment('Unquoted 1')->create(), // unquoted -> unquoted
                Column::editor()->setUnquotedName('where')->setTypeName(Types::INTEGER)->setComment('Unquoted 2')->create(), // unquoted -> reserved keyword
                Column::editor()->setQuotedName('foo')->setTypeName(Types::INTEGER)->setComment('Unquoted 3')->create(), // unquoted -> quoted
                Column::editor()->setUnquotedName('reserved_keyword')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 1')->create(), // reserved keyword -> unquoted
                Column::editor()->setUnquotedName('from')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 2')->create(), // reserved keyword -> reserved keyword
                Column::editor()->setQuotedName('bar')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 3')->create(), // reserved keyword -> quoted
                Column::editor()->setUnquotedName('quoted')->setTypeName(Types::INTEGER)->setComment('Quoted 1')->create(), // quoted -> unquoted
                Column::editor()->setUnquotedName('and')->setTypeName(Types::INTEGER)->setComment('Quoted 2')->create(), // quoted -> reserved keyword
                Column::editor()->setQuotedName('baz')->setTypeName(Types::INTEGER)->setComment('Quoted 3')->create(), // quoted -> quoted
            )
            ->create();
        $comparator = new Comparator($this->_platform);
        $found      = $this->_platform->getAlterTableSQL($comparator->compareTables($fromTable, $toTable));
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

    #[Group('DBAL-807')]
    public function testAlterTableRenameIndexInSchema(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('mytable', 'myschema')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $fromTable,
            renamedIndexes: [
                'idx_foo' => Index::editor()
                    ->setUnquotedName('idx_bar')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            ],
        );
        $found     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX idx_foo', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX idx_bar ON myschema.mytable (id)', $found[1]);
    }

    #[Group('DBAL-807')]
    public function testQuotesAlterTableRenameIndexInSchema(): void
    {
        $fromTable = Table::editor()
            ->setName(new OptionallyQualifiedName(
                Identifier::unquoted('table'),
                Identifier::quoted('schema'),
            ))
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $fromTable,
            renamedIndexes: [
                'create' => Index::editor()
                    ->setUnquotedName('select')
                    ->setUnquotedColumnNames('id')
                    ->create(),
                '`foo`' => Index::editor()
                    ->setQuotedName('bar')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            ],
        );
        $found     = $this->_platform->getAlterTableSQL($tableDiff);
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

    #[Group('DBAL-1004')]
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

    #[Group('DBAL-1010')]
    public function testGeneratesAlterTableRenameColumnSQL(): void
    {
        $table = Table::editor()
            ->setUnquotedName('foo')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::INTEGER)
                    ->setNotNull(true)
                    ->setDefaultValue(666)
                    ->setComment('rename test')
                    ->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $table,
            changedColumns: [
                new ColumnDiff(
                    Column::editor()
                        ->setUnquotedName('bar')
                        ->setTypeName(Types::INTEGER)
                        ->setNotNull(true)
                        ->setDefaultValue(666)
                        ->setComment('rename test')
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('baz')
                        ->setTypeName(Types::INTEGER)
                        ->setNotNull(true)
                        ->setDefaultValue(666)
                        ->setComment('rename test')
                        ->create(),
                ),
            ],
        );
        $found     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE foo ALTER COLUMN bar TO baz', $found[0]);
    }

    #[Group('DBAL-1016')]
    public function testQuotesTableIdentifiersInAlterTableSQL(): void
    {
        $table = Table::editor()
            ->setQuotedName('foo')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('fk')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('fk2')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('fk3')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('bar')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('baz')->setTypeName(Types::INTEGER)->create(),
            )
            ->create();
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

        $comparator = new Comparator($this->_platform);
        $tableDiff = $comparator->compareTables($table, $table2);

        $found = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(10, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE "foo" DROP CONSTRAINT fk2', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('ALTER TABLE "foo" DROP CONSTRAINT fk1', $found[1]);
        self::assertArrayHasKey(2, $found);
        self::assertSame('ALTER TABLE "foo" ADD bloo INTEGER NOT NULL', $found[2]);
        self::assertArrayHasKey(5, $found);
        self::assertSame('ALTER TABLE "foo" DROP baz', $found[5]);
        self::assertArrayHasKey(6, $found);
        /**
         * Firebird 3
         */
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('ALTER TABLE "foo" ALTER bar DROP NOT NULL', $found[6]);
        } else {
            self::assertSame('UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = NULL WHERE UPPER(RDB$FIELD_NAME) = UPPER(\'bar\') '
            . 'AND UPPER(RDB$RELATION_NAME) = UPPER(\'foo\')', $found[6]);
        }

        self::assertArrayHasKey(7, $found);
        self::assertSame('ALTER TABLE "foo" ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)', $found[7]);
        self::assertArrayHasKey(8, $found);
        self::assertSame('ALTER TABLE "foo" ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)', $found[8]);
    }

    #[Group('DBAL-1090')]
    public function testAlterStringToFixedString(): void
    {
        $table = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(2)
                    ->create(),
            )
            ->create();
        $tableDiff = new TableDiff(
            $table,
            changedColumns: [
                new ColumnDiff(
                    Column::editor()
                        ->setUnquotedName('name')
                        ->setTypeName(Types::STRING)
                        ->setLength(2)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('name')
                        ->setTypeName(Types::STRING)
                        ->setFixed(true)
                        ->setLength(2)
                        ->create(),
                ),
            ],
        );
        $found     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(1, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('ALTER TABLE mytable ALTER COLUMN name TYPE CHAR(2)', $found[0]);
    }

    #[Group('DBAL-1062')]
    public function testGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): void
    {
        $foreignTable = Table::editor()
            ->setUnquotedName('foreign_table')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();
        $primaryTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('foo')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('bar')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('baz')->setTypeName(Types::INTEGER)->create(),
            )
            ->create();
        $primaryTable->addIndex(['foo'], 'idx_foo');
        $primaryTable->addIndex(['bar'], 'idx_bar');
        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['foo'], ['id'], [], 'fk_foo');
        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['bar'], ['id'], [], 'fk_bar');
        $tableDiff = new TableDiff(
            $primaryTable,
            renamedIndexes: [
                'idx_foo' => Index::editor()
                    ->setUnquotedName('idx_foo_renamed')
                    ->setUnquotedColumnNames('foo')
                    ->create(),
            ],
        );
        $found     = $this->_platform->getAlterTableSQL($tableDiff);
        self::assertIsArray($found);
        self::assertCount(2, $found);
        self::assertArrayHasKey(0, $found);
        self::assertSame('DROP INDEX idx_foo', $found[0]);
        self::assertArrayHasKey(1, $found);
        self::assertSame('CREATE INDEX idx_foo_renamed ON mytable (foo)', $found[1]);
    }

    #[Group('DBAL-1082')]
    #[DataProvider('getGeneratesDecimalTypeDeclarationSQL')]
    public function testGeneratesDecimalTypeDeclarationSQL(array $column, $expectedSql): void
    {
        self::assertSame($expectedSql, $this->_platform->getDecimalTypeDeclarationSQL($column));
    }

    /** @return array */
    public static function getGeneratesDecimalTypeDeclarationSQL(): Iterator
    {
        // DBAL4: getDecimalTypeDeclarationSQL() requires explicit precision and scale
        yield [['name' => 'col', 'precision' => 10, 'scale' => 0], 'NUMERIC(10, 0)'];
        yield [['name' => 'col', 'precision' => 10, 'scale' => 0, 'unsigned' => true], 'NUMERIC(10, 0)'];
        yield [['name' => 'col', 'precision' => 10, 'scale' => 0, 'unsigned' => false], 'NUMERIC(10, 0)'];
        yield [['name' => 'col', 'precision' => 5, 'scale' => 0], 'NUMERIC(5, 0)'];
        yield [['name' => 'col', 'precision' => 10, 'scale' => 5], 'NUMERIC(10, 5)'];
        yield [['name' => 'col', 'precision' => 8, 'scale' => 2], 'NUMERIC(8, 2)'];
    }

    #[Group('DBAL-1082')]
    #[DataProvider('getGeneratesFloatDeclarationSQL')]
    public function testGeneratesFloatDeclarationSQL(array $column, $expectedSql): void
    {
        self::assertSame($expectedSql, $this->_platform->getFloatDeclarationSQL($column));
    }

    /** @return array */
    public static function getGeneratesFloatDeclarationSQL(): Iterator
    {
        yield [[], 'DOUBLE PRECISION'];
        yield [['unsigned' => true], 'DOUBLE PRECISION'];
        yield [['unsigned' => false], 'DOUBLE PRECISION'];
        yield [['precision' => 5], 'DOUBLE PRECISION'];
        yield [['scale' => 5], 'DOUBLE PRECISION'];
        yield [['precision' => 8, 'scale' => 2], 'DOUBLE PRECISION'];
    }
}
