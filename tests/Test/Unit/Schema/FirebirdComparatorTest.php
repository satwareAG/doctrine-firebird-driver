<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdComparator;

/**
 * Unit tests for FirebirdComparator covering charset/collation stripping
 * and NULL default normalization.
 */
#[CoversClass(FirebirdComparator::class)]
class FirebirdComparatorTest extends TestCase
{
    private FirebirdComparator $comparator;
    private FirebirdPlatform $platform;

    protected function setUp(): void
    {
        $this->platform   = new FirebirdPlatform();
        $this->comparator = new FirebirdComparator($this->platform);
    }

    public function testCompareTablesIgnoresCharsetDifference(): void
    {
        // fromTable has charset platform option (as Firebird introspection adds)
        $fromTable = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->setCharset('UTF8')
                    ->create(),
            )
            ->create();

        // toTable has no charset option (user-defined table)
        $toTable = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->create(),
            )
            ->create();

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // charset difference should be stripped - no modified columns
        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesIgnoresCollationDifference(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('products')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('title')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setCollation('UNICODE_FSS')
                    ->create(),
            )
            ->create();

        $toTable = Table::editor()
            ->setUnquotedName('products')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('title')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
            )
            ->create();

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // collation difference should be stripped
        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesIgnoresCharsetAndCollationTogether(): void
    {
        $fromTable = Table::editor()
            ->setUnquotedName('items')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('description')
                    ->setTypeName(Types::STRING)
                    ->setLength(500)
                    ->setCharset('NONE')
                    ->setCollation('UNICODE_FSS')
                    ->create(),
            )
            ->create();

        $toTable = Table::editor()
            ->setUnquotedName('items')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('description')
                    ->setTypeName(Types::STRING)
                    ->setLength(500)
                    ->create(),
            )
            ->create();

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesNormalizesNullDefault(): void
    {
        // fromTable column has string 'NULL' as default (Firebird introspection artifact)
        $fromTable = Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('notes')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setDefaultValue('NULL')
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();

        // toTable column has actual null default (user-defined)
        $toTable = Table::editor()
            ->setUnquotedName('orders')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('notes')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
            )
            ->create();

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // 'NULL' string default should be treated as no default - no diff
        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesNormalizesWhitespacePaddedDefault(): void
    {
        // fromTable column has whitespace-padded default (Firebird stores with spaces)
        $fromTable = Table::editor()
            ->setUnquotedName('config')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('status')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->setDefaultValue('  active  ')
                    ->create(),
            )
            ->create();

        // toTable column has same default without whitespace
        $toTable = Table::editor()
            ->setUnquotedName('config')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('status')
                    ->setTypeName(Types::STRING)
                    ->setLength(50)
                    ->setDefaultValue('active')
                    ->create(),
            )
            ->create();

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // Whitespace-trimmed defaults should match
        self::assertCount(0, $diff->getModifiedColumns());
    }
}
