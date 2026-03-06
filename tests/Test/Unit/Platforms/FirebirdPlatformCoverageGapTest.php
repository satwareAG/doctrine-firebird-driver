<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
}
