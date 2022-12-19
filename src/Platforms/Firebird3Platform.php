<?php
namespace Kafoso\DoctrineFirebirdDriver\Platforms;

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
    public function getName()
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
        $sql = [];
        $commentsSQL = [];
        $columnSql = [];

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $query = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;

            $comment = $this->getColumnComment($column);

            if (null !== $comment && '' !== $comment) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                        $diff->getName($this)->getQuotedName($this), $column->getQuotedName($this), $comment
                );
            }
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $query = 'DROP ' . $column->getQuotedName($this);
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
        }

        foreach ($diff->changedColumns as $columnDiff) {
            /** @var $columnDiff \Doctrine\DBAL\Schema\ColumnDiff */
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = $columnDiff->getOldColumnName()->getQuotedName($this);
            $column = $columnDiff->column;

            if ($columnDiff->hasChanged('type') || $columnDiff->hasChanged('precision') || $columnDiff->hasChanged('scale') || $columnDiff->hasChanged('fixed')) {
                $type = $column->getType();

                $query = 'ALTER COLUMN ' . $oldColumnName . ' TYPE ' . $type->getSqlDeclaration($column->toArray(), $this);
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }

            if ($columnDiff->hasChanged('default') || $columnDiff->hasChanged('type')) {
                $defaultClause = null === $column->getDefault() ? ' DROP DEFAULT' : ' SET' . $this->getDefaultValueDeclarationSQL($column->toArray());
                $query = 'ALTER ' . $oldColumnName . $defaultClause;
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }

            if ($columnDiff->hasChanged('notnull')) {

                /**
                 * https://www.delphipraxis.net/191043-firebird-3-0-rdb%24relation_fields-update.html
                 * Firebird 3.0.1 Release Notes S. 70:
                 * ALTER TABLE <table name> ALTER <field name> { DROP | SET } [NOT] NULL
                 * ALTER DOMAIN <domain name> { DROP | SET } [NOT] NU
                 */

                /**
                 * Firebird 2.5 Code
                 */
                /**
                    $newNullFlag = $column->getNotnull() ? 1 : 'NULL';
                    $sql[] = 'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = ' .
                    $newNullFlag . ' ' .
                    'WHERE UPPER(RDB$FIELD_NAME) = ' .
                    'UPPER(\'' . $columnDiff->getOldColumnName()->getName() . '\') AND ' .
                    'UPPER(RDB$RELATION_NAME) = UPPER(\'' . $diff->getName($this)->getName() . '\')';
                 */

                if($column->getNotnull()) {
                    $sql[] = 'ALTER TABLE '. $diff->getName($this)->getName().' ALTER ' . $columnDiff->getOldColumnName()->getName() . ' SET NOT NULL';
                } else {
                    $sql[] = 'ALTER TABLE '. $diff->getName($this)->getName().' ALTER ' . $columnDiff->getOldColumnName()->getName() . ' DROP NOT NULL';
                }

            }

            if ($columnDiff->hasChanged('autoincrement')) {
                if ($column->getAutoincrement()) {
                    // add autoincrement
                    $seqName = $this->getIdentitySequenceName($diff->name, $oldColumnName);

                    $sql[] = "CREATE SEQUENCE " . $seqName;
                    $sql[] = "SELECT setval('" . $seqName . "', (SELECT MAX(" . $oldColumnName . ") FROM " . $diff->getName($this)->getQuotedName($this) . "))";
                    $query = "ALTER " . $oldColumnName . " SET DEFAULT nextval('" . $seqName . "')";
                    $sql[] = "ALTER TABLE " . $diff->getName($this)->getQuotedName($this) . " " . $query;
                } else {
                    // Drop autoincrement, but do NOT drop the sequence. It might be re-used by other tables or have
                    $query = "ALTER " . $oldColumnName . " " . "DROP DEFAULT";
                    $sql[] = "ALTER TABLE " . $diff->getName($this)->getQuotedName($this) . " " . $query;
                }
            }

            if ($columnDiff->hasChanged('comment')) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                        $diff->getName($this)->getQuotedName($this), $column->getQuotedName($this), $this->getColumnComment($column)
                );
            }

            if ($columnDiff->hasChanged('length')) {
                $query = 'ALTER COLUMN ' . $oldColumnName . ' TYPE ' . $column->getType()->getSqlDeclaration($column->toArray(), $this);
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
            }
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new Identifier($oldColumnName);

            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) .
                    ' ALTER COLUMN ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
        }

        $tableSql = [];

        if (!$this->onSchemaAlterTable($diff, $tableSql)) {
            $sql = array_merge($sql, $commentsSQL);

            if ($diff->newName !== false) {
                throw \Doctrine\DBAL\DBALException::notSupported(__METHOD__ . ' Cannot rename tables because firebird does not support it');
            }

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

}
