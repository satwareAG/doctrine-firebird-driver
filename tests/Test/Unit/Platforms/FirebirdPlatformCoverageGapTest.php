<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Event\SchemaAlterTableAddColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

/**
 * Targeted coverage tests for FirebirdPlatform uncovered code paths:
 *  - Boolean conversion (array, null, char mode, no-native-bool paths)
 *  - getCreateSequenceSQL (non-default initial value, error branches)
 *  - getCreateTableSQL (boolean columns without native bool type)
 *  - getDropTableSQL (Table object deprecated input)
 */
#[CoversClass(FirebirdPlatform::class)]
final class FirebirdPlatformCoverageGapTest extends TestCase
{
    /** FirebirdPlatform instance with $hasNativeBooleanType = false (base/legacy behaviour). */
    private FirebirdPlatform $platform;

    /** FirebirdPlatform with char-based booleans ('Y'/'N') instead of SMALLINT. */
    private FirebirdPlatform $charPlatform;

    protected function setUp(): void
    {
        $this->platform = new FirebirdPlatform();

        $this->charPlatform = new FirebirdPlatform();
        $this->charPlatform->setUseSmallIntBoolean(false);
    }

    // -------------------------------------------------------------------------
    // convertBooleans — array and non-bool paths
    // -------------------------------------------------------------------------

    public function testConvertBooleansArrayWithMixedValues(): void
    {
        // Array containing bools AND non-bools → bools converted, non-bools unchanged
        $input  = [true, false, 'text', 42, null];
        $result = $this->platform->convertBooleans($input);

        self::assertSame([1, 0, 'text', 42, null], $result);
    }

    public function testConvertBooleansArrayOnlyBools(): void
    {
        $result = $this->platform->convertBooleans([true, false]);
        self::assertSame([1, 0], $result);
    }

    public function testConvertBooleansArrayEmptyReturnsEmpty(): void
    {
        $result = $this->platform->convertBooleans([]);
        self::assertSame([], $result);
    }

    public function testConvertBooleansNonBoolScalarUnchanged(): void
    {
        // A non-bool scalar passes through untouched
        self::assertSame('hello', $this->platform->convertBooleans('hello'));
        self::assertSame(42, $this->platform->convertBooleans(42));
        self::assertNull($this->platform->convertBooleans(null));
    }

    public function testConvertBooleansTrueToSmallInt(): void
    {
        self::assertSame(1, $this->platform->convertBooleans(true));
    }

    public function testConvertBooleansFalseToSmallInt(): void
    {
        self::assertSame(0, $this->platform->convertBooleans(false));
    }

    public function testConvertBooleansCharModeTrue(): void
    {
        self::assertSame('Y', $this->charPlatform->convertBooleans(true));
    }

    public function testConvertBooleansCharModeFalse(): void
    {
        self::assertSame('N', $this->charPlatform->convertBooleans(false));
    }

    public function testConvertBooleansCharModeArray(): void
    {
        $result = $this->charPlatform->convertBooleans([true, false, 'x']);
        self::assertSame(['Y', 'N', 'x'], $result);
    }

    // -------------------------------------------------------------------------
    // convertFromBoolean — null, SmallInt, and char mode paths
    // -------------------------------------------------------------------------

    public function testConvertFromBooleanNullReturnsNull(): void
    {
        self::assertNull($this->platform->convertFromBoolean(null));
    }

    public function testConvertFromBooleanSmallInt1ReturnsTrue(): void
    {
        // SMALLINT 1 → true
        self::assertTrue($this->platform->convertFromBoolean(1));
    }

    public function testConvertFromBooleanSmallInt0ReturnsFalse(): void
    {
        // SMALLINT 0 → false
        self::assertFalse($this->platform->convertFromBoolean(0));
    }

    public function testConvertFromBooleanCharYReturnsTrue(): void
    {
        self::assertTrue($this->charPlatform->convertFromBoolean('Y'));
    }

    public function testConvertFromBooleanCharNReturnsFalse(): void
    {
        self::assertFalse($this->charPlatform->convertFromBoolean('N'));
    }

    // -------------------------------------------------------------------------
    // convertBooleansToDatabaseValue — non-native-bool path
    // -------------------------------------------------------------------------

    public function testConvertBooleansToDatabaseValueTrue(): void
    {
        // Base platform: delegates to convertBooleans() → SMALLINT
        self::assertSame(1, $this->platform->convertBooleansToDatabaseValue(true));
    }

    public function testConvertBooleansToDatabaseValueFalse(): void
    {
        self::assertSame(0, $this->platform->convertBooleansToDatabaseValue(false));
    }

    public function testConvertBooleansToDatabaseValueCharMode(): void
    {
        self::assertSame('Y', $this->charPlatform->convertBooleansToDatabaseValue(true));
        self::assertSame('N', $this->charPlatform->convertBooleansToDatabaseValue(false));
    }

    // -------------------------------------------------------------------------
    // getCreateSequenceSQL — non-default initial value paths
    // -------------------------------------------------------------------------

    public function testGetCreateSequenceSQLDefaultInitialValue(): void
    {
        $seq = new Sequence('my_seq');
        // Default initialValue=1 → simple CREATE SEQUENCE (name not uppercased by getQuotedName)
        $sql = $this->platform->getCreateSequenceSQL($seq);
        self::assertSame('CREATE SEQUENCE my_seq', $sql);
    }

    public function testGetCreateSequenceSQLWithCustomInitialValue(): void
    {
        $seq = new Sequence('MY_SEQ', 1, 100); // allocationSize=1, initialValue=100
        $sql = $this->platform->getCreateSequenceSQL($seq);

        // Should use EXECUTE BLOCK + CREATE SEQUENCE + SET GENERATOR
        self::assertStringContainsString('CREATE SEQUENCE', $sql);
        self::assertStringContainsString('SET GENERATOR', $sql);
        self::assertStringContainsString('100', $sql);
    }

    public function testGetCreateSequenceSQLThrowsOnAllocationSizeGreaterThanOne(): void
    {
        $seq = new Sequence('MY_SEQ', 5, 100); // allocationSize=5 → unsupported

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('allocation size > 1');

        $this->platform->getCreateSequenceSQL($seq);
    }

    public function testGetCreateSequenceSQLThrowsOnCacheNotNull(): void
    {
        // Create sequence with allocationSize=1, initialValue=5, cache=10
        $seq = new Sequence('MY_SEQ', 1, 5, 10);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('with cache not null');

        $this->platform->getCreateSequenceSQL($seq);
    }

    // -------------------------------------------------------------------------
    // getCreateTableSQL — boolean column without native bool type
    // -------------------------------------------------------------------------

    public function testGetCreateTableSQLWithBooleanColumnSmallInt(): void
    {
        // Base platform: bool column stored as SMALLINT
        $table = new Table('test_bool_table');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('active', Types::BOOLEAN);
        $table->setPrimaryKey(['id']);

        $sql = $this->platform->getCreateTableSQL($table);

        // Should produce a CREATE TABLE with SMALLINT for the boolean column
        $ddl = implode(' ', $sql);
        self::assertStringContainsString('TEST_BOOL_TABLE', $ddl);
        self::assertStringContainsString('SMALLINT', $ddl);
    }

    public function testGetCreateTableSQLWithBooleanColumnCharMode(): void
    {
        // Char-mode platform: bool column stored as CHAR(1)
        $table = new Table('test_bool_char');
        $table->addColumn('flag', Types::BOOLEAN);

        $sql = $this->charPlatform->getCreateTableSQL($table);

        $ddl = implode(' ', $sql);
        // Char-mode path: table name stays as-is (string path, not normalized to uppercase)
        self::assertStringContainsStringIgnoringCase('test_bool_char', $ddl);
        // Column type should be VARCHAR or CHAR (since it gets switched to string type)
        self::assertMatchesRegularExpression('/CHAR|VARCHAR/', $ddl);
    }

    // -------------------------------------------------------------------------
    // getDropTableSQL — Table object (deprecated) path
    // -------------------------------------------------------------------------

    public function testGetDropTableSQLAcceptsTableObject(): void
    {
        $table = new Table('my_table');

        // Deprecated path: passing Table object
        // Should still return valid DROP TABLE SQL (as an EXECUTE BLOCK)
        $sql = @$this->platform->getDropTableSQL($table);

        self::assertStringContainsString('MY_TABLE', $sql);
        self::assertStringContainsString('DROP TABLE', $sql);
    }

    public function testGetDropTableSQLAcceptsString(): void
    {
        $sql = $this->platform->getDropTableSQL('my_table');
        self::assertStringContainsString('MY_TABLE', $sql);
        self::assertStringContainsString('DROP TABLE', $sql);
    }

    public function testGetDropTableSQLThrowsOnInvalidArgument(): void
    {
        // getDropTableSQL() with a non-string, non-Table argument hits the
        // InvalidArgumentException branch (lines 575-577 in FirebirdPlatform).
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects $table parameter to be string or');

        // @phpstan-ignore argument.type
        $this->platform->getDropTableSQL(42);
    }

    public function testNativeBooleanPlatformConvertsBooleansPassThrough(): void
    {
        // Firebird3Platform has $hasNativeBooleanType = true, so getBooleanDatabaseValue()
        // takes the native-bool branch (line 1756: return $value) instead of SMALLINT.
        $platform = new Firebird3Platform();
        self::assertTrue($platform->convertBooleans(true));
        self::assertFalse($platform->convertBooleans(false));
    }

    public function testGetExecuteBlockSqlWithBlockParams(): void
    {
        // getExecuteBlockSql() is protected; use Reflection to call it with blockParams
        // to cover lines 1420-1429 (the blockParams loop in EXECUTE BLOCK header).
        $method = new ReflectionMethod(FirebirdPlatform::class, 'getExecuteBlockSql');

        $result = $method->invoke($this->platform, [
            'blockParams' => ['param1' => 'INTEGER', 'param2' => 'VARCHAR(100)'],
            'statements'  => ['SELECT 1 FROM RDB$DATABASE'],
            'formatLineBreak' => false,
        ]);

        self::assertStringContainsString('EXECUTE BLOCK', $result);
        self::assertStringContainsString('param1 INTEGER', $result);
        self::assertStringContainsString('param2 VARCHAR(100)', $result);
        self::assertStringContainsString('SELECT 1 FROM RDB$DATABASE', $result);
    }

    // -------------------------------------------------------------------------
    // getAlterTableSQL — hasDefaultChanged paths (lines 691-695)
    // -------------------------------------------------------------------------

    public function testGetAlterTableSQLWithDefaultChanged(): void
    {
        // Old: VARCHAR(50) DEFAULT 'old_val' → New: VARCHAR(50) DEFAULT 'new_val'
        // Drives hasDefaultChanged() = true → SET DEFAULT branch (lines 691-695)
        $old = new Table('alter_tbl');
        $old->addColumn('col1', Types::STRING, ['length' => 50, 'default' => 'old_val']);

        $new = new Table('alter_tbl');
        $new->addColumn('col1', Types::STRING, ['length' => 50, 'default' => 'new_val']);

        $comparator = new Comparator($this->platform);
        $diff       = $comparator->compareTables($old, $new);
        $sql        = $this->platform->getAlterTableSQL($diff);

        self::assertNotEmpty($sql);
        $allSql = implode(' ', $sql);
        self::assertStringContainsStringIgnoringCase('SET DEFAULT', $allSql);
    }

    public function testGetAlterTableSQLWithDefaultDropped(): void
    {
        // Old: VARCHAR(50) DEFAULT 'old_val' → New: VARCHAR(50) no default
        // Drives hasDefaultChanged() = true → DROP DEFAULT branch (line 692)
        $old = new Table('alter_tbl2');
        $old->addColumn('col1', Types::STRING, ['length' => 50, 'default' => 'old_val']);

        $new = new Table('alter_tbl2');
        $new->addColumn('col1', Types::STRING, ['length' => 50]);

        $comparator = new Comparator($this->platform);
        $diff       = $comparator->compareTables($old, $new);
        $sql        = $this->platform->getAlterTableSQL($diff);

        self::assertNotEmpty($sql);
        $allSql = implode(' ', $sql);
        self::assertStringContainsStringIgnoringCase('DROP DEFAULT', $allSql);
    }

    // -------------------------------------------------------------------------
    // getAlterTableSQL — hasAutoIncrementChanged paths (lines 709-721)
    // -------------------------------------------------------------------------

    public function testGetAlterTableSQLWithAutoIncrementAdded(): void
    {
        // Old: INTEGER not autoincrement → New: INTEGER autoincrement
        // Drives hasAutoIncrementChanged() = true, newColumn->getAutoincrement() = true
        // Covers lines 709, 712, 714-717
        // The Comparator does not detect autoincrement changes, so we build the diff manually.
        $intType = Type::getType(Types::INTEGER);

        $oldCol = new Column('id', $intType);
        // autoincrement defaults to false

        $newCol = new Column('id', $intType);
        $newCol->setAutoincrement(true);

        // changedProperties drives hasAutoIncrementChanged() = true
        $colDiff = new ColumnDiff('id', $newCol, ['autoincrement'], $oldCol);

        $fromTable = new Table('autoinc_tbl');
        $fromTable->addColumn('id', Types::INTEGER);

        // @phpstan-ignore argument.type (deprecated TableDiff constructor, but correct for DBAL 3.x)
        $diff = new TableDiff('autoinc_tbl', [], [$colDiff], [], [], [], [], $fromTable);
        $sql  = $this->platform->getAlterTableSQL($diff);

        // When autoincrement is added, a CREATE SEQUENCE statement is generated
        $allSql = implode(' ', $sql);
        self::assertStringContainsStringIgnoringCase('SEQUENCE', $allSql);
    }

    public function testGetAlterTableSQLWithAutoIncrementDropped(): void
    {
        // Old: INTEGER autoincrement → New: INTEGER not autoincrement
        // Drives hasAutoIncrementChanged() = true, newColumn->getAutoincrement() = false
        // Covers lines 720-721 (DROP DEFAULT path)
        // The Comparator does not detect autoincrement changes, so we build the diff manually.
        $intType = Type::getType(Types::INTEGER);

        $oldCol = new Column('id', $intType);
        $oldCol->setAutoincrement(true);

        $newCol = new Column('id', $intType);
        // autoincrement defaults to false → DROP DEFAULT path

        // changedProperties drives hasAutoIncrementChanged() = true
        $colDiff = new ColumnDiff('id', $newCol, ['autoincrement'], $oldCol);

        $fromTable = new Table('autoinc_drop_tbl');
        $fromTable->addColumn('id', Types::INTEGER, ['autoincrement' => true]);

        // @phpstan-ignore argument.type (deprecated TableDiff constructor, but correct for DBAL 3.x)
        $diff = new TableDiff('autoinc_drop_tbl', [], [$colDiff], [], [], [], [], $fromTable);
        $sql  = $this->platform->getAlterTableSQL($diff);

        $allSql = implode(' ', $sql);
        self::assertStringContainsStringIgnoringCase('DROP DEFAULT', $allSql);
    }

    // -------------------------------------------------------------------------
    // getAlterTableSQL — hasLengthChanged path (lines 742-744)
    // -------------------------------------------------------------------------

    public function testGetAlterTableSQLWithLengthChanged(): void
    {
        // Old: VARCHAR(50) → New: VARCHAR(200)
        // Drives hasLengthChanged() = true → covers the ALTER TYPE block at 742-744
        $old = new Table('len_tbl');
        $old->addColumn('col1', Types::STRING, ['length' => 50]);

        $new = new Table('len_tbl');
        $new->addColumn('col1', Types::STRING, ['length' => 200]);

        $comparator = new Comparator($this->platform);
        $diff       = $comparator->compareTables($old, $new);
        $sql        = $this->platform->getAlterTableSQL($diff);

        self::assertNotEmpty($sql);
        $allSql = implode(' ', $sql);
        // The length-change ALTER produces a TYPE declaration with 200
        self::assertStringContainsString('200', $allSql);
    }

    // -------------------------------------------------------------------------
    // getQuotedNameOf — string input path (line 1711)
    // -------------------------------------------------------------------------

    public function testGetQuotedNameOfWithStringInput(): void
    {
        // getQuotedNameOf() is protected; call via ReflectionMethod.
        // When given a plain string (not an AbstractAsset), it wraps it in an Identifier.
        $method = new ReflectionMethod(FirebirdPlatform::class, 'getQuotedNameOf');

        $result = $method->invoke($this->platform, 'my_table_name');

        self::assertIsString($result);
        self::assertStringContainsStringIgnoringCase('my_table_name', $result);
    }

    // -------------------------------------------------------------------------
    // getQuotedNameOf — AbstractAsset input path (line 1711)
    // -------------------------------------------------------------------------

    public function testGetQuotedNameOfWithIdentifierObjectCoversAbstractAssetBranch(): void
    {
        // When an Identifier (which extends AbstractAsset) is passed, line 1711 is hit:
        //   return $name->getQuotedName($this);
        $method = new ReflectionMethod(FirebirdPlatform::class, 'getQuotedNameOf');

        $id     = new Identifier('my_table_name'); // Identifier extends AbstractAsset
        $result = $method->invoke($this->platform, $id);

        self::assertIsString($result);
        self::assertStringContainsStringIgnoringCase('my_table_name', $result);
    }

    // -------------------------------------------------------------------------
    // getDropSequenceIfExistsPSql — non-inBlock path (line 1532)
    // -------------------------------------------------------------------------

    public function testGetDropSequenceIfExistsPSqlNonBlock(): void
    {
        // getDropSequenceIfExistsPSql($aSequence, $inBlock = false) → line 1532: return $result;
        $method = new ReflectionMethod(FirebirdPlatform::class, 'getDropSequenceIfExistsPSql');

        /** @var string $result */
        $result = $method->invoke($this->platform, 'MY_SEQ', false);

        self::assertIsString($result);
        self::assertStringContainsString('IF (EXISTS', $result);
        self::assertStringContainsString('MY_SEQ', $result);
    }

    // -------------------------------------------------------------------------
    // _getCreateTableSQL — check constraint path (line 1654)
    // -------------------------------------------------------------------------

    public function testCreateTableSQLWithCheckConstraint(): void
    {
        // _getCreateTableSQL() with a column that has a 'check' option covers line 1654:
        //   $query .= ', ' . $check;
        $method = new ReflectionMethod(FirebirdPlatform::class, '_getCreateTableSQL');

        $columnData = [
            'name'             => 'qty',
            'type'             => Type::getType(Types::INTEGER),
            'notnull'          => true,
            'default'          => null,
            'autoincrement'    => false,
            'comment'          => null,
            'length'           => null,
            'precision'        => 10,
            'scale'            => 0,
            'unsigned'         => false,
            'fixed'            => false,
            'check'            => 'qty > 0',
            'columnDefinition' => null,
        ];

        /** @var string[] $sql */
        $sql = $method->invoke($this->platform, 'check_tbl', ['qty' => $columnData], []);

        $ddl = implode(' ', $sql);
        self::assertStringContainsString('check_tbl', $ddl);
        self::assertStringContainsString('qty > 0', $ddl);
    }

    // -------------------------------------------------------------------------
    // _getCreateTableSQL — sequence column path (line 1670)
    // -------------------------------------------------------------------------

    public function testCreateTableSQLWithSequenceColumn(): void
    {
        // _getCreateTableSQL() with a column that has a 'sequence' option covers line 1670:
        //   $sql[] = $this->getCreateSequenceSQL($column['sequence']);
        $method = new ReflectionMethod(FirebirdPlatform::class, '_getCreateTableSQL');

        $seq        = new Sequence('MY_SEQ');
        $columnData = [
            'name'             => 'id',
            'type'             => Type::getType(Types::INTEGER),
            'notnull'          => true,
            'default'          => null,
            'autoincrement'    => false,
            'comment'          => null,
            'length'           => null,
            'precision'        => 10,
            'scale'            => 0,
            'unsigned'         => false,
            'fixed'            => false,
            'columnDefinition' => null,
            'sequence'         => $seq,
        ];

        /** @var string[] $sql */
        $sql = $method->invoke($this->platform, 'seq_tbl', ['id' => $columnData], []);

        $ddl = implode(' ', $sql);
        self::assertStringContainsString('seq_tbl', $ddl);
        // The sequence CREATE SEQUENCE statement should be present
        self::assertStringContainsString('MY_SEQ', $ddl);
    }

    // -------------------------------------------------------------------------
    // generateIdentifier — second quoted prefix triggers continue (line 1279)
    // -------------------------------------------------------------------------

    public function testGenerateIdentifierWithMultipleQuotedPrefixesCoversContinue(): void
    {
        // generateIdentifier() iterates over prefix array.
        // When needQuote is already true, line 1279 (continue;) is hit.
        // Trigger: pass [QuotedIdentifier, UnquotedIdentifier] as prefix array.
        $method = new ReflectionMethod(FirebirdPlatform::class, 'generateIdentifier');

        // '"quoted"' creates a quoted Identifier (isQuoted() = true)
        // On the second prefix, needQuote is already true → continue; at line 1279
        $prefixes = [
            new Identifier('"first_quoted"'),   // isQuoted() = true → sets needQuote = true
            new Identifier('second_plain'),      // needQuote already true → line 1279 continue
        ];

        /** @var Identifier $result */
        $result = $method->invoke($this->platform, $prefixes, 'IDX', 30);

        self::assertInstanceOf(Identifier::class, $result);
    }

    // -------------------------------------------------------------------------
    // getAlterTableSQL — deprecated schema event hook continue paths (lines 633, 658, 667, 749)
    // -------------------------------------------------------------------------

    public function testGetAlterTableSQLAddColumnEventPreventsDefault(): void
    {
        // Registers an EventManager listener that calls preventDefault() for
        // onSchemaAlterTableAddColumn → the `continue;` at line 633 is executed.
        $em = new EventManager();
        $em->addEventListener(
            Events::onSchemaAlterTableAddColumn,
            new class {
                public function onSchemaAlterTableAddColumn(SchemaAlterTableAddColumnEventArgs $e): void
                {
                    $e->preventDefault();
                }
            },
        );

        $platform = new FirebirdPlatform();
        $platform->setEventManager($em);

        $fromTable = new Table('evt_tbl');
        $newTable  = new Table('evt_tbl');
        $newTable->addColumn('new_col', Types::STRING, ['length' => 10]);

        $comparator = new Comparator($platform);
        $diff       = $comparator->compareTables($fromTable, $newTable);
        // Event prevents ADD COLUMN SQL; no exception should be thrown
        $sql = $platform->getAlterTableSQL($diff);
        // The event prevented default so ADD COLUMN SQL should be suppressed
        $allSql = implode(' ', $sql);
        self::assertStringNotContainsStringIgnoringCase('ADD', $allSql);
    }

    public function testGetAlterTableSQLDropColumnEventPreventsDefault(): void
    {
        // Registers listener for onSchemaAlterTableRemoveColumn → line 658.
        $em = new EventManager();
        $em->addEventListener(
            Events::onSchemaAlterTableRemoveColumn,
            new class {
                public function onSchemaAlterTableRemoveColumn(SchemaAlterTableRemoveColumnEventArgs $e): void
                {
                    $e->preventDefault();
                }
            },
        );

        $platform = new FirebirdPlatform();
        $platform->setEventManager($em);

        $fromTable = new Table('evt_tbl2');
        $fromTable->addColumn('col_to_drop', Types::STRING, ['length' => 10]);
        $newTable = new Table('evt_tbl2');

        $comparator = new Comparator($platform);
        $diff       = $comparator->compareTables($fromTable, $newTable);
        $sql        = $platform->getAlterTableSQL($diff);
        $allSql     = implode(' ', $sql);
        self::assertStringNotContainsStringIgnoringCase('DROP', $allSql);
    }

    public function testGetAlterTableSQLChangeColumnEventPreventsDefault(): void
    {
        // Registers listener for onSchemaAlterTableChangeColumn → line 667.
        $em = new EventManager();
        $em->addEventListener(
            Events::onSchemaAlterTableChangeColumn,
            new class {
                public function onSchemaAlterTableChangeColumn(SchemaAlterTableChangeColumnEventArgs $e): void
                {
                    $e->preventDefault();
                }
            },
        );

        $platform = new FirebirdPlatform();
        $platform->setEventManager($em);

        $fromTable = new Table('evt_tbl3');
        $fromTable->addColumn('col1', Types::STRING, ['length' => 10]);
        $newTable = new Table('evt_tbl3');
        $newTable->addColumn('col1', Types::STRING, ['length' => 20]);

        $comparator = new Comparator($platform);
        $diff       = $comparator->compareTables($fromTable, $newTable);
        $sql        = $platform->getAlterTableSQL($diff);
        // Change was suppressed - no ALTER TYPE statement
        $allSql = implode(' ', $sql);
        self::assertStringNotContainsStringIgnoringCase('ALTER COLUMN', $allSql);
    }

    public function testGetAlterTableSQLRenameColumnEventPreventsDefault(): void
    {
        // Registers listener for onSchemaAlterTableRenameColumn → line 749.
        $em = new EventManager();
        $em->addEventListener(
            Events::onSchemaAlterTableRenameColumn,
            new class {
                public function onSchemaAlterTableRenameColumn(SchemaAlterTableRenameColumnEventArgs $e): void
                {
                    $e->preventDefault();
                }
            },
        );

        $platform = new FirebirdPlatform();
        $platform->setEventManager($em);

        $renamedCol = new Column('new_name', Type::getType(Types::STRING), ['length' => 10]);
        // @phpstan-ignore argument.type
        $diff = new TableDiff('evt_tbl4', [], [], [], [], [], [], null, [], [], [], ['old_name' => $renamedCol]);
        $sql  = $platform->getAlterTableSQL($diff);
        // Rename was suppressed
        $allSql = implode(' ', $sql);
        self::assertStringNotContainsString('new_name', $allSql);
    }
}
