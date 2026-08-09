<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\Group;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function array_map;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function uniqid;

/**
 * Functional tests for the DBAL 4 introspection API powered by
 * {@see \Satag\DoctrineFirebirdDriver\Platforms\Firebird\FirebirdMetadataProvider}.
 *
 * Each test creates schema objects (tables, views, sequences), then introspects
 * them via the new API to verify column types, FK metadata, identity columns,
 * and listing methods produce correct results.
 *
 * Note: Firebird uppercases unquoted identifiers (folding = UPPER). All
 * comparisons use strtoupper() on the expected value to account for this.
 */
#[Group('functional')]
class FirebirdMetadataProviderTest extends FunctionalTestCase
{
    /** @var non-empty-string */
    private string $tableName;
    /** @var non-empty-string */
    private string $refTableName;
    /** @var non-empty-string */
    private string $viewName;
    /** @var non-empty-string */
    private string $sequenceName;

    /**
     * Step 3.1: Parity between old listTableColumns() and new introspectTableColumnsByUnquotedName().
     *
     * Both code paths query RDB$RELATION_FIELDS with the same SQL but map results
     * through different code (SchemaManager vs MetadataProvider). Column names,
     * types, and nullability must match.
     *
     * @throws Exception
     */
    public function testColumnParityListVsIntrospect(): void
    {
        $table = Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('price')
                    ->setTypeName(Types::DECIMAL)
                    ->setPrecision(10)
                    ->setScale(2)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('description')
                    ->setTypeName(Types::TEXT)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $sm = $this->connection->createSchemaManager();

        // Old API (deprecated, uses SchemaManager select* methods)
        $oldColumns = $sm->listTableColumns($this->tableName);

        // New API (uses MetadataProvider)
        $newColumns = $sm->introspectTableColumnsByUnquotedName($this->tableName);

        self::assertSameSize($oldColumns, $newColumns, 'Column count mismatch between old and new API');

        // Build name-indexed maps for comparison (Firebird uppercases names)
        $oldByName = [];
        foreach ($oldColumns as $column) {
            $oldByName[strtolower($column->getObjectName()->toString())] = $column;
        }

        foreach ($newColumns as $newColumn) {
            $name = strtolower($newColumn->getObjectName()->toString());
            self::assertArrayHasKey($name, $oldByName, 'Column ' . $name . ' missing from old API result');

            $oldColumn = $oldByName[$name];

            self::assertSame(
                Type::lookupName($oldColumn->getType()),
                Type::lookupName($newColumn->getType()),
                'Type mismatch for column ' . $name,
            );
            self::assertSame(
                $oldColumn->getNotnull(),
                $newColumn->getNotnull(),
                'Nullability mismatch for column ' . $name,
            );
        }
    }

    /**
     * Step 3.2a: introspectTableNames() includes user-created tables and excludes system tables.
     *
     * @throws Exception
     */
    public function testIntrospectTableNamesIncludesUserTable(): void
    {
        $table = Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $sm         = $this->connection->createSchemaManager();
        $tableNames = $sm->introspectTableNames();

        $nameValues = array_map(
            static fn ($name): string => strtoupper($name->getUnqualifiedName()->getValue()),
            $tableNames,
        );

        // Firebird uppercases unquoted identifiers
        self::assertContains(
            strtoupper($this->tableName),
            $nameValues,
            'User table not found in introspectTableNames() result',
        );

        // System tables must not appear
        self::assertNotContains('RDB$DATABASE', $nameValues, 'System table leaked into result');
        self::assertNotContains('RDB$RELATIONS', $nameValues, 'System table leaked into result');
    }

    /**
     * Step 3.2b: introspectViews() includes user-created views with correct SQL.
     *
     * @throws Exception
     */
    public function testIntrospectViewsIncludesUserView(): void
    {
        $table = Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $viewSql = sprintf('SELECT id FROM %s', strtoupper($this->tableName));

        $view = View::editor()
            ->setUnquotedName($this->viewName)
            ->setSQL($viewSql)
            ->create();

        $this->connection->createSchemaManager()->createView($view);

        $views = $this->connection->createSchemaManager()->introspectViews();

        $found = false;
        foreach ($views as $introspectedView) {
            if (strtoupper($introspectedView->getName()) === strtoupper($this->viewName)) {
                $found = true;
                self::assertStringContainsString(
                    'SELECT',
                    $introspectedView->getSql(),
                    'View SQL should contain the SELECT statement',
                );
                break;
            }
        }

        self::assertTrue($found, sprintf('View "%s" not found in introspectViews() result', $this->viewName));
    }

    /**
     * Step 3.2c: introspectSequences() includes user-created sequences.
     *
     * @throws Exception
     */
    public function testIntrospectSequencesIncludesUserSequence(): void
    {
        $this->dropSequenceIfExists($this->sequenceName);

        $sequence = Sequence::editor()
            ->setUnquotedName($this->sequenceName)
            ->create();

        $this->connection->createSchemaManager()->createSequence($sequence);

        $sequences = $this->connection->createSchemaManager()->introspectSequences();

        $names = array_map(
            static fn (Sequence $seq): string => strtoupper($seq->getName()),
            $sequences,
        );

        self::assertContains(
            strtoupper($this->sequenceName),
            $names,
            'User sequence not found in introspectSequences() result',
        );
    }

    /**
     * Step 3.3: FK referential actions are correctly introspected via the new API.
     *
     * Tests CASCADE, SET NULL, and NO ACTION through the introspection API
     * (as opposed to the deprecated listTableForeignKeys() used by ForeignKeyConstraintTest).
     *
     * @throws Exception
     */
    public function testForeignKeyReferentialActionIntrospection(): void
    {
        $refTable = Table::editor()
            ->setUnquotedName($this->refTableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $suffix = strtoupper(uniqid());

        $fkCascade = ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_cascade_' . $suffix)
            ->setUnquotedReferencingColumnNames('parent_id')
            ->setUnquotedReferencedTableName($this->refTableName)
            ->setUnquotedReferencedColumnNames('id')
            ->setOnDeleteAction(ReferentialAction::CASCADE)
            ->create();

        $fkSetNull = ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_setnull_' . $suffix)
            ->setUnquotedReferencingColumnNames('nullable_ref')
            ->setUnquotedReferencedTableName($this->refTableName)
            ->setUnquotedReferencedColumnNames('id')
            ->setOnDeleteAction(ReferentialAction::SET_NULL)
            ->create();

        $fkNoAction = ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_noaction_' . $suffix)
            ->setUnquotedReferencingColumnNames('strict_ref')
            ->setUnquotedReferencedTableName($this->refTableName)
            ->setUnquotedReferencedColumnNames('id')
            ->setOnDeleteAction(ReferentialAction::NO_ACTION)
            ->create();

        $table = Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('parent_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('nullable_ref')
                    ->setTypeName(Types::INTEGER)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('strict_ref')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints($fkCascade, $fkSetNull, $fkNoAction)
            ->create();

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($refTable);
        $sm->createTable($table);

        // New API
        $fks = $sm->introspectTableForeignKeyConstraintsByUnquotedName($this->tableName);

        self::assertCount(3, $fks, 'Expected 3 foreign keys');

        // Index by uppercase name (Firebird folding)
        $fkByName = [];
        foreach ($fks as $fk) {
            $objName        = $fk->getObjectName();
            $key            = $objName !== null ? strtoupper($objName->getIdentifier()->getValue()) : '';
            $fkByName[$key] = $fk;
        }

        // CASCADE
        self::assertArrayHasKey('FK_CASCADE_' . $suffix, $fkByName, 'FK cascade not found');
        self::assertSame(
            ReferentialAction::CASCADE,
            $fkByName['FK_CASCADE_' . $suffix]->getOnDeleteAction(),
            'CASCADE action mismatch',
        );

        // SET NULL
        self::assertArrayHasKey('FK_SETNULL_' . $suffix, $fkByName, 'FK set_null not found');
        self::assertSame(
            ReferentialAction::SET_NULL,
            $fkByName['FK_SETNULL_' . $suffix]->getOnDeleteAction(),
            'SET NULL action mismatch',
        );

        // NO ACTION
        self::assertArrayHasKey('FK_NOACTION_' . $suffix, $fkByName, 'FK no_action not found');
        self::assertSame(
            ReferentialAction::NO_ACTION,
            $fkByName['FK_NOACTION_' . $suffix]->getOnDeleteAction(),
            'NO ACTION action mismatch',
        );
    }

    /**
     * Step 3.3b: FK references correct table and column names through introspection.
     *
     * @throws Exception
     */
    public function testForeignKeyReferencesIntrospection(): void
    {
        $refTable = Table::editor()
            ->setUnquotedName($this->refTableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('ref_id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('ref_id')
                    ->create(),
            )
            ->create();

        $table = Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('ext_ref')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_ext_' . strtoupper(uniqid()))
                    ->setUnquotedReferencingColumnNames('ext_ref')
                    ->setUnquotedReferencedTableName($this->refTableName)
                    ->setUnquotedReferencedColumnNames('ref_id')
                    ->create(),
            )
            ->create();

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($refTable);
        $sm->createTable($table);

        $fks = $sm->introspectTableForeignKeyConstraintsByUnquotedName($this->tableName);

        self::assertCount(1, $fks);
        $fk = $fks[0];

        // Column names come back as UnqualifiedName objects; extract raw value
        $refCols  = array_map(
            static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(),
            $fk->getReferencingColumnNames(),
        );
        $refdCols = array_map(
            static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(),
            $fk->getReferencedColumnNames(),
        );

        self::assertSame(['EXT_REF'], $refCols, 'Referencing column names mismatch');
        self::assertSame(
            strtoupper($this->refTableName),
            $fk->getReferencedTableName()->getUnqualifiedName()->getValue(),
            'Referenced table mismatch',
        );
        self::assertSame(['REF_ID'], $refdCols, 'Referenced column names mismatch');
    }

    /**
     * Step 3.4: Identity column (GENERATED BY DEFAULT AS IDENTITY) is introspected correctly.
     *
     * Firebird 3.0+ supports identity columns. The MetadataProvider must detect
     * RDB$IDENTITY_TYPE and set the autoincrement flag on the column.
     *
     * @throws Exception
     */
    public function testIdentityColumnIntrospection(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform instanceof Firebird3Platform) {
            self::markTestSkipped(sprintf(
                'Identity columns require Firebird 3.0+, got %s',
                $platform::class,
            ));
        }

        $table = Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setAutoincrement(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('label')
                    ->setTypeName(Types::STRING)
                    ->setLength(100)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $sm      = $this->connection->createSchemaManager();
        $columns = $sm->introspectTableColumnsByUnquotedName($this->tableName);

        // Find the 'id' column
        $idColumn = null;
        foreach ($columns as $column) {
            if (strtolower($column->getName()) === 'id') {
                $idColumn = $column;
                break;
            }
        }

        self::assertNotNull($idColumn, 'Column "id" not found in introspection result');
        self::assertTrue(
            $idColumn->getAutoincrement(),
            'Column "id" should be detected as autoincrement (identity)',
        );

        // Non-identity column should NOT have autoincrement
        foreach ($columns as $column) {
            if (strtolower($column->getName()) !== 'label') {
                continue;
            }

            self::assertFalse(
                $column->getAutoincrement(),
                'Column "label" should not be autoincrement',
            );
        }
    }

    /**
     * Step 3.1b: Full table introspection - columns + PK + indexes.
     *
     * Creates a table and verifies that introspectTableByUnquotedName()
     * returns all components correctly.
     *
     * @throws Exception
     */
    public function testIntrospectTableWithPrimaryKeyAndIndexes(): void
    {
        $table = Table::editor()
            ->setUnquotedName($this->tableName)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('email')
                    ->setTypeName(Types::STRING)
                    ->setLength(255)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('status')
                    ->setTypeName(Types::STRING)
                    ->setLength(20)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_email_' . strtoupper(uniqid()))
                    ->setUnquotedColumnNames('email')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $sm           = $this->connection->createSchemaManager();
        $introspected = $sm->introspectTableByUnquotedName($this->tableName);

        // Column count
        self::assertCount(3, $introspected->getColumns());

        // PK constraint
        $pk = $introspected->getPrimaryKeyConstraint();
        self::assertNotNull($pk, 'Primary key should be introspected');
        $pkCols = array_map(
            static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(),
            $pk->getColumnNames(),
        );
        self::assertContains('ID', $pkCols);

        // User index should be present (PK-backed index is excluded by MetadataProvider)
        $foundIndex = false;
        foreach ($introspected->getIndexes() as $index) {
            if (str_starts_with(strtoupper($index->getName()), 'IDX_EMAIL_')) {
                $foundIndex = true;
                break;
            }
        }

        self::assertTrue($foundIndex, 'User index should be introspected');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $suffix             = uniqid();
        $this->tableName    = 'md_test_' . $suffix;
        $this->refTableName = 'md_ref_' . $suffix;
        $this->viewName     = 'md_view_' . $suffix;
        $this->sequenceName = 'md_seq_' . $suffix;
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();
        $sm = $this->connection->createSchemaManager();

        try {
            $sm->dropView($this->viewName);
        } catch (Throwable) {
            // View may not exist
        }

        foreach ([$this->tableName, $this->refTableName] as $table) {
            try {
                $sm->dropTable($table);
            } catch (Throwable) {
                // Table may not exist
            }
        }

        $this->dropSequenceIfExists($this->sequenceName);

        parent::tearDown();
    }
}
