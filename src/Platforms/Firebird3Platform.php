<?php
namespace Kafoso\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Table;
use Kafoso\DoctrineFirebirdDriver\Platforms\Keywords\Firebird3Keywords;
use Kafoso\DoctrineFirebirdDriver\Platforms\Keywords\FirebirdInterbaseKeywords;

class Firebird3Platform extends FirebirdInterbasePlatform
{
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
     *
     * Taken from the PostgreSql-Driver and adapted for Firebird
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
                 * ALTER TABLE <table name> ALTER <field name> { DROP | SET } [NOT] NULL
                 * ALTER DOMAIN <domain name> { DROP | SET } [NOT] NU
                 */

                $query = 'ALTER ' . $oldColumnName . ' ' . ($newColumn->getNotnull() ? 'SET' : 'DROP') . ' NOT NULL';
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasAutoIncrementChanged()) {
                if ($newColumn->getAutoincrement()) {
                    // add autoincrement
                    $seqName = $this->getIdentitySequenceName(
                        $table->getName(),
                        $oldColumnName,
                    );

                    $sql[] = 'CREATE SEQUENCE ' . $seqName;
                    $sql[] = "SELECT setval('" . $seqName . "', (SELECT MAX(" . $oldColumnName . ') FROM '
                        . $tableNameSQL . '))';
                    $query = 'ALTER ' . $oldColumnName . " SET DEFAULT nextval('" . $seqName . "')";
                } else {
                    // Drop autoincrement, but do NOT drop the sequence. It might be re-used by other tables or have
                    $query = 'ALTER ' . $oldColumnName . ' DROP DEFAULT';
                }

                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
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


            if ($newName !== false) {
                throw Exception::notSupported(__METHOD__ . ' Cannot rename table because firebird does not support it');
            }

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


}
