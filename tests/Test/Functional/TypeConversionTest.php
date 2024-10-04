<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use DateTime;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use stdClass;

use function str_repeat;

class TypeConversionTest extends FunctionalTestCase
{
    private static int $typeCounter = 0;

    #[DataProvider('booleanProvider')]
    public function testIdempotentConversionToBoolean(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsBool($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    #[DataProvider('integerProvider')]
    public function testIdempotentConversionToInteger(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsInt($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    #[DataProvider('floatProvider')]
    public function testIdempotentConversionToFloat(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsFloat($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    #[DataProvider('toStringProvider')]
    public function testIdempotentConversionToString(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsString($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    #[DataProvider('toArrayProvider')]
    public function testIdempotentConversionToArray(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsArray($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    #[DataProvider('toObjectProvider')]
    public function testIdempotentConversionToObject(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsObject($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    #[DataProvider('toDateTimeProvider')]
    public function testIdempotentConversionToDateTime(string $type, DateTime $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertInstanceOf(DateTime::class, $dbValue);

        if ($type === Types::DATETIMETZ_MUTABLE) {
            return;
        }

        self::assertEquals($originalValue, $dbValue);
        self::assertEquals($originalValue->getTimezone(), $dbValue->getTimezone());
    }

    /** @return mixed[][] */
    public static function booleanProvider(): Iterator
    {
        yield 'true' => [Types::BOOLEAN, true];
        yield 'false' => [Types::BOOLEAN, false];
    }

    /** @return mixed[][] */
    public static function integerProvider(): Iterator
    {
        yield 'smallint' => [Types::SMALLINT, 123];
    }

    /** @return mixed[][] */
    public static function floatProvider(): Iterator
    {
        yield 'float' => [Types::FLOAT, 1.5];
    }

    /** @return mixed[][] */
    public static function toStringProvider(): Iterator
    {
        yield 'string' => [Types::STRING, 'ABCDEFGabcdefg'];
        yield 'text' => [Types::TEXT, str_repeat('foo ', 1000)];
    }

    /**
     * @return mixed[][]
     *
     * @psalm-suppress DeprecatedConstant
     */
    public static function toArrayProvider(): Iterator
    {
        yield 'array' => [Types::ARRAY, ['foo' => 'bar']];
        yield 'json' => [Types::JSON, ['foo' => 'bar']];
    }

    /**
     * @return mixed[][]
     *
     * @psalm-suppress DeprecatedConstant
     */
    public static function toObjectProvider(): Iterator
    {
        $obj      = new stdClass();
        $obj->foo = 'bar';
        $obj->bar = 'baz';

        yield 'object' => [Types::OBJECT, $obj];
    }

    /** @return mixed[][] */
    public static function toDateTimeProvider(): Iterator
    {
        yield 'datetime' => [Types::DATETIME_MUTABLE, new DateTime('2010-04-05 10:10:10')];
        yield 'datetimetz' => [Types::DATETIMETZ_MUTABLE, new DateTime('2010-04-05 10:10:10')];
        yield 'date' => [Types::DATE_MUTABLE, new DateTime('2010-04-05')];
        yield 'time' => [Types::TIME_MUTABLE, new DateTime('1970-01-01 10:10:10')];
    }

    /** @psalm-suppress DeprecatedConstant */
    protected function setUp(): void
    {
        $table = new Table('type_conversion');
        $table->addColumn('id', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('test_string', Types::STRING, ['notnull' => false]);
        $table->addColumn('test_boolean', Types::BOOLEAN, ['notnull' => false]);
        $table->addColumn('test_bigint', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('test_smallint', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('test_datetime', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_datetimetz', Types::DATETIMETZ_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_time', Types::TIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_text', Types::TEXT, ['notnull' => false]);
        $table->addColumn('test_array', Types::ARRAY, ['notnull' => false]);
        $table->addColumn('test_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('test_object', Types::OBJECT, ['notnull' => false]);
        $table->addColumn('test_float', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('test_decimal', Types::DECIMAL, ['notnull' => false, 'scale' => 2, 'precision' => 10]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    private function processValue(string $type, mixed $originalValue): mixed
    {
        $columnName     = 'test_' . $type;
        $typeInstance   = Type::getType($type);
        $insertionValue = $typeInstance->convertToDatabaseValue(
            $originalValue,
            $this->connection->getDatabasePlatform(),
        );

        $this->connection->insert('type_conversion', ['id' => ++self::$typeCounter, $columnName => $insertionValue]);

        $sql = 'SELECT ' . $columnName . ' FROM type_conversion WHERE id = ' . self::$typeCounter;

        return $typeInstance->convertToPHPValue(
            $this->connection->fetchOne($sql),
            $this->connection->getDatabasePlatform(),
        );
    }
}
