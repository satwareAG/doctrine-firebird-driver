<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Validates round-trip of PHP DateTimeImmutable through Firebird TIME columns.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/64
 */
class TimeImmutableTypeTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table('time_immutable_test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::TIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);
    }

    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testTimeRoundTrip(): void
    {
        $time = new DateTimeImmutable('1970-01-01 09:15:30');

        $this->connection->insert('time_immutable_test', ['id' => 1, 'val' => $time], [
            'id'  => Types::INTEGER,
            'val' => Types::TIME_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM time_immutable_test WHERE id = 1');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::TIME_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame('09:15:30', $retrieved->format('H:i:s'));
    }

    public function testTimeMidnight(): void
    {
        $midnight = new DateTimeImmutable('1970-01-01 00:00:00');

        $this->connection->insert('time_immutable_test', ['id' => 2, 'val' => $midnight], [
            'id'  => Types::INTEGER,
            'val' => Types::TIME_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM time_immutable_test WHERE id = 2');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::TIME_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame('00:00:00', $retrieved->format('H:i:s'));
    }

    public function testTimeEndOfDay(): void
    {
        $endOfDay = new DateTimeImmutable('1970-01-01 23:59:59');

        $this->connection->insert('time_immutable_test', ['id' => 3, 'val' => $endOfDay], [
            'id'  => Types::INTEGER,
            'val' => Types::TIME_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM time_immutable_test WHERE id = 3');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::TIME_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame('23:59:59', $retrieved->format('H:i:s'));
    }
}
