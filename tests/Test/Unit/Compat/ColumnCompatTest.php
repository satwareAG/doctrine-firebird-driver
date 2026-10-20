<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Compat;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Compat\ColumnCompat;

use function get_class;

/**
 * Under a DBAL 4.5 vendor, only the getTypeName() branch of ColumnCompat::type()
 * is exercisable; the 4.4 fallback branch (Column::getType()) is verified on the
 * 4.4.5 resolution (suite green on both bounds) and by the cross-version
 * functional matrix in CI.
 */
#[CoversClass(ColumnCompat::class)]
final class ColumnCompatTest extends TestCase
{
    public function testTypeReturnsTypeInstance(): void
    {
        $column = Column::editor()
            ->setUnquotedName('c')
            ->setTypeName(Types::BINARY)
            ->create();

        self::assertSame(BinaryType::class, get_class(ColumnCompat::type($column)));
    }

    public function testTypeResolvesIntegerColumn(): void
    {
        $column = Column::editor()
            ->setUnquotedName('c')
            ->setTypeName(Types::INTEGER)
            ->create();

        self::assertSame(IntegerType::class, get_class(ColumnCompat::type($column)));
    }
}
