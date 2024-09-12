<?php
namespace Kafoso\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Exception;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Kafoso\DoctrineFirebirdDriver\Platforms\Keywords\Firebird3Keywords;

class Firebird3Platform extends FirebirdInterbasePlatform
{
    /**
     * Firebird 3 has a native Boolean Type
     */
    protected bool $hasNativeBooleanType = true;

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName(): string
    {
        return "Firebird3";
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return Firebird3Keywords::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql         = [];
        $commentsSQL = [];
        $columnSql   = [];

        $table = $diff->getOldTable() ?? $diff->getName($this);
        $tableNameSQL = $table->getQuotedName($this);

        foreach ($diff->getAddedColumns() as $addedColumn) {
            if ($this->onSchemaAlterTableAddColumn($addedColumn, $diff, $columnSql)) {
                continue;
            }

            $query = 'ADD ' . $this->getColumnDeclarationSQL(
                $addedColumn->getQuotedName($this),
                $addedColumn->toArray()
            );

            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;

            $comment = $this->getColumnComment($addedColumn);

            if ($comment === null || $comment === '') {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $tableNameSQL,
                    $addedColumn->getQuotedName($this),
                    $comment,
            );

        }

        foreach ($diff->getDroppedColumns() as $droppedColumn) {
            if ($this->onSchemaAlterTableRemoveColumn($droppedColumn, $diff, $columnSql)) {
                continue;
            }

            $query = 'DROP ' . $droppedColumn->getQuotedName($this);
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
        }

        foreach ($diff->getModifiedColumns() as $columnDiff) {

            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $oldColumn = $columnDiff->getOldColumn() ?? $columnDiff->getOldColumnName();
            $newColumn = $columnDiff->getNewColumn();

            $oldColumnName = $oldColumn->getQuotedName($this);

            if (
                $columnDiff->hasTypeChanged()
                || $columnDiff->hasPrecisionChanged()
                || $columnDiff->hasScaleChanged()
                || $columnDiff->hasFixedChanged()
            ) {
                $type = $newColumn->getType();
                $columnDefinition                  = $newColumn->toArray();
                $columnDefinition['autoincrement'] = false;

                $query = 'ALTER COLUMN ' . $oldColumnName . ' TYPE ' . $type->getSqlDeclaration($columnDefinition, $this);
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasDefaultChanged()) {
                $defaultClause = null === $newColumn->getDefault()
                    ? ' DROP DEFAULT'
                    : ' SET' . $this->getDefaultValueDeclarationSQL($newColumn->toArray());
                $query = 'ALTER ' . $oldColumnName . $defaultClause;
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasNotNullChanged()) {
                /**
                 * https://www.delphipraxis.net/191043-firebird-3-0-rdb%24relation_fields-update.html
                 * Firebird 3.0.1 Release Notes S. 70:
                 */

                $query = 'ALTER ' . $oldColumnName . ' ' . ($newColumn->getNotnull() ? 'SET' : 'DROP') . ' NOT NULL';
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasAutoIncrementChanged()) {
                // Step 1: Add a new temporary column with the desired data type
                $temp_column = $this->getTemporaryColumnName($oldColumnName);
                $type = $newColumn->getType();
                $columnDefinition                  = $newColumn->toArray();

                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ADD ' . $this->getColumnDeclarationSQL(
                        $temp_column,
                        $columnDefinition
                    );

                // Step 2: Copy the data from the original column to the temporary column
                $sql[] = 'UPDATE ' . $tableNameSQL . ' SET ' . $temp_column . '='. $oldColumnName .' )';
                // Step 3: Drop the original column
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' DROP ' . $oldColumnName;
                // Step 4: Rename the temporary column to the original column name
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ALTER COLUMN ' . $temp_column . ' TO '. $oldColumnName;
                // ToDo: Step 5: (Optional) Recreate any indexes or constraints on the new column
                // For example, if my_column was part of the primary key, you would need to re-add the primary key constraint
                // $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ADD PRIMARY KEY ('. $oldColumnName . ')';
            }

            $oldComment = $this->getOldColumnComment($columnDiff);
            $newComment = $this->getColumnComment($newColumn);
            if (
                $columnDiff->hasCommentChanged()
                || ($columnDiff->getOldColumn() !== null && $oldComment !== $newComment)
            ) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $tableNameSQL,
                    $newColumn->getQuotedName($this),
                    $newComment,
                );
            }


            if (! $columnDiff->hasLengthChanged()) {
                continue;
            }

            $query = 'ALTER ' . $oldColumnName . ' TYPE '
                . $newColumn->getType()->getSQLDeclaration($newColumn->toArray(), $this);
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
        }

        foreach ($diff->getRenamedColumns() as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new Identifier($oldColumnName);

            $sql[] = 'ALTER TABLE ' .$tableNameSQL .
                    ' ALTER COLUMN ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
        }

        $tableSql = [];

        if (!$this->onSchemaAlterTable($diff, $tableSql)) {
            $sql = array_merge($sql, $commentsSQL);

            $newName = $diff->getNewName();

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getCreateAutoincrementSql($column, $tableName)
    {
        return [];
    }

    public function getDropAutoincrementSql($table)
    {
        return [];
    }

    protected function _getCommonIntegerTypeDeclarationSQL(array $column)
    {
        $autoinc = '';
        if (! empty($column['autoincrement'])) {
            $autoinc = ' GENERATED BY DEFAULT AS IDENTITY';
        }

        return $autoinc;
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        $query = <<<'___query___'
            SELECT TRIM(r.RDB$FIELD_NAME) AS "FIELD_NAME",
            TRIM(f.RDB$FIELD_NAME) AS "FIELD_DOMAIN",
            TRIM(f.RDB$FIELD_TYPE) AS "FIELD_TYPE",
            TRIM(typ.RDB$TYPE_NAME) AS "FIELD_TYPE_NAME",
            f.RDB$FIELD_SUB_TYPE AS "FIELD_SUB_TYPE",
            f.RDB$FIELD_LENGTH AS "FIELD_LENGTH",
            f.RDB$CHARACTER_LENGTH AS "FIELD_CHAR_LENGTH",
            f.RDB$FIELD_PRECISION AS "FIELD_PRECISION",
            f.RDB$FIELD_SCALE AS "FIELD_SCALE",
            MIN(TRIM(rc.RDB$CONSTRAINT_TYPE)) AS "FIELD_CONSTRAINT_TYPE",
            MIN(TRIM(i.RDB$INDEX_NAME)) AS "FIELD_INDEX_NAME",
            r.RDB$NULL_FLAG as "FIELD_NOT_NULL_FLAG",
            r.RDB$DEFAULT_SOURCE AS "FIELD_DEFAULT_SOURCE",
            r.RDB$FIELD_POSITION AS "FIELD_POSITION",
            r.RDB$DESCRIPTION AS "FIELD_DESCRIPTION",
            f.RDB$CHARACTER_SET_ID as "CHARACTER_SET_ID",
            TRIM(cs.RDB$CHARACTER_SET_NAME) as "CHARACTER_SET_NAME",
            f.RDB$COLLATION_ID as "COLLATION_ID",
            TRIM(cl.RDB$COLLATION_NAME) as "COLLATION_NAME",
            TRIM(r.RDB$IDENTITY_TYPE) AS "IDENTITY_TYPE" 
            FROM RDB$RELATION_FIELDS r
            LEFT OUTER JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
            LEFT OUTER JOIN RDB$INDEX_SEGMENTS s ON s.RDB$FIELD_NAME=r.RDB$FIELD_NAME
            LEFT OUTER JOIN RDB$INDICES i ON i.RDB$INDEX_NAME = s.RDB$INDEX_NAME AND i.RDB$RELATION_NAME = r.RDB$RELATION_NAME
            LEFT OUTER JOIN RDB$RELATION_CONSTRAINTS rc ON rc.RDB$INDEX_NAME = s.RDB$INDEX_NAME AND rc.RDB$INDEX_NAME = i.RDB$INDEX_NAME AND rc.RDB$RELATION_NAME = i.RDB$RELATION_NAME
            LEFT OUTER JOIN RDB$REF_CONSTRAINTS REFC ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
            LEFT OUTER JOIN RDB$TYPES typ ON typ.RDB$FIELD_NAME = 'RDB$FIELD_TYPE' AND typ.RDB$TYPE = f.RDB$FIELD_TYPE
            LEFT OUTER JOIN RDB$TYPES sub ON sub.RDB$FIELD_NAME = 'RDB$FIELD_SUB_TYPE' AND sub.RDB$TYPE = f.RDB$FIELD_SUB_TYPE
            LEFT OUTER JOIN RDB$CHARACTER_SETS cs ON cs.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID
            LEFT OUTER JOIN RDB$COLLATIONS cl ON cl.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID AND cl.RDB$COLLATION_ID = f.RDB$COLLATION_ID
            WHERE UPPER(r.RDB$RELATION_NAME) = UPPER(':TABLE')
            GROUP BY "FIELD_NAME", "FIELD_DOMAIN", "FIELD_TYPE", "FIELD_TYPE_NAME", "FIELD_SUB_TYPE",  "FIELD_LENGTH",
                     "FIELD_CHAR_LENGTH", "FIELD_PRECISION", "FIELD_SCALE", "FIELD_NOT_NULL_FLAG", "FIELD_DEFAULT_SOURCE",
                     "FIELD_POSITION",
                     "CHARACTER_SET_ID",
                     "CHARACTER_SET_NAME",
                     "COLLATION_ID",
                     "COLLATION_NAME",
                     "FIELD_DESCRIPTION",
                     "IDENTITY_TYPE"
            ORDER BY "FIELD_POSITION"
___query___;
        return str_replace(':TABLE', $this->unquotedIdentifierName($table), $query);
    }

    /**
     * @inheritDoc
     */
    public function usesSequenceEmulatedIdentityColumns()
    {
        return true; //
    }

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new DefaultSelectSQLBuilder($this, 'FOR UPDATE', 'SKIP LOCKED');
    }

    /**
     * {@inheritDoc}
     */

    public function getCreateSequenceSQL(Sequence $sequence)
    {
        return $this->getExecuteBlockWithExecuteStatementsSql([
            'statements' => [
                'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
                    ' START WITH ' . $sequence->getInitialValue() .
                    ' INCREMENT BY ' . $sequence->getAllocationSize() .
                    $this->getSequenceCacheSQL($sequence),
                $this->getCreateSequenceCommentSQL($sequence)
            ],
            'formatLineBreak' => true,
        ]);
    }

    public function getCreateSequenceCommentSQL(Sequence $sequence)
    {
        return 'COMMENT ON SEQUENCE ' . $sequence->getQuotedName($this) . ' IS ' . $this->quoteStringLiteral($this->getSequenceCommentString($sequence));
    }

    /**
     * Returns the Sequence Config as JSON
     */
    private function getSequenceCommentString(Sequence $sequence)
    {
        return json_encode([
            'name' => $sequence->getName(),
            'initialValue' => $sequence->getInitialValue(),
            'allocationSize' => $sequence->getAllocationSize(),
            'cache' => $sequence->getCache()
        ]);
    }

    /**
     * Cache definition for sequences
     */
    private function getSequenceCacheSQL(Sequence $sequence): string
    {
        if ($sequence->getCache() > 1) {
            return ' CACHE ' . $sequence->getCache();
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        return $this->getExecuteBlockWithExecuteStatementsSql([
            'statements' => [ 'ALTER SEQUENCE ' . $sequence->getQuotedName($this)
                . ' RESTART WITH ' . $sequence->getInitialValue()
                . ' INCREMENT BY ' . $sequence->getAllocationSize()
                . $this->getSequenceCacheSQL($sequence) ,
                $this->getSequenceCommentString($sequence)
            ],
            'formatLineBreak' => true,
        ]);
    }

    /**
     * @inheritDoc
     *
     * In Firebird 3
     */
    public function getIdentitySequenceName($tableName, $columnName)
    {
        $table = new Identifier($tableName);
        $colum = new Identifier($columnName);
        $query = "SELECT RDB\$GENERATOR_NAME as GENERATOR_NAME
              FROM RDB\$RELATION_FIELDS rf
              JOIN RDB\$FIELDS f ON rf.RDB\$FIELD_SOURCE = f.RDB\$FIELD_NAME
              WHERE rf.RDB\$RELATION_NAME = UPPER('" .$table->getQuotedName($this) . "')
                AND rf.RDB\$FIELD_NAME = UPPER('" .$colum->getQuotedName($this) . "')
                AND rf.RDB\$IDENTITY_TYPE IS NOT NULL";  // Identity column flag

        return $query;

    }
    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['boolean'] = Types::BOOLEAN;
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $column)
    {
        return 'BOOLEAN';
    }

    /**
     * {@inheritDoc}
     */
    public function isCommentedDoctrineType(Type $doctrineType)
    {
        return AbstractPlatform::isCommentedDoctrineType($doctrineType);
    }
}
