<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnValuesRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function count;
use function implode;
use function sprintf;
use function str_repeat;

/** @template T of AbstractPlatform */
abstract class PlatformTestCase extends TestCase
{
    use VerifyDeprecations;

    /** @var T */
    protected AbstractPlatform $platform;

    private Type|null $backedUpType = null;

    /** @return T */
    abstract public function createPlatform(): AbstractPlatform;

    protected function setUp(): void
    {
        $this->platform = $this->createPlatform();
    }

    public function testQuoteIdentifier(): void
    {
        if ($this->platform instanceof SQLServerPlatform) {
            self::markTestSkipped('Not working this way on mssql.');
        }

        $c = '"'; // Firebird uses double-quote; getIdentifierQuoteCharacter() removed in DBAL4
        self::assertSame($c . 'test' . $c, $this->platform->quoteIdentifier('test'));
        self::assertSame($c . 'test' . $c . '.' . $c . 'test' . $c, $this->platform->quoteIdentifier('test.test'));
        self::assertSame(str_repeat($c, 4), $this->platform->quoteIdentifier($c));
    }

    public function testQuoteSingleIdentifier(): void
    {
        if ($this->platform instanceof SQLServerPlatform) {
            self::markTestSkipped('Not working this way on mssql.');
        }

        $c = '"'; // Firebird uses double-quote; getIdentifierQuoteCharacter() removed in DBAL4
        self::assertSame($c . 'test' . $c, $this->platform->quoteSingleIdentifier('test'));
        self::assertSame($c . 'test.test' . $c, $this->platform->quoteSingleIdentifier('test.test'));
        self::assertSame(str_repeat($c, 4), $this->platform->quoteSingleIdentifier($c));
    }

    #[DataProvider('getReturnsForeignKeyReferentialActionSQL')]
    public function testReturnsForeignKeyReferentialActionSQL(string $action, string $expectedSQL): void
    {
        self::assertSame($expectedSQL, $this->platform->getForeignKeyReferentialActionSQL($action));
    }

    /** @return mixed[][] */
    public static function getReturnsForeignKeyReferentialActionSQL(): Iterator
    {
        yield ['CASCADE', 'CASCADE'];
        yield ['SET NULL', 'SET NULL'];
        yield ['NO ACTION', 'NO ACTION'];
        yield ['RESTRICT', 'RESTRICT'];
        yield ['SET DEFAULT', 'SET DEFAULT'];
        yield ['CaScAdE', 'CASCADE'];
    }

    public function testGetInvalidForeignKeyReferentialActionSQL(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->platform->getForeignKeyReferentialActionSQL('unknown');
    }

    /** @return mixed[][] */
    public static function getGeneratesAdvancedForeignKeyOptionsSQLData(): iterable
    {
        return [
            [[], ''],
            [['onDelete' => 'NO ACTION'], ' ON DELETE NO ACTION'],
            [['onDelete' => 'CASCADE'], ' ON DELETE CASCADE'],
            [['onDelete' => 'SET NULL'], ' ON DELETE SET NULL'],
            [['onDelete' => 'SET DEFAULT'], ' ON DELETE SET DEFAULT'],
            [['onUpdate' => 'NO ACTION'], ' ON UPDATE NO ACTION'],
            [['onUpdate' => 'CASCADE'], ' ON UPDATE CASCADE'],
            [['onUpdate' => 'SET NULL'], ' ON UPDATE SET NULL'],
            [['onUpdate' => 'SET DEFAULT'], ' ON UPDATE SET DEFAULT'],

        ];
    }

    public function testGetUnknownDoctrineMappingType(): void
    {
        $this->expectException(Exception::class);
        $this->platform->getDoctrineTypeMapping('foobar');
    }

    public function testRegisterDoctrineMappingType(): void
    {
        $this->platform->registerDoctrineTypeMapping('foo', Types::INTEGER);
        self::assertSame(Types::INTEGER, $this->platform->getDoctrineTypeMapping('foo'));
    }

    public function testCaseInsensitiveDoctrineTypeMappingFromType(): void
    {
        $type = new class () extends Type {
            /**
             * {@inheritDoc}
             */
            public function getMappedDatabaseTypes(AbstractPlatform $platform): array
            {
                return ['TESTTYPE'];
            }

            public function getName(): string
            {
                return 'testtype';
            }

            /**
             * {@inheritDoc}
             */
            public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
            {
                return $platform->getDecimalTypeDeclarationSQL($column);
            }
        };

        if (Type::hasType($type->getName())) {
            Type::overrideType($type->getName(), $type::class);
        } else {
            Type::addType($type->getName(), $type::class);
        }

        self::assertSame($type->getName(), $this->platform->getDoctrineTypeMapping('TeStTyPe'));
    }

    public function testRegisterUnknownDoctrineMappingType(): void
    {
        $this->expectException(Exception::class);
        $this->platform->registerDoctrineTypeMapping('foo', 'bar');
    }

    public function testCreateWithNoColumns(): void
    {
        $table = new Table('test');

        $this->expectException(Exception::class);
        $this->platform->getCreateTableSQL($table);
    }

    public function testGeneratesTableCreationSql(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setNotNull(true)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test')
                    ->setTypeName(Types::STRING)
                    ->setNotNull(false)
                    ->setLength(255)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertStringEqualsStringIgnoringLineEndings($this->getGenerateTableSql(), $sql[0]);
    }

    abstract public function getGenerateTableSql(): string;

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

        $sql      = $this->platform->getCreateTableSQL($table);
        $expected = $this->getGenerateTableWithMultiColumnUniqueIndexSql();

        self::assertCount(count($expected), $sql);
        foreach ($sql as $i => $query) {
            self::assertStringEqualsStringIgnoringLineEndings($expected[$i], $query);
        }
    }

    /** @return string[] */
    abstract public function getGenerateTableWithMultiColumnUniqueIndexSql(): array;

    public function testGeneratesIndexCreationSql(): void
    {
        $indexDef = Index::editor()
            ->setUnquotedName('my_idx')
            ->setUnquotedColumnNames('user_name', 'last_login')
            ->create();

        self::assertStringEqualsStringIgnoringLineEndings($this->getGenerateIndexSql(), $this->platform->getCreateIndexSQL($indexDef, 'mytable'));
    }

    abstract public function getGenerateIndexSql(): string;

    public function testGeneratesUniqueIndexCreationSql(): void
    {
        $indexDef = Index::editor()
            ->setUnquotedName('index_name')
            ->setUnquotedColumnNames('test', 'test2')
            ->setType(IndexType::UNIQUE)
            ->create();

        $sql = $this->platform->getCreateIndexSQL($indexDef, 'test');
        self::assertStringEqualsStringIgnoringLineEndings($this->getGenerateUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateUniqueIndexSql(): string;

    public function testGeneratesPartialIndexesSqlOnlyWhenSupportingPartialIndexes(): void
    {
        $where            = 'test IS NULL AND test2 IS NOT NULL';
        $indexDef         = Index::editor()
            ->setUnquotedName('name')
            ->setUnquotedColumnNames('test', 'test2')
            ->setPredicate($where)
            ->create();
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('name')
            ->setUnquotedColumnNames('test', 'test2')
            ->create();

        $expected = ' WHERE ' . $where;

        $indexes = [];

        if ($this->supportsInlineIndexDeclaration()) {
            $indexes[] = $this->platform->getIndexDeclarationSQL($indexDef);
        }

        $uniqueConstraintSQL = $this->platform->getUniqueConstraintDeclarationSQL($uniqueConstraint);
        self::assertStringEndsNotWith($expected, $uniqueConstraintSQL, 'WHERE clause should NOT be present');

        $indexes[] = $this->platform->getCreateIndexSQL($indexDef, 'table');

        foreach ($indexes as $index) {
            if ($this->platform->supportsPartialIndexes()) {
                self::assertStringEndsWith($expected, $index, 'WHERE clause should be present');
            } else {
                self::assertStringEndsNotWith($expected, $index, 'WHERE clause should NOT be present');
            }
        }
    }

    public function testGeneratesForeignKeyCreationSql(): void
    {
        $fk = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('fk_name_id')
            ->setUnquotedReferencedTableName('other_table')
            ->setUnquotedReferencedColumnNames('id')
            ->create();

        $sql = $this->platform->getCreateForeignKeySQL($fk, 'test');
        self::assertStringEqualsStringIgnoringLineEndings($this->getGenerateForeignKeySql(), $sql);
    }

    abstract protected function getGenerateForeignKeySql(): string;

    public function testGeneratesConstraintCreationSql(): void
    {
        $this->markTestSkipped('DBAL4: getCreateConstraintSQL() was removed from AbstractPlatform.');
    }

    protected function getBitAndComparisonExpressionSql(string $value1, string $value2): string
    {
        return 'BIN_AND (' . $value1 . ', ' . $value2 . ')';
    }

    public function testGeneratesBitAndComparisonExpressionSql(): void
    {
        $sql = $this->platform->getBitAndComparisonExpression('2', '4');
        self::assertEquals($this->getBitAndComparisonExpressionSql('2', '4'), $sql);
    }

    protected function getBitOrComparisonExpressionSql(string $value1, string $value2): string
    {
        return sprintf('BIN_OR (%s, %s)', $value1, $value2);
    }

    public function testGeneratesBitOrComparisonExpressionSql(): void
    {
        $sql = $this->platform->getBitOrComparisonExpression('2', '4');
        self::assertEquals($this->getBitOrComparisonExpressionSql('2', '4'), $sql);
    }

    public function getGenerateConstraintUniqueIndexSql(): string
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name UNIQUE (test)';
    }

    public function getGenerateConstraintPrimaryIndexSql(): string
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name PRIMARY KEY (test)';
    }

    public function getGenerateConstraintForeignKeySql(ForeignKeyConstraint $fk): string
    {
        $quotedForeignTable = $fk->getQuotedForeignTableName($this->platform);

        return sprintf(
            'ALTER TABLE test ADD CONSTRAINT constraint_fk FOREIGN KEY (fk_name) REFERENCES %s (id)',
            $quotedForeignTable,
        );
    }

    public function testGetCustomColumnDeclarationSql(): void
    {
        self::assertSame('foo MEDIUMINT(6) UNSIGNED', $this->platform->getColumnDeclarationSQL('foo', ['columnDefinition' => 'MEDIUMINT(6) UNSIGNED']));
    }

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
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        self::assertEquals($this->getCreateTableColumnCommentsSQL(), $this->platform->getCreateTableSQL($table));
    }

    public function testAlterTableColumnComments(): void
    {
        $oldTable  = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('foo')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('bar')->setTypeName(Types::INTEGER)->create(),
            )
            ->create();
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
                    Column::editor()->setUnquotedName('foo')->setTypeName(Types::INTEGER)->create(),
                    Column::editor()->setUnquotedName('foo')->setTypeName(Types::STRING)->create(),
                ),
                new ColumnDiff(
                    Column::editor()->setUnquotedName('bar')->setTypeName(Types::INTEGER)->create(),
                    Column::editor()
                        ->setUnquotedName('baz')
                        ->setTypeName(Types::STRING)
                        ->setComment('B comment')
                        ->create(),
                ),
            ],
        );

        self::assertEquals($this->getAlterTableColumnCommentsSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /** @return string[] */
    public function getCreateTableColumnCommentsSQL(): array
    {
        self::markTestSkipped('Platform does not support Column comments.');
    }

    /** @return string[] */
    public function getAlterTableColumnCommentsSQL(): array
    {
        self::markTestSkipped('Platform does not support Column comments.');
    }

    /** @return string[] */
    public function getCreateTableColumnTypeCommentsSQL(): array
    {
        self::markTestSkipped('Platform does not support Column comments.');
    }

    public function testGetDefaultValueDeclarationSQL(): void
    {
        // non-timestamp value will get single quotes
        self::assertSame(" DEFAULT 'non_timestamp'", $this->platform->getDefaultValueDeclarationSQL([
            'type' => Type::getType(Types::STRING),
            'default' => 'non_timestamp',
        ]));
    }

    public function testGetDefaultValueDeclarationSQLDateTime(): void
    {
        $types = [
            Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_MUTABLE,
            Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_IMMUTABLE,
        ];
        // timestamps on datetime types should not be quoted
        foreach ($types as $type) {
            self::assertSame(' DEFAULT ' . $this->platform->getCurrentTimestampSQL(), $this->platform->getDefaultValueDeclarationSQL([
                'type'    => Type::getType($type),
                'default' => $this->platform->getCurrentTimestampSQL(),
            ]));
        }
    }

    public function testGetDefaultValueDeclarationSQLForIntegerTypes(): void
    {
        foreach ([Types::BIGINT, Types::INTEGER, Types::SMALLINT] as $type) {
            self::assertSame(' DEFAULT 1', $this->platform->getDefaultValueDeclarationSQL([
                'type'    => Type::getType($type),
                'default' => 1,
            ]));
        }
    }

    public function testGetDefaultValueDeclarationSQLForDateType(): void
    {
        $currentDateSql = $this->platform->getCurrentDateSQL();
        foreach ([Types::DATE_MUTABLE, Types::DATE_IMMUTABLE] as $type) {
            self::assertSame(' DEFAULT ' . $currentDateSql, $this->platform->getDefaultValueDeclarationSQL([
                'type'    => Type::getType($type),
                'default' => $currentDateSql,
            ]));
        }
    }

    public function testKeywordList(): void
    {
        $keywordList = $this->platform->getReservedKeywordsList();
        self::assertInstanceOf(KeywordList::class, $keywordList);

        self::assertTrue($keywordList->isKeyword('table'));
    }

    public function testQuotedColumnInPrimaryKeyPropagation(): void
    {
        $table = Table::editor()
            ->setQuotedName('quoted')
            ->setColumns(
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('create')->create(),
            )
            ->create();

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInPrimaryKeySQL(), $sql);
    }

    /** @return string[] */
    abstract protected function getQuotedColumnInPrimaryKeySQL(): array;

    /** @return string[] */
    abstract protected function getQuotedColumnInIndexSQL(): array;

    /** @return string[] */
    abstract protected function getQuotedNameInIndexSQL(): array;

    /** @return string[] */
    abstract protected function getQuotedColumnInForeignKeySQL(): array;

    public function testQuotedColumnInIndexPropagation(): void
    {
        $table = Table::editor()
            ->setQuotedName('quoted')
            ->setColumns(
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->create(),
            )
            ->create();
        $table->addIndex(['create']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInIndexSQL(), $sql);
    }

    public function testQuotedNameInIndexSQL(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test')
            ->setColumns(
                Column::editor()->setUnquotedName('column1')->setTypeName(Types::STRING)->create(),
            )
            ->create();
        $table->addIndex(['column1'], '`key`');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedNameInIndexSQL(), $sql);
    }

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

        // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable->getQuotedName($this->platform),
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

        // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable->getQuotedName($this->platform),
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

        // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable->getQuotedName($this->platform),
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_INTENDED_QUOTATION',
        );

        $sql = $this->platform->getCreateTableSQL(
            $table,
            AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS,
        );
        self::assertEquals($this->getQuotedColumnInForeignKeySQL(), $sql);
    }

    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): void
    {
        $constraint = UniqueConstraint::editor()
            ->setUnquotedName('select')
            ->setUnquotedColumnNames('foo')
            ->create();

        self::assertSame($this->getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(), $this->platform->getUniqueConstraintDeclarationSQL($constraint));
    }

    abstract protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): string;

    public function testQuotesReservedKeywordInTruncateTableSQL(): void
    {
        self::assertSame($this->getQuotesReservedKeywordInTruncateTableSQL(), $this->platform->getTruncateTableSQL('select'));
    }

    abstract protected function getQuotesReservedKeywordInTruncateTableSQL(): string;

    public function testQuotesReservedKeywordInIndexDeclarationSQL(): void
    {
        $index = Index::editor()
            ->setUnquotedName('select')
            ->setUnquotedColumnNames('foo')
            ->create();

        if (! $this->supportsInlineIndexDeclaration()) {
            $this->expectException(Exception::class);
        }

        self::assertSame($this->getQuotesReservedKeywordInIndexDeclarationSQL(), $this->platform->getIndexDeclarationSQL($index));
    }

    abstract protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string;

    protected function supportsInlineIndexDeclaration(): bool
    {
        return true;
    }

    public function testSupportsCommentOnStatement(): void
    {
        self::assertSame($this->supportsCommentOnStatement(), $this->platform->supportsCommentOnStatement());
    }

    protected function supportsCommentOnStatement(): bool
    {
        return false;
    }

    public function testGetCreateSchemaSQL(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getCreateSchemaSQL('schema');
    }

    public function testAlterTableChangeQuotedColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('select')->setTypeName(Types::INTEGER)->create(),
            )
            ->create();

        $tableDiff = new TableDiff(
            $table,
            changedColumns: [
                new ColumnDiff(
                    Column::editor()->setUnquotedName('select')->setTypeName(Types::INTEGER)->create(),
                    Column::editor()->setUnquotedName('select')->setTypeName(Types::STRING)->create(),
                ),
            ],
        );

        self::assertStringContainsString($this->platform->quoteIdentifier('select'), implode(';', $this->platform->getAlterTableSQL($tableDiff)));
    }

    public function testUsesSequenceEmulatedIdentityColumns(): void
    {
        self::assertFalse($this->platform->usesSequenceEmulatedIdentityColumns());
    }

    #[Group('DBAL-563')]
    public function testReturnsIdentitySequenceName(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getIdentitySequenceName('mytable', 'mycolumn');
    }

    public function testReturnsBinaryDefaultLength(): void
    {
        self::assertSame($this->getBinaryDefaultLength(), $this->platform->getBinaryDefaultLength());
    }

    protected function getBinaryDefaultLength(): int
    {
        return 255;
    }

    public function testReturnsBinaryMaxLength(): void
    {
        self::assertSame($this->getBinaryMaxLength(), $this->platform->getBinaryMaxLength());
    }

    protected function getBinaryMaxLength(): int
    {
        return 8191;
    }

    public function testReturnsBinaryTypeDeclarationSQL(): void
    {
        self::assertSame('VARCHAR(255)', $this->platform->getBinaryTypeDeclarationSQL([]));
        self::assertSame('VARCHAR(8191)', $this->platform->getBinaryTypeDeclarationSQL(['length' => 0]));
        self::assertSame('VARCHAR(2000)', $this->platform->getBinaryTypeDeclarationSQL(['length' => 2000]));

        self::assertSame('CHAR(255)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true]));
        self::assertSame('CHAR(8191)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 0]));

        self::assertSame('CHAR(2000)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 2000]));
    }

    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL(): void
    {
        $this->markTestSkipped('Not applicable to the platform');
    }

    public function hasNativeJsonType(): void
    {
        self::assertFalse($this->platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        $column = [
            'length'  => 666,
            'notnull' => true,
            'type'    => Type::getType(Types::JSON),
        ];

        self::assertSame($this->platform->getClobTypeDeclarationSQL($column), $this->platform->getJsonTypeDeclarationSQL($column));
    }

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

        self::assertSame($this->getAlterTableRenameIndexSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return [
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON mytable (id)',
        ];
    }

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

        self::assertSame($this->getQuotedAlterTableRenameIndexSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            'DROP INDEX "create"',
            'CREATE INDEX "select" ON "table" (id)',
            'DROP INDEX "foo"',
            'CREATE INDEX "bar" ON "table" (id)',
        ];
    }

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
                // unquoted -> unquoted
                Column::editor()->setUnquotedName('unquoted')->setTypeName(Types::INTEGER)->setComment('Unquoted 1')->create(),
                // unquoted -> reserved keyword
                Column::editor()->setUnquotedName('where')->setTypeName(Types::INTEGER)->setComment('Unquoted 2')->create(),
                // unquoted -> quoted
                Column::editor()->setQuotedName('foo')->setTypeName(Types::INTEGER)->setComment('Unquoted 3')->create(),
                // reserved keyword -> unquoted
                Column::editor()->setUnquotedName('reserved_keyword')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 1')->create(),
                // reserved keyword -> reserved keyword
                Column::editor()->setUnquotedName('from')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 2')->create(),
                // reserved keyword -> quoted
                Column::editor()->setQuotedName('bar')->setTypeName(Types::INTEGER)->setComment('Reserved keyword 3')->create(),
                // quoted -> unquoted
                Column::editor()->setUnquotedName('quoted')->setTypeName(Types::INTEGER)->setComment('Quoted 1')->create(),
                // quoted -> reserved keyword
                Column::editor()->setUnquotedName('and')->setTypeName(Types::INTEGER)->setComment('Quoted 2')->create(),
                // quoted -> quoted
                Column::editor()->setQuotedName('baz')->setTypeName(Types::INTEGER)->setComment('Quoted 3')->create(),
            )
            ->create();

        // DBAL4: diffTable() → compareTables() which always returns TableDiff
        $diff = (new Comparator($this->platform))->compareTables($fromTable, $toTable);

        self::assertEquals($this->getQuotedAlterTableRenameColumnSQL(), $this->platform->getAlterTableSQL($diff));
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableRenameColumn}.
     *
     * @return string[]
     */
    abstract protected function getQuotedAlterTableRenameColumnSQL(): array;

    public function testQuotesAlterTableChangeColumnLength(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('unquoted1')->setTypeName(Types::STRING)->setComment('Unquoted 1')->setLength(10)->create(),
                Column::editor()->setUnquotedName('unquoted2')->setTypeName(Types::STRING)->setComment('Unquoted 2')->setLength(10)->create(),
                Column::editor()->setUnquotedName('unquoted3')->setTypeName(Types::STRING)->setComment('Unquoted 3')->setLength(10)->create(),
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->setComment('Reserved keyword 1')->setLength(10)->create(),
                Column::editor()->setUnquotedName('table')->setTypeName(Types::STRING)->setComment('Reserved keyword 2')->setLength(10)->create(),
                Column::editor()->setUnquotedName('select')->setTypeName(Types::STRING)->setComment('Reserved keyword 3')->setLength(10)->create(),
            )
            ->create();

        $toTable = Table::editor()
            ->setUnquotedName('mytable')
            ->setColumns(
                Column::editor()->setUnquotedName('unquoted1')->setTypeName(Types::STRING)->setComment('Unquoted 1')->setLength(255)->create(),
                Column::editor()->setUnquotedName('unquoted2')->setTypeName(Types::STRING)->setComment('Unquoted 2')->setLength(255)->create(),
                Column::editor()->setUnquotedName('unquoted3')->setTypeName(Types::STRING)->setComment('Unquoted 3')->setLength(255)->create(),
                Column::editor()->setUnquotedName('create')->setTypeName(Types::STRING)->setComment('Reserved keyword 1')->setLength(255)->create(),
                Column::editor()->setUnquotedName('table')->setTypeName(Types::STRING)->setComment('Reserved keyword 2')->setLength(255)->create(),
                Column::editor()->setUnquotedName('select')->setTypeName(Types::STRING)->setComment('Reserved keyword 3')->setLength(255)->create(),
            )
            ->create();

        // DBAL4: diffTable() → compareTables() which always returns TableDiff
        $diff = (new Comparator($this->platform))->compareTables($fromTable, $toTable);

        self::assertEquals($this->getQuotedAlterTableChangeColumnLengthSQL(), $this->platform->getAlterTableSQL($diff));
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableChangeColumnLength}.
     *
     * @return string[]
     */
    abstract protected function getQuotedAlterTableChangeColumnLengthSQL(): array;

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

        self::assertSame($this->getAlterTableRenameIndexInSchemaSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON myschema.mytable (id)',
        ];
    }

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

        self::assertSame($this->getQuotedAlterTableRenameIndexInSchemaSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'DROP INDEX "create"',
            'CREATE INDEX "select" ON "schema"."table" (id)',
            'DROP INDEX "foo"',
            'CREATE INDEX "bar" ON "schema"."table" (id)',
        ];
    }

    public function testQuotesDropConstraintSQL(): void
    {
        $this->markTestSkipped('DBAL4: getDropConstraintSQL() is now protected in AbstractPlatform.');
    }

    protected function getQuotesDropConstraintSQL(): string
    {
        return 'ALTER TABLE "table" DROP CONSTRAINT "select"';
    }

    protected function getStringLiteralQuoteCharacter(): string
    {
        return "'";
    }

    public function testGetStringLiteralQuoteCharacter(): void
    {
        $this->markTestSkipped('DBAL4: getStringLiteralQuoteCharacter() was removed from AbstractPlatform.');
    }

    protected function getQuotedCommentOnColumnSQLWithoutQuoteCharacter(): string
    {
        return "COMMENT ON COLUMN mytable.id IS 'This is a comment'";
    }

    public function testGetCommentOnColumnSQLWithoutQuoteCharacter(): void
    {
        self::assertSame($this->getQuotedCommentOnColumnSQLWithoutQuoteCharacter(), $this->platform->getCommentOnColumnSQL('mytable', 'id', 'This is a comment'));
    }

    protected function getQuotedCommentOnColumnSQLWithQuoteCharacter(): string
    {
        return "COMMENT ON COLUMN mytable.id IS 'It''s a quote !'";
    }

    public function testGetCommentOnColumnSQLWithQuoteCharacter(): void
    {
        $c = $this->getStringLiteralQuoteCharacter();

        self::assertSame($this->getQuotedCommentOnColumnSQLWithQuoteCharacter(), $this->platform->getCommentOnColumnSQL('mytable', 'id', 'It' . $c . 's a quote !'));
    }

    #[DataProvider('getGeneratesInlineColumnCommentSQL')]
    public function testGeneratesInlineColumnCommentSQL(string $comment, string $expectedSql): void
    {
        if (! $this->platform->supportsInlineColumnComments()) {
            self::markTestSkipped('Platform does not support inline column comments.');
        }

        self::assertSame($expectedSql, $this->platform->getInlineColumnCommentSQL($comment));
    }

    /** @return mixed[][] */
    public static function getGeneratesInlineColumnCommentSQL(): Iterator
    {
        yield 'regular comment' => ['Regular comment', static::getInlineColumnRegularCommentSQL()];
        yield 'comment requiring escaping' => [
            sprintf(
                'Using inline comment delimiter %s works',
                static::getInlineColumnCommentDelimiter(),
            ),
            static::getInlineColumnCommentRequiringEscapingSQL(),
        ];

        yield 'empty comment' => ['', static::getInlineColumnEmptyCommentSQL()];
    }

    protected static function getInlineColumnCommentDelimiter(): string
    {
        return "'";
    }

    protected static function getInlineColumnRegularCommentSQL(): string
    {
        return "COMMENT 'Regular comment'";
    }

    protected static function getInlineColumnCommentRequiringEscapingSQL(): string
    {
        return "COMMENT 'Using inline comment delimiter '' works'";
    }

    protected static function getInlineColumnEmptyCommentSQL(): string
    {
        return "COMMENT ''";
    }

    protected function getQuotedStringLiteralWithoutQuoteCharacter(): string
    {
        return "'No quote'";
    }

    protected function getQuotedStringLiteralWithQuoteCharacter(): string
    {
        return "'It''s a quote'";
    }

    protected function getQuotedStringLiteralQuoteCharacter(): string
    {
        return "''''";
    }

    public function testThrowsExceptionOnGeneratingInlineColumnCommentSQLIfUnsupported(): void
    {
        if ($this->platform->supportsInlineColumnComments()) {
            self::markTestSkipped(sprintf('%s supports inline column comments.', $this->platform::class));
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Operation "' . AbstractPlatform::class . '::getInlineColumnCommentSQL" is not supported by platform.',
        );
        $this->expectExceptionCode(0);

        $this->platform->getInlineColumnCommentSQL('unsupported');
    }

    public function testQuoteStringLiteral(): void
    {
        $c = $this->getStringLiteralQuoteCharacter();

        self::assertSame($this->getQuotedStringLiteralWithoutQuoteCharacter(), $this->platform->quoteStringLiteral('No quote'));
        self::assertSame($this->getQuotedStringLiteralWithQuoteCharacter(), $this->platform->quoteStringLiteral('It' . $c . 's a quote'));
        self::assertSame($this->getQuotedStringLiteralQuoteCharacter(), $this->platform->quoteStringLiteral($c));
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getGuidTypeDeclarationSQL([]);
    }

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

        self::assertSame($this->getAlterTableRenameColumnSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /** @return string[] */
    abstract public function getAlterTableRenameColumnSQL(): array;

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

        $sql = $this->platform->getAlterTableSQL($tableDiff);

        $expected = $this->getAlterStringToFixedStringSQL();

        self::assertCount(count($expected), $sql);
        foreach ($sql as $i => $query) {
            self::assertStringEqualsStringIgnoringLineEndings($expected[$i], $query);
        }
    }

    /** @return string[] */
    abstract protected function getAlterStringToFixedStringSQL(): array;

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

        $sql      = $this->platform->getAlterTableSQL($tableDiff);
        $expected = $this->getGeneratesAlterTableRenameIndexUsedByForeignKeySQL();

        self::assertCount(count($expected), $sql);
        foreach ($sql as $i => $query) {
            self::assertStringEqualsStringIgnoringLineEndings($expected[$i], $query);
        }
    }

    /** @return string[] */
    abstract protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array;

    /** @param mixed[] $column */
    #[DataProvider('getGeneratesDecimalTypeDeclarationSQL')]
    public function testGeneratesDecimalTypeDeclarationSQL(array $column, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->platform->getDecimalTypeDeclarationSQL($column));
    }

    /** @return mixed[][] */
    public static function getGeneratesDecimalTypeDeclarationSQL(): Iterator
    {
        yield [['name' => 'col', 'precision' => 10, 'scale' => 0], 'NUMERIC(10, 0)'];
        yield [['name' => 'col', 'unsigned' => true, 'precision' => 10, 'scale' => 0], 'NUMERIC(10, 0)'];
        yield [['name' => 'col', 'unsigned' => false, 'precision' => 10, 'scale' => 0], 'NUMERIC(10, 0)'];
        yield [['name' => 'col', 'precision' => 5, 'scale' => 0], 'NUMERIC(5, 0)'];
        yield [['name' => 'col', 'precision' => 10, 'scale' => 5], 'NUMERIC(10, 5)'];
        yield [['name' => 'col', 'precision' => 8, 'scale' => 2], 'NUMERIC(8, 2)'];
    }

    /** @param mixed[] $column */
    #[DataProvider('getGeneratesFloatDeclarationSQL')]
    public function testGeneratesFloatDeclarationSQL(array $column, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->platform->getFloatDeclarationSQL($column));
    }

    /** @return mixed[][] */
    public static function getGeneratesFloatDeclarationSQL(): Iterator
    {
        yield [[], 'DOUBLE PRECISION'];
        yield [['unsigned' => true], 'DOUBLE PRECISION'];
        yield [['unsigned' => false], 'DOUBLE PRECISION'];
        yield [['precision' => 5], 'DOUBLE PRECISION'];
        yield [['scale' => 5], 'DOUBLE PRECISION'];
        yield [['precision' => 8, 'scale' => 2], 'DOUBLE PRECISION'];
    }

    public function testItEscapesStringsForLike(): void
    {
        self::assertSame('\_25\% off\_ your next purchase \\\\o/', $this->platform->escapeStringForLike('_25% off_ your next purchase \o/', '\\'));
    }

    public function testZeroOffsetWithoutLimitIsIgnored(): void
    {
        $query = 'SELECT * FROM user';

        self::assertSame($query, $this->platform->modifyLimitQuery($query, null, 0));
    }

    public function testLimitOffsetCastToInt(): void
    {
        $this->markTestSkipped('DBAL4: modifyLimitQuery() requires ?int for $limit - string cast no longer supported.');
    }

    protected function getLimitOffsetCastToIntExpectedQuery(): string
    {
        return 'SELECT * FROM user LIMIT 1 OFFSET 2';
    }

    /** @param array<string, mixed> $column */
    #[DataProvider('asciiStringSqlDeclarationDataProvider')]
    public function testAsciiSQLDeclaration(string $expectedSql, array $column): void
    {
        $declarationSql = $this->platform->getAsciiStringTypeDeclarationSQL($column);
        self::assertSame($expectedSql, $declarationSql);
    }

    /** @return array<int, array{string, array<string, mixed>}> */
    public static function asciiStringSqlDeclarationDataProvider(): Iterator
    {
        yield ['VARCHAR(12)', ['length' => 12]];
        yield ['CHAR(12)', ['length' => 12, 'fixed' => true]];
    }

    public function testGetName(): void
    {
        self::assertStringEndsWith($this->platform->getName() . 'Platform', $this->platform::class);
    }

    public function testItAddsCommentsForOverridingTypes(): void
    {
        $this->markTestSkipped('DBAL4: requiresSQLCommentHint() was removed from Type.');
    }

    public function testEmptyTableDiff(): void
    {
        $diff = new TableDiff(new Table('test'));

        self::assertTrue($diff->isEmpty());
        self::assertSame([], $this->platform->getAlterTableSQL($diff));
    }

    public function testEmptySchemaDiff(): void
    {
        $diff = new SchemaDiff([], [], [], [], [], [], [], []);

        self::assertTrue($diff->isEmpty());
        self::assertSame([], $this->platform->getAlterSchemaSQL($diff));
    }

    public function testColumnComparison(): void
    {
        $this->markTestSkipped('DBAL4: columnsEqual() type comment behavior differs; columns with same SQL type are always equal.');
    }

    /** @param array<string, mixed> $column */
    #[DataProvider('getGeneratesSmallFloatDeclarationSQL')]
    public function testGeneratesSmallFloatDeclarationSQL(array $column, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->platform->getSmallFloatDeclarationSQL($column));
    }

    /** @return list<array{array<string, mixed>, string}> */
    public static function getGeneratesSmallFloatDeclarationSQL(): iterable
    {
        return [
            [[], 'REAL'],
            [['unsigned' => true], 'REAL'],
            [['unsigned' => false], 'REAL'],
            [['precision' => 5], 'REAL'],
            [['scale' => 5], 'REAL'],
            [['precision' => 4, 'scale' => 2], 'REAL'],
        ];
    }

    /** @param array<string> $values */
    #[DataProvider('getEnumDeclarationSQLProvider')]
    public function testGetEnumDeclarationSQL(array $values, string $expectedSQL): void
    {
        self::assertSame($expectedSQL, $this->platform->getEnumDeclarationSQL(['values' => $values]));
    }

    /** @return array<string, array{array<string>, string}> */
    public static function getEnumDeclarationSQLProvider(): array
    {
        return [
            'single value' => [['foo'], 'VARCHAR(3)'],
            'multiple values' => [['foo', 'bar1'], 'VARCHAR(4)'],
        ];
    }

    /** @param array<string> $values */
    #[DataProvider('getEnumDeclarationWithLengthSQLProvider')]
    public function testGetEnumDeclarationWithLengthSQL(array $values, int $length, string $expectedSQL): void
    {
        $result = $this->platform->getEnumDeclarationSQL([
            'values' => $values,
            'length' => $length,
        ]);

        self::assertSame($expectedSQL, $result);
    }

    /** @param array<string> $values */
    #[DataProvider('getEnumDeclarationExceptionWithLengthSQLProvider')]
    public function testGetEnumDeclarationExceptionWithLengthSQL(array $values, int $length): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->platform->getEnumDeclarationSQL([
            'values' => $values,
            'length' => $length,
        ]);
    }

    /** @return array<string, array{array<string>, int, string}> */
    public static function getEnumDeclarationWithLengthSQLProvider(): array
    {
        return [
            'single value and bigger length' => [['foo'], 42, 'VARCHAR(42)'],
            'multiple values and bigger length' => [['foo', 'bar1'], 42, 'VARCHAR(42)'],
        ];
    }

    /** @return array<string, array{array<string>, int}> */
    public static function getEnumDeclarationExceptionWithLengthSQLProvider(): array
    {
        return [
            'single value and lower length' => [['foo'], 1],
            'multiple values and lower length' => [['foo', 'bar1'], 2],
        ];
    }

    /** @param array<string, mixed> $column */
    #[DataProvider('getEnumDeclarationSQLWithInvalidValuesProvider')]
    public function testGetEnumDeclarationSQLWithInvalidValues(array $column): void
    {
        self::expectException(ColumnValuesRequired::class);
        $this->platform->getEnumDeclarationSQL($column);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function getEnumDeclarationSQLWithInvalidValuesProvider(): array
    {
        return [
            "field 'values' does not exist" => [[]],
            "field 'values' is not an array" => [['values' => 'foo']],
            "field 'values' is an empty array" => [['values' => []]],
        ];
    }

    public function testGetDecimalTypeDeclarationSQLNoPrecision(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);
        $this->platform->getDecimalTypeDeclarationSQL(['name' => 'price', 'scale' => 2]);
    }

    public function testGetDecimalTypeDeclarationSQLNoScale(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);
        $this->platform->getDecimalTypeDeclarationSQL(['name' => 'price', 'precision' => 10]);
    }

    public function testReturnsJsonbTypeDeclarationSQL(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6939');

        self::assertSame(
            $this->platform->getJsonTypeDeclarationSQL(['jsonb' => true]),
            $this->platform->getJsonbTypeDeclarationSQL([]),
        );
    }

    public function testGetFixedLengthStringTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthStringTypeDeclarationSQLNoLength(),
            $this->platform->getStringTypeDeclarationSQL(['fixed' => true]),
        );
    }

    protected function getExpectedFixedLengthStringTypeDeclarationSQLNoLength(): string
    {
        // jane: Firebird pads null-length CHAR to 255 (default) instead of bare 'CHAR'.
        return 'CHAR(255)';
    }

    public function testGetFixedLengthStringTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthStringTypeDeclarationSQLWithLength(),
            $this->platform->getStringTypeDeclarationSQL([
                'fixed' => true,
                'length' => 16,
            ]),
        );
    }

    protected function getExpectedFixedLengthStringTypeDeclarationSQLWithLength(): string
    {
        return 'CHAR(16)';
    }

    public function testGetVariableLengthStringTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthStringTypeDeclarationSQLNoLength(),
            $this->platform->getStringTypeDeclarationSQL(['name' => 'email']),
        );
    }

    protected function getExpectedVariableLengthStringTypeDeclarationSQLNoLength(): string
    {
        // jane: Firebird pads null-length VARCHAR to 255 (default) instead of bare 'VARCHAR'.
        return 'VARCHAR(255)';
    }

    public function testGetVariableLengthStringTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthStringTypeDeclarationSQLWithLength(),
            $this->platform->getStringTypeDeclarationSQL(['length' => 16]),
        );
    }

    protected function getExpectedVariableLengthStringTypeDeclarationSQLWithLength(): string
    {
        return 'VARCHAR(16)';
    }

    public function testGetFixedLengthBinaryTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthBinaryTypeDeclarationSQLNoLength(),
            $this->platform->getBinaryTypeDeclarationSQL(['name' => 'checksum', 'fixed' => true]),
        );
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        // jane: Firebird maps fixed binary to CHAR(255) (no native BINARY type).
        return 'CHAR(255)';
    }

    public function testGetFixedLengthBinaryTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthBinaryTypeDeclarationSQLWithLength(),
            $this->platform->getBinaryTypeDeclarationSQL([
                'fixed' => true,
                'length' => 16,
            ]),
        );
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        // jane: Firebird maps fixed binary to CHAR(n) (no native BINARY type).
        return 'CHAR(16)';
    }

    public function testGetVariableLengthBinaryTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthBinaryTypeDeclarationSQLNoLength(),
            $this->platform->getBinaryTypeDeclarationSQL(['name' => 'attachment']),
        );
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        // jane: Firebird maps variable binary to VARCHAR(255) (no native VARBINARY type).
        return 'VARCHAR(255)';
    }

    public function testGetVariableLengthBinaryTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthBinaryTypeDeclarationSQLWithLength(),
            $this->platform->getBinaryTypeDeclarationSQL(['length' => 16]),
        );
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        // jane: Firebird maps variable binary to VARCHAR(n) (no native VARBINARY type).
        return 'VARCHAR(16)';
    }

    /**
     * @see testGetCommentOnColumnSQL
     *
     * @return string[]
     */
    protected function getCommentOnColumnSQL(): array
    {
        return [
            'COMMENT ON COLUMN foo.bar IS \'comment\'',
            'COMMENT ON COLUMN "Foo"."BAR" IS \'comment\'',
            'COMMENT ON COLUMN "select"."from" IS \'comment\'',
        ];
    }

    public function testGetCommentOnColumnSQL(): void
    {
        self::assertSame(
            $this->getCommentOnColumnSQL(),
            [
                $this->platform->getCommentOnColumnSQL('foo', 'bar', 'comment'), // regular identifiers
                $this->platform->getCommentOnColumnSQL('`Foo`', '`BAR`', 'comment'), // explicitly quoted identifiers
                $this->platform->getCommentOnColumnSQL('select', 'from', 'comment'), // reserved keyword identifiers
            ],
        );
    }

    public function tearDown(): void
    {
        if (! $this->backedUpType instanceof Type) {
            return;
        }

        Type::getTypeRegistry()->override(Types::STRING, $this->backedUpType);
        $this->backedUpType = null;
    }
}
