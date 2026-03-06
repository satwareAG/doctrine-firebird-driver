<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
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
        $type = Type::getType(Types::STRING);

        // fromTable has charset platform option (as Firebird introspection adds)
        $fromCol = new Column('name', $type, ['length' => 100]);
        $fromCol->setPlatformOptions(['charset' => 'UTF8']);
        $fromTable = new Table('users');
        $fromTable->addColumn('name', Types::STRING, ['length' => 100]);
        // Add charset option to the column
        $fromTable->getColumn('name')->setPlatformOptions(['charset' => 'UTF8']);

        // toTable has no charset option (user-defined table)
        $toTable = new Table('users');
        $toTable->addColumn('name', Types::STRING, ['length' => 100]);

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // charset difference should be stripped - no modified columns
        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesIgnoresCollationDifference(): void
    {
        $fromTable = new Table('products');
        $fromTable->addColumn('title', Types::STRING, ['length' => 255]);
        $fromTable->getColumn('title')->setPlatformOptions(['collation' => 'UNICODE_FSS']);

        $toTable = new Table('products');
        $toTable->addColumn('title', Types::STRING, ['length' => 255]);

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // collation difference should be stripped
        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesIgnoresCharsetAndCollationTogether(): void
    {
        $fromTable = new Table('items');
        $fromTable->addColumn('description', Types::STRING, ['length' => 500]);
        $fromTable->getColumn('description')->setPlatformOptions([
            'charset'   => 'NONE',
            'Collation' => 'UNICODE_FSS',
        ]);

        $toTable = new Table('items');
        $toTable->addColumn('description', Types::STRING, ['length' => 500]);

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesNormalizesNullDefault(): void
    {
        // fromTable column has string 'NULL' as default (Firebird introspection artifact)
        $fromTable = new Table('orders');
        $fromTable->addColumn('notes', Types::STRING, ['length' => 255, 'default' => 'NULL', 'notnull' => false]);

        // toTable column has actual null default (user-defined)
        $toTable = new Table('orders');
        $toTable->addColumn('notes', Types::STRING, ['length' => 255, 'notnull' => false]);

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // 'NULL' string default should be treated as no default - no diff
        self::assertCount(0, $diff->getModifiedColumns());
    }

    public function testCompareTablesNormalizesWhitespacePaddedDefault(): void
    {
        // fromTable column has whitespace-padded default (Firebird stores with spaces)
        $fromTable = new Table('config');
        $fromTable->addColumn('status', Types::STRING, ['length' => 50, 'default' => '  active  ']);

        // toTable column has same default without whitespace
        $toTable = new Table('config');
        $toTable->addColumn('status', Types::STRING, ['length' => 50, 'default' => 'active']);

        $diff = $this->comparator->compareTables($fromTable, $toTable);

        // Whitespace-trimmed defaults should match
        self::assertCount(0, $diff->getModifiedColumns());
    }
}
