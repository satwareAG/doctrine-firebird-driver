<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Validates round-trip of PHP DateTimeImmutable through Firebird DATE columns.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/64
 */
class DateImmutableTypeTest extends FunctionalTestCase
{
    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    #[DataProvider('dateProvider')]
    public function testDateRoundTrip(string $dateString): void
    {
        $date = new DateTimeImmutable($dateString);

        $this->connection->insert('date_immutable_test', ['id' => 1, 'val' => $date], [
            'id'  => Types::INTEGER,
            'val' => Types::DATE_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM date_immutable_test WHERE id = 1');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::DATE_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame($date->format('Y-m-d'), $retrieved->format('Y-m-d'));
    }

    public function testDateLeapYear(): void
    {
        $leapDay = new DateTimeImmutable('2024-02-29');

        $this->connection->insert('date_immutable_test', ['id' => 2, 'val' => $leapDay], [
            'id'  => Types::INTEGER,
            'val' => Types::DATE_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM date_immutable_test WHERE id = 2');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::DATE_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame('2024-02-29', $retrieved->format('Y-m-d'));
    }

    public function testDateBoundary(): void
    {
        // Firebird DATE min: 0001-01-01, max: 9999-12-31
        $minDate = new DateTimeImmutable('1900-01-01');
        $maxDate = new DateTimeImmutable('2099-12-31');

        $this->connection->insert('date_immutable_test', ['id' => 3, 'val' => $minDate], [
            'id'  => Types::INTEGER,
            'val' => Types::DATE_IMMUTABLE,
        ]);
        $this->connection->insert('date_immutable_test', ['id' => 4, 'val' => $maxDate], [
            'id'  => Types::INTEGER,
            'val' => Types::DATE_IMMUTABLE,
        ]);

        $rawMin = $this->connection->fetchOne('SELECT val FROM date_immutable_test WHERE id = 3');
        $rawMax = $this->connection->fetchOne('SELECT val FROM date_immutable_test WHERE id = 4');

        self::assertNotFalse($rawMin);
        self::assertNotFalse($rawMax);

        $retrievedMin = $this->connection->convertToPHPValue($rawMin, Types::DATE_IMMUTABLE);
        $retrievedMax = $this->connection->convertToPHPValue($rawMax, Types::DATE_IMMUTABLE);

        self::assertInstanceOf(DateTimeImmutable::class, $retrievedMin);
        self::assertInstanceOf(DateTimeImmutable::class, $retrievedMax);
        self::assertSame('1900-01-01', $retrievedMin->format('Y-m-d'));
        self::assertSame('2099-12-31', $retrievedMax->format('Y-m-d'));
    }

    /** @return iterable<string, array{string}> */
    public static function dateProvider(): iterable
    {
        yield 'regular date'  => ['2024-06-15'];
        yield 'leap year day' => ['2024-02-29'];
        yield 'year boundary' => ['2023-12-31'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table('date_immutable_test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::DATE_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);
    }
}
