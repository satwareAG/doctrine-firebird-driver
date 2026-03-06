<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Event\SchemaAlterTableAddColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

/**
 * Targeted coverage tests for Firebird3Platform::getAlterTableSQL() uncovered paths:
 *  - Line 45: AddColumn event hook continue;
 *  - Line 70: dropped column query generation
 *  - Line 79: modified column old-column resolution
 *  - Line 164: RenameColumn event hook continue;
 *  + DropColumn and ChangeColumn event hooks
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

    /**
     * Covers Firebird3Platform::getAlterTableSQL() line 45 (AddColumn event preventDefault).
     *
     * When an event listener calls preventDefault(), the column addition
     * is skipped (continue;). SQL generated must NOT include ADD for new_col.
     */
    public function testGetAlterTableSQLAddColumnEventPreventsDefault(): void
    {
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

        $platform = new Firebird3Platform();
        $platform->setEventManager($em);

        $fromTable = new Table('fb3_evt_add');
        $newTable  = new Table('fb3_evt_add');
        $newTable->addColumn('new_col', Types::STRING, ['length' => 10]);

        $comparator = new Comparator($platform);
        $diff       = $comparator->compareTables($fromTable, $newTable);
        $sql        = $platform->getAlterTableSQL($diff);

        // preventDefault means the ADD column SQL must not be emitted
        $allSql = implode(' ', $sql);
        self::assertStringNotContainsStringIgnoringCase('new_col', $allSql);
    }

    /**
     * Covers Firebird3Platform::getAlterTableSQL() line for DropColumn event preventDefault.
     *
     * When an event listener calls preventDefault(), the column drop is skipped.
     */
    public function testGetAlterTableSQLDropColumnEventPreventsDefault(): void
    {
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

        $platform = new Firebird3Platform();
        $platform->setEventManager($em);

        $fromTable = new Table('fb3_evt_drop');
        $fromTable->addColumn('col_to_drop', Types::STRING, ['length' => 20]);
        $newTable = new Table('fb3_evt_drop');

        $comparator = new Comparator($platform);
        $diff       = $comparator->compareTables($fromTable, $newTable);
        $sql        = $platform->getAlterTableSQL($diff);

        // preventDefault means DROP column SQL must not be emitted
        $allSql = implode(' ', $sql);
        self::assertStringNotContainsStringIgnoringCase('col_to_drop', $allSql);
    }

    /**
     * Covers Firebird3Platform::getAlterTableSQL() ChangeColumn event preventDefault.
     *
     * When an event listener calls preventDefault(), column modification is skipped.
     */
    public function testGetAlterTableSQLChangeColumnEventPreventsDefault(): void
    {
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

        $platform = new Firebird3Platform();
        $platform->setEventManager($em);

        $fromTable = new Table('fb3_evt_chg');
        $fromTable->addColumn('col1', Types::STRING, ['length' => 50]);
        $newTable = new Table('fb3_evt_chg');
        $newTable->addColumn('col1', Types::STRING, ['length' => 100]);

        $comparator = new Comparator($platform);
        $diff       = $comparator->compareTables($fromTable, $newTable);
        $sql        = $platform->getAlterTableSQL($diff);

        // preventDefault: no ALTER for the changed column, nothing with '100'
        $allSql = implode(' ', $sql);
        self::assertStringNotContainsString('100', $allSql);
    }

    /**
     * Covers Firebird3Platform::getAlterTableSQL() line 164 (RenameColumn event preventDefault).
     *
     * When an event listener calls preventDefault(), rename is skipped.
     */
    public function testGetAlterTableSQLRenameColumnEventPreventsDefault(): void
    {
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

        $platform = new Firebird3Platform();
        $platform->setEventManager($em);

        $renamedCol = new Column('new_name', Type::getType(Types::STRING), ['length' => 10]);
        // @phpstan-ignore argument.type
        $diff = new TableDiff('fb3_evt_ren', [], [], [], [], [], [], null, [], [], [], ['old_name' => $renamedCol]);
        $sql  = $platform->getAlterTableSQL($diff);

        // preventDefault: no ALTER TO new_name
        $allSql = implode(' ', $sql);
        self::assertStringNotContainsStringIgnoringCase('new_name', $allSql);
    }
}
