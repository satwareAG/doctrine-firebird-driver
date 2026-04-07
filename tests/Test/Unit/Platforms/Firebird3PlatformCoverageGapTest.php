<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

/**
 * Targeted coverage tests for Firebird3Platform::getAlterTableSQL() uncovered paths:
 *  - Line 70: dropped column query generation
 *  - Line 79: modified column old-column resolution
 */
#[CoversClass(Firebird3Platform::class)]
final class Firebird3PlatformCoverageGapTest extends TestCase
{
    private Firebird3Platform $platform;

    protected function setUp(): void
    {
        $this->platform = new Firebird3Platform();
    }

    /**
     * Covers Firebird3Platform::getAlterTableSQL() line 70:
     *   $query = 'DROP ' . $droppedColumn->getQuotedName($this);
     *
     * A dropped column (old table has col2, new table does not) causes
     * getAlterTableSQL to iterate getDroppedColumns() and build a DROP query.
     */
    public function testGetAlterTableSQLWithDroppedColumn(): void
    {
        $old = new Table('drop_col_tbl');
        $old->addColumn('id', Types::INTEGER);
        $old->addColumn('col_to_drop', Types::STRING, ['length' => 50]);

        $new = new Table('drop_col_tbl');
        $new->addColumn('id', Types::INTEGER);
        // col_to_drop is absent in the new table → triggers dropped-column path

        $comparator = new Comparator($this->platform);
        $diff       = $comparator->compareTables($old, $new);
        $sql        = $this->platform->getAlterTableSQL($diff);

        self::assertNotEmpty($sql);
        $allSql = implode(' ', $sql);
        self::assertStringContainsStringIgnoringCase('DROP', $allSql);
        self::assertStringContainsStringIgnoringCase('col_to_drop', $allSql);
    }

    /**
     * Covers Firebird3Platform::getAlterTableSQL() line 79:
     *   $oldColumn = $columnDiff->getOldColumn() ?? $columnDiff->getOldColumnName();
     *
     * A modified column (length change) causes getAlterTableSQL to iterate
     * getModifiedColumns() and resolve the old column object/name.
     */
    public function testGetAlterTableSQLWithModifiedColumn(): void
    {
        $old = new Table('mod_col_tbl');
        $old->addColumn('col1', Types::STRING, ['length' => 50]);

        $new = new Table('mod_col_tbl');
        $new->addColumn('col1', Types::STRING, ['length' => 100]);

        $comparator = new Comparator($this->platform);
        $diff       = $comparator->compareTables($old, $new);
        $sql        = $this->platform->getAlterTableSQL($diff);

        self::assertNotEmpty($sql);
        $allSql = implode(' ', $sql);
        // Length change generates an ALTER COLUMN ... TYPE ... statement with new size
        self::assertStringContainsString('100', $allSql);
    }
}
