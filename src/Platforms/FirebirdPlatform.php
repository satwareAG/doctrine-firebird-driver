<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DateIntervalUnit;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;
use Satag\DoctrineFirebirdDriver\DBAL\FirebirdBooleanType;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\FirebirdKeywords;
use Satag\DoctrineFirebirdDriver\Platforms\SQL\Builder\FirebirdSelectSQLBuilder;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;
use Satag\DoctrineFirebirdDriver\ValueFormatter;

use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function crc32;
use function end;
use function explode;
use function floor;
use function func_get_arg;
use function func_num_args;
use function implode;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function stripos;
use function strlen;
use function strtoupper;
use function substr_replace;

use const PHP_INT_MAX;

/**
 * Provides the behaviour, features and SQL dialect of the Firebird SQL server database platform
 * of the oldest supported version.
 */
class FirebirdPlatform extends AbstractPlatform
{
    /**
     * Firebird 2.5 has no native Boolean Type
     */
    protected bool $hasNativeBooleanType = false;
    private string $charTrue             = 'Y';

    private string $charFalse = 'N';

    /**
     * If false we use CHAR(1) field instead of SMALLINT for Boolean Type
     */
    private bool $useSmallIntBoolean = true;

    public function __construct()
    {
        Type::overrideType('boolean', FirebirdBooleanType::class);
    }

    public function setCharTrue(string $char): FirebirdPlatform
    {
        $this->charTrue = $char;

        return $this;
    }

    public function setCharFalse(string $char): FirebirdPlatform
    {
        $this->charFalse = $char;

        return $this;
    }

    public function setUseSmallIntBoolean(bool $useSmallIntBoolean): self
    {
        $this->useSmallIntBoolean = $useSmallIntBoolean;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4749',
            'FirebirdPlatform::getName() is deprecated. Identify platforms by their class.',
        );
        $classParts = explode('\\', static::class);

        return str_replace('Platform', '', end($classParts));
    }

    public function getMaxIdentifierLength(): int
    {
        return 31;
    }

    /**
     * Returns the max length of constraint names
     */
    public function getMaxConstraintIdentifierLength(): int
    {
        return 27;
    }

    /**
     * Checks if the identifier exceeds the platform limit
     *
     * @param Identifier|string $aIdentifier The identifier to check
     * @param int               $maxLength   Length limit to check. Usually the result of
     *                                           {@link getMaxIdentifierLength()} should be passed
     *
     * @throws Exception
     */
    public function checkIdentifierLength(Identifier|string $aIdentifier, int|null $maxLength = null): void
    {
        $maxLength ?? $maxLength = $this->getMaxIdentifierLength();
        $name                    = $aIdentifier instanceof AbstractAsset ?
                $aIdentifier->getName() : $aIdentifier;

        if (strlen($name) > $maxLength) {
            throw Exception::notSupported('Identifier ' . $name . ' is too long for firebird platform. Maximum identifier length is ' . $maxLength);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRegexpExpression()
    {
        return 'SIMILAR TO';
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos === false) {
            return 'POSITION (' . $substr . ' in ' . $str . ')';
        }

        return 'POSITION (' . $substr . ', ' . $str . ', ' . $startPos . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddDaysExpression($date, $days)
    {
        return 'DATEADD(' . $days . ' DAY TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return 'BIN_AND (' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return 'BIN_OR (' . $value1 . ', ' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubDaysExpression($date, $days)
    {
        return 'DATEADD(-' . $days . ' DAY TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateAddMonthExpression($date, $months)
    {
        return 'DATEADD(' . $months . ' MONTH TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateSubMonthExpression($date, $months)
    {
        return 'DATEADD(-' . $months . ' MONTH TO ' . $date . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return 'DATEDIFF(day, ' . $date2 . ',' . $date1 . ')';
    }

    public function supportsForeignKeyConstraints()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function usesSequenceEmulatedIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentitySequenceName($tableName, $columnName): string
    {
        return $this->generateIdentifier([$tableName], 'D2IS', $this->getMaxIdentifierLength())->getQuotedName($this);
    }

    public function getIdentitySequenceTriggerName(mixed $tableName, mixed $columnName): string
    {
        return $this->generateIdentifier([$tableName], 'D2IT', $this->getMaxIdentifierLength())->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsViews()
    {
        return true;
    }

     /**
      * {@inheritDoc}
      */
    public function supportsIdentityColumns()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsInlineColumnComments()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCommentOnStatement()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCreateDropDatabase()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * Signals that the firebird driver supports limited rows
     *
     * The SQL is build in doModifyLimitQuery
     */
    public function supportsLimitOffset(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function prefersIdentityColumns()
    {
        return false;
    }

    /** @inheritDoc */
    public function getListTablesSQL()
    {
        return 'SELECT TRIM(RDB$RELATION_NAME) AS RDB$RELATION_NAME
                FROM RDB$RELATIONS
                WHERE
                    (RDB$SYSTEM_FLAG=0 OR RDB$SYSTEM_FLAG IS NULL) and
                    (RDB$VIEW_BLR IS NULL)';
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database)
    {
        return 'SELECT
                    TRIM(RDB$RELATION_NAME) AS RDB$RELATION_NAME,
                    TRIM(RDB$VIEW_SOURCE) AS RDB$VIEW_SOURCE
                FROM RDB$RELATIONS
                WHERE
                    (RDB$SYSTEM_FLAG=0 OR RDB$SYSTEM_FLAG IS NULL) and
                    (RDB$RELATION_TYPE = 1)';
    }

    /**
     * {@inheritDoc}
     *
     * See: How to run a select without table? https://www.firebirdfaq.org/faq30/
     */
    public function getDummySelectSQL()
    {
        $expression = func_num_args() > 0 ? func_get_arg(0)  : '1';

        return sprintf('SELECT %s FROM RDB$DATABASE', $expression);
    }

    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL($name)
    {
        return 'DROP VIEW ' . $name;
    }

    /**
     * Generates a PSQL-Statement to drop all views of a table
     *
     * Note: This statement needs a variable TMP_VIEW_NAME VARCHAR(255) declared
     *
     * @param string|Table $table
     * @param bool $inBlock
     * @return string
     */
    public function getDropAllViewsOfTablePSqlSnippet(string|Table $table, bool $inBlock = false): string
    {
        $result = 'FOR SELECT TRIM(v.RDB$VIEW_NAME) ' .
                'FROM RDB$VIEW_RELATIONS v, RDB$RELATIONS r ' .
                'WHERE ' .
                'TRIM(UPPER(v.RDB$RELATION_NAME)) = TRIM(UPPER(' . $this->quoteStringLiteral($this->unquotedIdentifierName($table)) . ')) AND ' .
                'v.RDB$RELATION_NAME = r.RDB$RELATION_NAME AND ' .
                '(r.RDB$SYSTEM_FLAG IS DISTINCT FROM 1) AND ' .
                '(r.RDB$RELATION_TYPE = 0) INTO :TMP_VIEW_NAME DO BEGIN ' .
                'EXECUTE STATEMENT \'DROP VIEW "\'||:TMP_VIEW_NAME||\'"\'; END';

        if ($inBlock) {
            $result = $this->getExecuteBlockSql(
                [
                    'statements' => $result,
                    'formatLineBreak' => false,
                    'blockVars' => ['TMP_VIEW_NAME' => 'varchar(255)'],
                ],
            );
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        if ($sequence->getInitialValue() === 1) {
            return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this);
        }

        // Firebird 2.5 only supports setting a start Value for an generator
        if ($sequence->getAllocationSize() > 1) {
            throw Exception::notSupported(sprintf(__METHOD__ . ' with an allocation size > 1 (%s given)', $sequence->getAllocationSize()));
        }

        if ($sequence->getCache() !== null) {
            throw Exception::notSupported(sprintf(__METHOD__ . ' with cache not null (%s given)', $sequence->getCache()));
        }

        return $this->getExecuteBlockWithExecuteStatementsSql([
            'statements' => [
                'CREATE SEQUENCE ' . $sequence->getQuotedName($this),
                'SET GENERATOR ' . $sequence->getQuotedName($this) . ' TO ' . $sequence->getInitialValue(),
            ],
            'formatLineBreak' => true,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $quotedIdentifierColumnName)
    {
        return 'INSERT INTO ' . $quotedTableName . ' DEFAULT VALUES';
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
                ' RESTART WITH ' . ($sequence->getInitialValue() - 1);
    }

    /**
     * {@inheritDoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if (! ($sequence instanceof Sequence)) {
            $sequence = new Sequence($sequence);
        }

        $sequenceName = $sequence->getQuotedName($this);
        if (stripos($sequenceName, '_D2IS') !== false) {
            // Seems to be a autoinc-sequence. Try to drop trigger before
            $triggerName = str_replace('_D2IS', '_D2IT', $sequenceName);

            return $this->getExecuteBlockWithExecuteStatementsSql([
                'statements' => [
                    $this->getDropTriggerIfExistsPSql($triggerName, true),
                    $this->getDropSequenceIfExistsPSql($sequence, true),
                ],
                'formatLineBreak' => false,
            ]);
        }

        return parent::getDropSequenceSQL($sequence);
    }

    /**
     * {@inheritDoc}
     *
     * Foreign keys are identified via constraint names in firebird
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        return $this->getDropConstraintSQL($foreignKey, $table);
    }

    /**
     * Returns just the function used to get the next value of a sequence
     */
    public function getSequenceNextValFunctionSQL(string $sequenceName): string
    {
        return 'NEXT VALUE FOR ' . $sequenceName;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $sequence
     *
     * @return string
     */
    public function getSequenceNextValSQL($sequence): string
    {
        return 'SELECT ' . $this->getSequenceNextValFunctionSQL($sequence) . ' FROM RDB$DATABASE';
    }

    /**
     * {@inheritDoc}
     *
     * It's not possible to set a default isolation level or change the isolation level of of
     * a running transaction on Firebird, because the SET TRANSACTION command starts a new
     * transaction
     */
    public function getSetTransactionIsolationSQL($level)
    {
        $sql = '';
        match ($level) {
            TransactionIsolationLevel::READ_UNCOMMITTED
            => $sql .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ UNCOMMITTED RECORD_VERSION',
            TransactionIsolationLevel::READ_COMMITTED
            => $sql .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL READ COMMITTED RECORD_VERSION',
            TransactionIsolationLevel::REPEATABLE_READ
            => $sql .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT',
            TransactionIsolationLevel::SERIALIZABLE
            => $sql .= 'SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT TABLE STABILITY',
            default => throw new DriverException(sprintf(
                'Isolation level %s is not supported',
                ValueFormatter::cast($level),
            )),
        };

        return $sql;
    }

    public function getBooleanTypeDeclarationSQL(array $column)
    {
        return 'SMALLINT';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $column)
    {
        return 'INTEGER' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $column)
    {
        return 'BIGINT' . $this->_getCommonIntegerTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     *
     * NOTE: This statement also tries to drop related views and the trigger used to simulate autoinc-fields
     */
    public function getDropTableSQL($table): string
    {
        $tableArg = $table;

        if ($table instanceof Table) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4798',
                'Passing $table as a Table object to %s is deprecated. Pass it as a quoted name instead.',
                __METHOD__,
            );

            $table = $table->getQuotedName($this);
        }

        if (! is_string($table)) {
            throw new \InvalidArgumentException(
                __METHOD__ . '() expects $table parameter to be string or ' . Table::class . '.',
            );
        }

        $statements = [];

        $statements[] = $this->getDropTriggerIfExistsPSql($this->getIdentitySequenceTriggerName($table, null), true);
        $statements[] = $this->getDropAllViewsOfTablePSqlSnippet($table, true);

        $dropAutoincrementSql = $this->getDropAutoincrementSql($table);
        if ($dropAutoincrementSql !== '') {
            $statements[] = $dropAutoincrementSql;
        }

        $statements[] =
        $this->getExecuteBlockWithExecuteStatementsSql([
            'statements' => [
                parent::getDropTableSQL($table),
            ],
            'formatLineBreak' => false,
        ]);

        return $this->getExecuteBlockWithExecuteStatementsSql(['statements' => $statements]);
    }

    /**
     * {@inheritDoc}
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        $identifier = new Identifier($tableName);
        $tableName  = $identifier->getQuotedName($this);

        return 'DELETE FROM ' . $this->normalizeIdentifier($tableName)->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s';
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

        $table        = $diff->getOldTable() ?? $diff->getName($this);
        $tableNameSQL = $table->getQuotedName($this);

        foreach ($diff->getAddedColumns() as $addedColumn) {
            if ($this->onSchemaAlterTableAddColumn($addedColumn, $diff, $columnSql)) {
                continue;
            }

            $query = 'ADD ' . $this->getColumnDeclarationSQL(
                $addedColumn->getQuotedName($this),
                $addedColumn->toArray(),
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
                $type                              = $newColumn->getType();
                $columnDefinition                  = $newColumn->toArray();
                $columnDefinition['autoincrement'] = false;

                $query = 'ALTER COLUMN ' . $oldColumnName . ' TYPE ' . $type->getSQLDeclaration($columnDefinition, $this);
                $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasDefaultChanged()) {
                $defaultClause = $newColumn->getDefault() === null
                    ? ' DROP DEFAULT'
                    : ' SET' . $this->getDefaultValueDeclarationSQL($newColumn->toArray());
                $query         = 'ALTER ' . $oldColumnName . $defaultClause;
                $sql[]         = 'ALTER TABLE ' . $tableNameSQL . ' ' . $query;
            }

            if ($columnDiff->hasNotNullChanged()) {
                $newNullFlag = $newColumn->getNotnull() ? 1 : 'NULL';
                $sql[]       = 'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = ' .
                        $newNullFlag . ' ' .
                        'WHERE UPPER(RDB$FIELD_NAME) = ' .
                        'UPPER(\'' . $columnDiff->getOldColumnName()->getName() . '\') AND ' .
                        'UPPER(RDB$RELATION_NAME) = UPPER(\'' . $diff->getName($this)->getName() . '\')';
            }

            if ($columnDiff->hasAutoIncrementChanged()) {
                if ($newColumn->getAutoincrement()) {
                    // add autoincrement
                    $seqName = $this->getIdentitySequenceName($diff->name, $oldColumnName);

                    $sql[] = 'CREATE SEQUENCE ' . $seqName;
                    $sql[] = "SELECT setval('" . $seqName . "', (SELECT MAX(" . $oldColumnName . ') FROM ' . $diff->getName($this)->getQuotedName($this) . '))';
                    $query = 'ALTER ' . $oldColumnName . " SET DEFAULT nextval('" . $seqName . "')";
                    $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
                } else {
                    // Drop autoincrement, but do NOT drop the sequence. It might be re-used by other tables or have
                    $query = 'ALTER ' . $oldColumnName . ' ' . 'DROP DEFAULT';
                    $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . $query;
                }
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

            $sql[] = 'ALTER TABLE ' . $tableNameSQL .
                    ' ALTER COLUMN ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
        }

        $tableSql = [];

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            $sql = array_merge($sql, $commentsSQL);

            $newName = $diff->getNewName();

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff),
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritDoc}
     *
     * Actually Firebird can store up to 32K bytes in a varchar, but we assume UTF8, thus the limit is 8191
     * https://firebirdsql.org/file/documentation/chunk/en/refdocs/fblangref40/fblangref40-datatypes-chartypes.html
     */
    public function getVarcharMaxLength()
    {
        return $this->getBinaryMaxLength();
    }

    /**
     * {@inheritDoc}
     *
     * Varchars character set binary are used for small blob/binary fields.
     */
    public function getBinaryMaxLength()
    {
        return 8191;
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $column)
    {
        return 'SMALLINT';
    }

    /**
     * @inheritDoc
     */
    public function getClobTypeDeclarationSQL(array $column): string
    {
        if (
            isset($column['length']) && is_numeric($column['length']) &&
                $column['length'] <= $this->getVarcharMaxLength()
        ) {
            return 'VARCHAR(' . $column['length'] . ')';
        }

        return 'BLOB SUB_TYPE TEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $column)
    {
        return 'BLOB';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column)
    {
        return 'TIMESTAMP';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $column)
    {
        return 'TIME';
    }

    /** @inheritDoc */
    public function getDateTypeDeclarationSQL(array $column)
    {
        return 'DATE';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $charset name of the charset
     *
     * @return string  DBMS specific SQL code portion needed to set the CHARACTER SET
     *                 of a field declaration.
     */
    public function getColumnCharsetDeclarationSQL($charset): string
    {
        if ($charset !== '') {
            return ' CHARACTER SET ' . $charset;
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getColumnDeclarationSQL($name, array $column)
    {
        if (isset($column['type']) && $column['type']->getName() === Types::BINARY) {
            $column['charset'] = 'octets';
        }

        return parent::getColumnDeclarationSQL($name, $column);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return 'CREATE GLOBAL TEMPORARY TABLE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableSQL()
    {
        return 'GLOBAL TEMPORARY';
    }

    /**
     * {@inheritdoc }
     */
    public function getCreateTableSQL(Table $table, $createFlags = self::CREATE_INDEXES)
    {
        if (! $this->hasNativeBooleanType) {
            foreach ($table->getColumns() as $column) {
                if ($column->getType()->getName() !== Types::BOOLEAN) {
                    continue;
                }

                $column->setComment(($column->getComment() ?? '') . $this->getDoctrineTypeComment(Type::getType(Types::BOOLEAN)));
                if ($this->useSmallIntBoolean) {
                    continue;
                }

                $column->setType(Type::getType(Types::STRING));
                $column->setLength(1);
            }
        }

        return parent::getCreateTableSQL($table, $createFlags);
    }

    /**
     * @throws Exception
     * @return string[]
     */
    public function getCreateAutoincrementSql(string|AbstractAsset $column, string|AbstractAsset $tableName): array
    {
        $sql = [];

        if (! $column instanceof AbstractAsset) {
            $column = $this->normalizeIdentifier($column);
        }

        if (! $tableName instanceof AbstractAsset) {
            $tableName = $this->normalizeIdentifier($tableName);
        }

        $sequenceName = $this->getIdentitySequenceName($tableName->getName(), $column->getName());
        $triggerName  = $this->getIdentitySequenceTriggerName($tableName, $column);
        $sequence     = new Sequence($sequenceName, 1, 1);

        $sql[] = $this->getCreateSequenceSQL($sequence);

        $sql[] = 'CREATE TRIGGER ' . $triggerName . ' FOR ' . $tableName->getQuotedName($this) . '
            BEFORE INSERT
            AS
            BEGIN
                IF ((NEW.' . $column->getQuotedName($this) . ' IS NULL) OR
                   (NEW.' . $column->getQuotedName($this) . ' = 0)) THEN
                BEGIN
                    NEW.' . $column->getQuotedName($this) . ' = NEXT VALUE FOR ' . $sequence->getQuotedName($this) . ';
                END
            END;';

        return $sql;
    }

    public function getDropAutoincrementSql(string $table): string
    {
        $table                = $this->normalizeIdentifier($table);
        $identitySequenceName = $this->getIdentitySequenceName(
            $table->isQuoted() ? $table->getQuotedName($this) : $table->getName(),
            '',
        );

        return $this->getDropSequenceSQL($identitySequenceName);
    }

    /**
     * {@inheritDoc}
     */
    public function getListSequencesSQL($database)
    {
        return 'select trim(rdb$generator_name) as rdb$generator_name, trim(RDB$DESCRIPTION) as comment from rdb$generators where rdb$system_flag is distinct from 1';
    }

    /**
     * {@inheritDoc}
     *
     * Returns a query resulting cointaining the following data:
     *
     * FIELD_NAME: Field Name
     * FIELD_DOMAIN: Domain
     * FIELD_TYPE: Internal Id of the field type
     * FIELD_TYPE_NAME: Name of the field type
     * FIELD_SUB_TYPE: Internal Id of the field sub-type
     * FIELD_LENGTH: Length of the field in *byte*
     * FIELD_CHAR_LENGTH: Length of the field in *chracters*
     * FIELD_PRECISION: Precision
     * FIELD_SCALE: Scale
     * FIELD_DEFAULT_SOURCE: Default declaration including the DEFAULT keyword and quotes if any
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        $table = $this->normalizeIdentifier($table);
        $table = $this->quoteStringLiteral($table->getName());

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
TRIM(cl.RDB$COLLATION_NAME) as "COLLATION_NAME"
FROM RDB$RELATION_FIELDS r
LEFT OUTER JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
LEFT OUTER JOIN RDB$INDEX_SEGMENTS s ON s.RDB$FIELD_NAME=r.RDB$FIELD_NAME
LEFT OUTER JOIN RDB$INDICES i ON i.RDB$INDEX_NAME = s.RDB$INDEX_NAME 
                              AND i.RDB$RELATION_NAME = r.RDB$RELATION_NAME
LEFT OUTER JOIN RDB$RELATION_CONSTRAINTS rc ON rc.RDB$INDEX_NAME = s.RDB$INDEX_NAME 
                                            AND rc.RDB$INDEX_NAME = i.RDB$INDEX_NAME 
                                            AND rc.RDB$RELATION_NAME = i.RDB$RELATION_NAME
LEFT OUTER JOIN RDB$REF_CONSTRAINTS REFC ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
LEFT OUTER JOIN RDB$TYPES typ ON typ.RDB$FIELD_NAME = 'RDB$FIELD_TYPE' 
                              AND typ.RDB$TYPE = f.RDB$FIELD_TYPE
LEFT OUTER JOIN RDB$TYPES sub ON sub.RDB$FIELD_NAME = 'RDB$FIELD_SUB_TYPE' 
                              AND sub.RDB$TYPE = f.RDB$FIELD_SUB_TYPE
LEFT OUTER JOIN RDB$CHARACTER_SETS cs ON cs.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID
LEFT OUTER JOIN RDB$COLLATIONS cl ON cl.RDB$CHARACTER_SET_ID = f.RDB$CHARACTER_SET_ID 
                                  AND cl.RDB$COLLATION_ID = f.RDB$COLLATION_ID
WHERE UPPER(r.RDB$RELATION_NAME) = UPPER(:TABLE)
GROUP BY "FIELD_NAME", "FIELD_DOMAIN", "FIELD_TYPE", "FIELD_TYPE_NAME", "FIELD_SUB_TYPE",  "FIELD_LENGTH",
         "FIELD_CHAR_LENGTH", "FIELD_PRECISION", "FIELD_SCALE", "FIELD_NOT_NULL_FLAG", "FIELD_DEFAULT_SOURCE",
         "FIELD_POSITION","FIELD_DESCRIPTION", 
         "CHARACTER_SET_ID", "CHARACTER_SET_NAME", "COLLATION_ID", "COLLATION_NAME"
ORDER BY "FIELD_POSITION"
___query___;

        return str_replace(':TABLE', $table, $query);
    }

    /**
     * @inheritDoc
     */
    public function getListTableForeignKeysSQL($table): string
    {
        $table = $this->normalizeIdentifier($table);
        $table = $this->quoteStringLiteral($table->getName());

        $query = <<<'___query___'
      SELECT TRIM(rc.RDB$CONSTRAINT_NAME) AS constraint_name,
      TRIM(i.RDB$RELATION_NAME) AS table_name,
      TRIM(s.RDB$FIELD_NAME) AS field_name,
      TRIM(i.RDB$DESCRIPTION) AS description,
      TRIM(rc.RDB$DEFERRABLE) AS is_deferrable,
      TRIM(rc.RDB$INITIALLY_DEFERRED) AS is_deferred,
      TRIM(refc.RDB$UPDATE_RULE) AS on_update,
      TRIM(refc.RDB$DELETE_RULE) AS on_delete,
      TRIM(refc.RDB$MATCH_OPTION) AS match_type,
      TRIM(i2.RDB$RELATION_NAME) AS references_table,
      TRIM(s2.RDB$FIELD_NAME) AS references_field,
      (s.RDB$FIELD_POSITION + 1) AS field_position
      FROM RDB$INDEX_SEGMENTS s
      LEFT JOIN RDB$INDICES i ON i.RDB$INDEX_NAME = s.RDB$INDEX_NAME
      LEFT JOIN RDB$RELATION_CONSTRAINTS rc ON rc.RDB$INDEX_NAME = s.RDB$INDEX_NAME
      LEFT JOIN RDB$REF_CONSTRAINTS refc ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
      LEFT JOIN RDB$RELATION_CONSTRAINTS rc2 ON rc2.RDB$CONSTRAINT_NAME = refc.RDB$CONST_NAME_UQ
      LEFT JOIN RDB$INDICES i2 ON i2.RDB$INDEX_NAME = rc2.RDB$INDEX_NAME
      LEFT JOIN RDB$INDEX_SEGMENTS s2 ON i2.RDB$INDEX_NAME = s2.RDB$INDEX_NAME AND s.RDB$FIELD_POSITION = s2.RDB$FIELD_POSITION
      WHERE rc.RDB$CONSTRAINT_TYPE = 'FOREIGN KEY' and UPPER(i.RDB$RELATION_NAME) = UPPER(:TABLE)
      ORDER BY rc.RDB$CONSTRAINT_NAME, s.RDB$FIELD_POSITION
___query___;

        return str_replace(':TABLE', $table, $query);
    }

    /**
     * {@inheritDoc}
     *
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaOracleReader.html
     */
    public function getListTableIndexesSQL($table, $database = null)
    {
        $table = $this->normalizeIdentifier($table);
        $table = $this->quoteStringLiteral($table->getName());
        $query = <<<'___query___'
      SELECT
        TRIM(RDB$INDEX_SEGMENTS.RDB$FIELD_NAME) AS field_name,
        TRIM(RDB$INDICES.RDB$DESCRIPTION) AS description,
        TRIM(RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_NAME)  as constraint_name,
        TRIM(RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_TYPE) as constraint_type,
        TRIM(RDB$INDICES.RDB$INDEX_NAME) as index_name,
        RDB$INDICES.RDB$UNIQUE_FLAG as unique_flag,
        RDB$INDICES.RDB$INDEX_TYPE as index_type,
        (RDB$INDEX_SEGMENTS.RDB$FIELD_POSITION + 1) AS field_position,
        RDB$INDICES.RDB$INDEX_INACTIVE as index_inactive,
        TRIM(RDB$INDICES.RDB$FOREIGN_KEY) as foreign_key
     FROM RDB$INDEX_SEGMENTS
     LEFT JOIN RDB$INDICES ON RDB$INDICES.RDB$INDEX_NAME = RDB$INDEX_SEGMENTS.RDB$INDEX_NAME
     LEFT JOIN RDB$RELATION_CONSTRAINTS ON RDB$RELATION_CONSTRAINTS.RDB$INDEX_NAME = RDB$INDEX_SEGMENTS.RDB$INDEX_NAME
     WHERE UPPER(RDB$INDICES.RDB$RELATION_NAME) = UPPER(:TABLE)
     ORDER BY RDB$INDICES.RDB$INDEX_NAME, RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_NAME, RDB$INDEX_SEGMENTS.RDB$FIELD_POSITION
___query___;

        return (string) str_replace(':TABLE', $table, $query);
    }


    public function getCurrentDatabaseExpression(): string
    {
        return 'rdb$get_context(\'SYSTEM\', \'DB_NAME\')';
    }

    /** @inheritDoc */
    public function getRenameTableSQL(string $oldName, string $newName): array
    {
        throw Exception::notSupported(__METHOD__ . ' Cannot rename tables because firebird does not support it');
        // return parent::getRenameTableSQL($oldName, $newName);
    }

    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new FirebirdSchemaManager($connection, $this);
    }

    public function getLengthExpression($column)
    {
        $max = $this->getVarcharMaxCastLength();

        return $column === '?' ? 'CHAR_LENGTH(CAST(? AS VARCHAR(' . $max . ')))' : 'CHAR_LENGTH(' . $column . ')';
    }

    /** @inheritDoc */
    public function supportsColumnCollation()
    {
        return true;
    }

    /** @inheritDoc */
    public function getNowExpression()
    {
        return 'CURRENT_TIMESTAMP';
    }

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new FirebirdSelectSQLBuilder($this, 'WITH LOCK', null);
    }

    public function getListTableConstraintsSQL($table)
    {
        $table = $this->normalizeIdentifier($table);
        $table = $this->quoteStringLiteral($table->getName());

        return sprintf(
            <<<'SQL'
SELECT
    cc.RDB$CONSTRAINT_NAME AS constraint_name,
    rc.RDB$CONSTRAINT_TYPE AS constraint_type,
    cc.RDB$TRIGGER_SOURCE AS check_condition
FROM
    RDB$CHECK_CONSTRAINTS cc
JOIN
    RDB$RELATION_CONSTRAINTS rc ON cc.RDB$CONSTRAINT_NAME = rc.RDB$CONSTRAINT_NAME
WHERE
    rc.RDB$RELATION_NAME = %s
SQL
            ,
            $table,
        );
    }

    public function getGuidTypeDeclarationSQL(array $column)
    {
        $column['length'] = 36;
        $column['fixed']  = false;

        return $this->getStringTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function isCommentedDoctrineType(Type $doctrineType)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5058',
            '%s() is deprecated and will be removed in Doctrine DBAL 4.0. Use Type::requiresSQLCommentHint() instead.',
            __METHOD__,
        );

        if ($doctrineType->getName() === Types::BOOLEAN) {
            // We require a commented boolean type in order to distinguish between boolean and smallint
            // as both (have to) map to the same native type.
            return true;
        }

        return parent::isCommentedDoctrineType($doctrineType);
    }

    /**
     * {@inheritDoc}
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $k => $value) {
                if (! is_bool($value)) {
                    continue;
                }

                $item[$k] = $this->getBooleanDatabaseValue($value);
            }
        } elseif (is_bool($item)) {
            $item = $this->getBooleanDatabaseValue($item);
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    public function convertFromBoolean($item)
    {
        // Handle both SMALLINT and CHAR representations
        if ($item === null) {
            return null;
        }

        if ($this->hasNativeBooleanType) {
            return (bool) $item;
        }

        if ($this->useSmallIntBoolean) {
            return (bool) $item; // SMALLINT (0, 1)
        }

        return $item === $this->charTrue;
    }

    /**
     * {@inheritDoc}
     */
    public function convertBooleansToDatabaseValue($item)
    {
        if ($this->hasNativeBooleanType) {
            return (bool) $item;
        }

        return $this->convertBooleans($item);
    }

    public function getBinaryDefaultLength()
    {
        return $this->getVarcharMaxCastLength();
    }

    /**
     * @throws Exception
     */
    public static function assertValidIdentifier(string $identifier): void
    {
        $pattern = '(^(([a-zA-Z]{1}[a-zA-Z0-9_$#]{0,})|("[^"]+"))$)';
        if (preg_match($pattern, $identifier) === 0) {
            throw new Exception('Invalid Firebird identifier %s provided');
        }
    }

    /**
     * Generates an internal ID based on the table name and a suffix
     *
     * @param string[]|string|AbstractAsset|AbstractAsset[] $prefix    Name, Identifier object or array of names or
     *                                                                 identifier objects to use as prefix.
     * @param int                                           $maxLength Length limit to check. Usually the result of
     *                                                                     {@link getMaxIdentifierLength()} should be passed
     */
    protected function generateIdentifier(array|string|AbstractAsset $prefix, string $suffix, int $maxLength): Identifier
    {
        $needQuote = false;
        $fullId    = '';
        $shortId   = '';
        $prefix    = is_array($prefix) ? $prefix : [$prefix];
        $ml        = (int) floor(($maxLength - strlen($suffix)) / count($prefix));
        foreach ($prefix as $p) {
            if (! $p instanceof AbstractAsset) {
                $p = $this->normalizeIdentifier($p);
            }

            $fullId .= $p->getName() . '_';
            if (strlen($p->getName()) >= $ml) {
                $c        = crc32($p->getName());
                $shortId .= substr_replace($p->getName(), sprintf('X%04x', $c & 0xFFFF), $ml - 6) . '_';
            } else {
                $shortId .= $p->getName() . '_';
            }

            if ($needQuote) {
                continue;
            }

            $needQuote = $p->isQuoted();
        }

        $fullId  .= $suffix;
        $shortId .= $suffix;
        if (strlen($fullId) > $maxLength) {
            return new Identifier($needQuote ? $this->quoteIdentifier($shortId) : $shortId);
        }

        return new Identifier($needQuote ? $this->quoteIdentifier($fullId) : $fullId);
    }

    /**
     * Quotes a SQL-Statement
     */
    protected function quoteSql(string $statement): string
    {
        return $this->quoteStringLiteral($statement);
    }

    /**
     * Returns a primary key constraint name for the table
     *
     * The format is tablename_PK. If the combined name exceeds the length limit, the table name gets shortened.
     *
     * @param Identifier|string $aTable Table name or identifier
     */
    protected function generatePrimaryKeyConstraintName(Identifier|string $aTable): string
    {
        return $this->generateIdentifier([$aTable], 'PK', $this->getMaxConstraintIdentifierLength())->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        if ($unit === DateIntervalUnit::QUARTER) {
            // Firebird does not support QUARTER - convert to month
            $interval = (int) $interval * 3;
            $unit     = DateIntervalUnit::MONTH;
        }

        if ($operator === '-') {
            $interval = (int) $interval * -1;
        }

        return 'DATEADD(' . $unit . ', ' . $interval . ', ' . $date . ')';
    }

    protected function getTemporaryColumnName(string $columnName): string
    {
        return $this->generateIdentifier('tmp', $columnName, $this->getMaxIdentifierLength())->getQuotedName($this);
    }

    /**
     * Adds a "Limit" using the firebird ROWS m TO n syntax
     *
     * @param int $limit  limit to numbers of records
     * @param int $offset starting point
     */
    protected function doModifyLimitQuery($query, $limit, $offset): string
    {
        if ((int) $limit === 0 && (int) $offset === 0) {
            return $query; // No limitation specified - change nothing
        }

        if ($offset === null) {
            // A limit is specified, but no offset, so the syntax ROWS <n> is used
            return $query . ' ROWS 1 TO ' . (int) $limit;
        }

        $from = (int) $offset + 1; // Firebird starts the offset at 1
        if ($limit === null) {
            $to = PHP_INT_MAX; // should be beyond a reasonable  number of rows
        } else {
            $to = $from + $limit - 1;
        }

        return $query . ' ROWS ' . $from . ' TO ' . $to;
    }

    /**
     * Generates simple sql expressions usually used in metadata-queries
     *
     * @param array $expressions
     */
    protected function makeSimpleMetadataSelectExpression(array $expressions): string
    {
        $result = '(';
        $i      = 0;
        foreach ($expressions as $f => $v) {
            if ($i > 0) {
                $result .= ' AND ';
            }

            if (
                ($v instanceof AbstractAsset) ||
                    (is_string($v))
            ) {
                $result .= 'UPPER(' . $f . ') = UPPER(\'' . $this->unquotedIdentifierName($v) . '\')';
            } else {
                if ($v === null) {
                    $result .= $f . ' IS NULL';
                } else {
                    $result .= $f . ' = ' . $v;
                }
            }

            $i++;
        }

        return $result . ')';
    }

    /**
     * Combines multiple statements into an execute block statement
     *
     * @param array $params
     * @return string
     */
    protected function getExecuteBlockSql(array $params = []): string
    {
        $params = array_merge(
            [
                'blockParams' => [],
                'blockVars' => [],
                'statements' => [],
                'formatLineBreak' => true,
            ],
            $params,
        );

        if ($params['formatLineBreak']) {
            $break  = "\n";
            $indent = '  ';
        } else {
            $break  = ' ';
            $indent = '';
        }

        $result = 'EXECUTE BLOCK ';
        if (isset($params['blockParams']) && is_array($params['blockParams']) && count($params['blockParams']) > 0) {
            $result .= '(';
            $n       = 0;
            foreach ($params['blockParams'] as $paramName => $paramDelcaration) {
                if ($n > 0) {
                    $result .= ', ';
                }

                $result .= $paramName . ' ' . $paramDelcaration;
                $n++;
            }

            $result .= ') ' . $break;
        }

        $result .= 'AS' . $break;
        if (is_array($params['blockVars'])) {
            foreach ($params['blockVars'] as $variableName => $variableDeclaration) {
                $result .= $indent . 'DECLARE ' . $variableName . ' ' . $variableDeclaration . '; ' . $break;
            }
        }

        $result .= 'BEGIN' . $break;
        foreach ((array) $params['statements'] as $stm) {
            $result .= $indent . $stm . $break;
        }

        $result .= 'END' . $break;

        return $result;
    }

    /**
     * Builds an Execute Block statement with a bunch of Execute Statement calls
     *
     * @param array $params
     * @return string
     */
    protected function getExecuteBlockWithExecuteStatementsSql(array $params = []): string
    {
        $params     = array_merge(
            [
                'blockParams' => [],
                'blockVars' => [],
                'statements' => [],
                'formatLineBreak' => true,
            ],
            $params,
        );
        $statements = [];
        foreach ((array) $params['statements'] as $s) {
            $statements[] = $this->getExecuteStatementPSql($s) . ';';
        }

        $params['statements'] = $statements;

        return $this->getExecuteBlockSql($params);
    }

    /**
     * Generates a execute statement PSQL-Statement
     */
    protected function getExecuteStatementPSql(string $aStatement): string
    {
        return 'EXECUTE STATEMENT ' . $this->quoteSql($aStatement);
    }

    /**
     * {@inheritDoc}
     */
    protected function getPlainDropSequenceSQL($sequence)
    {
        return $this->getDropSequenceSQL($sequence);
    }

    /**
     * Returns a simple DROP TRIGGER statement
     */
    protected function getDropTriggerSql(string $aTrigger): string
    {
        return 'DROP TRIGGER ' . $this->getQuotedNameOf($aTrigger);
    }

    protected function getDropTriggerIfExistsPSql(string $aTrigger, bool $inBlock = false): string
    {
        $result = sprintf(
            'IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE %s)) THEN BEGIN %s; END',
            $this->makeSimpleMetadataSelectExpression([
                'RDB$TRIGGER_NAME' => $aTrigger,
                'RDB$SYSTEM_FLAG' => 0,
            ]),
            $this->getExecuteStatementPSql($this->getDropTriggerSql($aTrigger)),
        );
        if ($inBlock) {
            return $this->getExecuteBlockSql([
                'statements' => $result,
                'formatLineBreak' => false,
            ]);
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function getDropSequenceIfExistsPSql($aSequence, $inBlock = false)
    {
        $result = sprintf(
            'IF (EXISTS(SELECT 1 FROM RDB$GENERATORS 
                              WHERE (UPPER(TRIM(RDB$GENERATOR_NAME)) = UPPER(\'%s\') 
                                AND (RDB$SYSTEM_FLAG IS DISTINCT FROM 1))
                              )) THEN BEGIN %s; END',
            $this->unquotedIdentifierName($aSequence),
            $this->getExecuteStatementPSql(parent::getDropSequenceSQL($aSequence)),
        );
        if ($inBlock) {
            return $this->getExecuteBlockSql([
                'statements' => $result,
                'formatLineBreak' => false,
            ]);
        }

        return $result;
    }


    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'boolean'       => TYPES::SMALLINT,
            'blob'          => Types::BLOB,
            'binary'        =>  Types::BLOB,
            'blob sub_type text' => Types::TEXT,
            'blob sub_type binary' => Types::BLOB,
            'cstring'       => Types::STRING,
            'char'          => Types::STRING,
            'double'        => Types::FLOAT,
            'decimal'       => Types::DECIMAL,
            'date'          => Types::DATE_MUTABLE,
            'float'         => Types::FLOAT,
            'int'           => Types::INTEGER,
            'integer'       => Types::INTEGER,
            'int64'         => Types::BIGINT,
            'long'          => Types::INTEGER, // YES, really. Not bigint.
            'longvarchar'   => Types::STRING,
            'mediumint'     => Types::INTEGER,
            'numeric'       => Types::DECIMAL,
            'smallint'      => TYPES::SMALLINT,
            'serial'        => Types::INTEGER,
            'tinyint'       => TYPES::SMALLINT,
            'text'          => Types::STRING, // Yes, really. 'char' is internally called text.
            'time'          => Types::TIME_MUTABLE,
            'real'          => Types::FLOAT,
            'short'         => TYPES::SMALLINT,
            'timestamp'     => Types::DATETIME_MUTABLE,
            'varchar'       => Types::STRING,
            'varying'       => Types::STRING,
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return FirebirdKeywords::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column)
    {
        return '';
    }/**























      * {@inheritDoc}
      */

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed): string
    {
        if ($fixed) {
            if ($length > 0) {
                return 'CHAR(' . $length . ')';
            }

            return 'CHAR(255)';
        }

        if ($length > 0) {
            return 'VARCHAR(' . $length . ')';
        }

        return 'VARCHAR(255)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        if ($length > $this->getBinaryMaxLength()) {
            return 'BLOB';
        }

        if ($fixed) {
            if ($length > 0) {
                return 'CHAR(' . $length . ')';
            }

            return 'CHAR(' . $this->getBinaryMaxLength() . ')';
        }

        return 'VARCHAR(' . ($length > 0 ? $length : $this->getBinaryMaxLength()) . ')';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($name, array $columns, array $options = [])
    {
        $this->checkIdentifierLength($name, $this->getMaxIdentifierLength());

        $isTemporary = (! empty($options['temporary']));

        $indexes            = $options['indexes'] ?? [];
        $options['indexes'] = [];

        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $constraintName => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($constraintName, $definition);
            }
        }

        if (! empty($options['primary'])) {
            $columnListSql .= ', CONSTRAINT ' . $this->generatePrimaryKeyConstraintName($name) . ' PRIMARY KEY (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        if (! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
                $columnListSql .= ', ' . $this->getIndexDeclarationSQL($index, $definition);
            }
        }

        $query = 'CREATE ' .
                ($isTemporary ? $this->getTemporaryTableSQL() . ' ' : '') .
                'TABLE ' . $name;

        $query .= ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (! empty($check)) {
            $query .= ', ' . $check;
        }

        $query .= ')';

        if ($isTemporary) {
            $query .= ' ON COMMIT PRESERVE ROWS';   // Session level temporary tables
        }

        $sql   = [];
        $sql[] = $query;

        // Create sequences and a trigger for autoinc-fields if necessary

        foreach ($columns as $columnName => $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence']);
            }

            if (
                ! (isset($column['autoincrement']) && $column['autoincrement'] ||
                    (isset($column['autoinc']) && $column['autoinc']))
            ) {
                continue;
            }

            $sql = array_merge($sql, $this->getCreateAutoincrementSql($columnName, $name));
        }

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $name);
            }
        }

        foreach ($indexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $name);
        }

        return $sql;
    }


    protected function unquotedIdentifierName(string|AbstractAsset $name): string
    {
        if (!($name instanceof AbstractAsset)) {
            $name = new Identifier($name);
        }

        return $name->getName();
    }

    /**
     * Returns a quoted name if necessary
     */
    protected function getQuotedNameOf(string|Identifier $name): string
    {
        if ($name instanceof AbstractAsset) {
            return $name->getQuotedName($this);
        }

        $id = new Identifier($name);

        return $id->getQuotedName($this);
    }

    /**
     * Normalize the identifier
     *
     * Firebird converts identifiers to uppercase if not quoted. This function converts the identifier to uppercase
     * if it is *not* quoted *and* does not not contain any Uppercase characters. Otherwise the function
     * quotes the identifier.
     *
     * @param string $name Identifier
     *
     * @return Identifier The normalized identifier.
     */
    protected function normalizeIdentifier(string $name): Identifier
    {
        $identifier = new Identifier($name);

        return $identifier->isQuoted() ? $identifier : new Identifier(strtoupper($name));
    }

    protected function getOldColumnComment(ColumnDiff $columnDiff): string|null
    {
        $oldColumn = $columnDiff->getOldColumn();

        if ($oldColumn !== null) {
            return $this->getColumnComment($oldColumn);
        }

        return null;
    }

    protected function getVarcharMaxCastLength(): int
    {
        return 255;
    }

    private function getBooleanDatabaseValue(mixed $value): bool|int|string
    {
        if ($this->hasNativeBooleanType) {
            return (bool) $value;
        }

        return $this->useSmallIntBoolean ? (int) $value : ( $value ? $this->charTrue : $this->charFalse);
    }
}
