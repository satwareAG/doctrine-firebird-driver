<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Validates round-trip of PHP DateTimeImmutable through Firebird TIMESTAMP columns.
 *
 * Note: Firebird TIMESTAMP has second-level precision (no microseconds).
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/64
 */
class DateTimeImmutableTypeTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table('datetime_immutable_test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);
    }

    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testDateTimeRoundTrip(): void
    {
        // Firebird TIMESTAMP truncates to seconds — use second-precision datetime
        $dt = new DateTimeImmutable('2024-06-15 14:30:45');

        $this->connection->insert('datetime_immutable_test', ['id' => 1, 'val' => $dt], [
            'id'  => Types::INTEGER,
            'val' => Types::DATETIME_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM datetime_immutable_test WHERE id = 1');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::DATETIME_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame($dt->format('Y-m-d H:i:s'), $retrieved->format('Y-m-d H:i:s'));
    }

    public function testDateTimeWithMidnight(): void
    {
        $midnight = new DateTimeImmutable('2024-01-01 00:00:00');

        $this->connection->insert('datetime_immutable_test', ['id' => 2, 'val' => $midnight], [
            'id'  => Types::INTEGER,
            'val' => Types::DATETIME_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM datetime_immutable_test WHERE id = 2');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::DATETIME_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame('2024-01-01 00:00:00', $retrieved->format('Y-m-d H:i:s'));
    }

    public function testDateTimeWithNoon(): void
    {
        $noon = new DateTimeImmutable('2025-07-04 12:00:00');

        $this->connection->insert('datetime_immutable_test', ['id' => 3, 'val' => $noon], [
            'id'  => Types::INTEGER,
            'val' => Types::DATETIME_IMMUTABLE,
        ]);

        $raw = $this->connection->fetchOne('SELECT val FROM datetime_immutable_test WHERE id = 3');
        self::assertNotFalse($raw);

        $retrieved = $this->connection->convertToPHPValue($raw, Types::DATETIME_IMMUTABLE);
        self::assertInstanceOf(DateTimeImmutable::class, $retrieved);
        self::assertSame('2025-07-04 12:00:00', $retrieved->format('Y-m-d H:i:s'));
    }
}
