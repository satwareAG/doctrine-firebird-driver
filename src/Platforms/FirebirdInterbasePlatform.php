<?php
namespace Kafoso\DoctrineFirebirdDriver\Platforms;

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
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;
use Kafoso\DoctrineFirebirdDriver\Platforms\Keywords\FirebirdInterbaseKeywords;
use Kafoso\DoctrineFirebirdDriver\Schema\FirebirdInterbaseSchemaManager;

class FirebirdInterbasePlatform extends AbstractPlatform
{
    /**
     * Firebird 2.5 has no native Boolean Type
     */
    protected bool $hasNativeBooleanType = false;
    private string $charTrue = 'Y';

    private string $charFalse = 'N';

    /**
     * If false we use CHAR(1) field instead of SMALLINT for Boolean Type
     */
    private bool $useSmallIntBoolean = true;

    public function setCharTrue(string $char): FirebirdInterbasePlatform
    {
        $this->charTrue = $char;
        return $this;
    }

    public function setCharFalse(string $char): FirebirdInterbasePlatform
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
            'PostgreSQLPlatform::getName() is deprecated. Identify platforms by their class.',
        );
        return "FirebirdInterbase";
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxIdentifierLength(): int
    {
        return 31;
    }

    /**
     * Returns the max length of constraint names
     *
     * @return integer
     */
    public function getMaxConstraintIdentifierLength(): int
    {
        return 27;
    }

    /**
     * Checks if the identifier exceeds the platform limit
     *
     * @param Identifier|string   $aIdentifier    The identifier to check
     * @param integer                                   $maxLength      Length limit to check. Usually the result of
     *                                                                  {@link getMaxIdentifierLength()} should be passed
     * @throws Exception
     */
    public function checkIdentifierLength($aIdentifier, ?int $maxLength = null): void
    {
        $maxLength ?? $maxLength = $this->getMaxIdentifierLength();
        $name = ($aIdentifier instanceof AbstractAsset) ?
                $aIdentifier->getName() : $aIdentifier;

        if (strlen($name) > $maxLength) {
            throw Exception::notSupported
                    ('Identifier ' . $name . ' is too long for firebird platform. Maximum identifier length is ' . $maxLength);
        }
    }

    /**
     * Generates an internal ID based on the table name and a suffix
     * @param string[]|string|AbstractAsset|AbstractAsset[] $prefix     Name, Identifier object or array of names or
     *                                                                  identifier objects to use as prefix.
     * @param integer                                       $maxLength  Length limit to check. Usually the result of
     *                                                                  {@link getMaxIdentifierLength()} should be passed
     *
     * @return Identifier
     */
    protected function generateIdentifier($prefix, string $suffix, int $maxLength)
    {
        $needQuote = false;
        $fullId = '';
        $shortId = '';
        $prefix = is_array($prefix) ? $prefix : [$prefix];
        $ml = (int)floor(($maxLength - strlen($suffix)) / count($prefix));
        foreach ($prefix as $p) {
            if (!$p instanceof AbstractAsset)
                $p = new Identifier($p);
            $fullId .= $p->getName() . '_';
            if (strlen($p->getName()) >= $ml) {
                $c = crc32($p->getName());
                $shortId .= substr_replace($p->getName(), sprintf("X%04x", $c & 0xFFFF), $ml - 6) . '_';
            } else {
                $shortId .= $p->getName() . '_';
            }
            if (!$needQuote) {
                $needQuote = $p->isQuoted();
            }
        }
        $fullId .= $suffix;
        $shortId .= $suffix;
        if (strlen($fullId) > $maxLength) {
            return new Identifier($needQuote ? $this->quoteIdentifier($shortId) : $shortId);
        } else {
            return new Identifier($needQuote ? $this->quoteIdentifier($fullId) : $fullId);
        }
    }

    /**
     * Quotes a SQL-Statement
     *
     * @param string $statement
     * @return string
     */
    protected function quoteSql($statement)
    {
        return $this->quoteStringLiteral($statement);
    }

    /**
     * Returns a primary key constraint name for the table
     *
     * The format is tablename_PK. If the combined name exceeds the length limit, the table name gets shortened.
     *
     * @param Identifier|string   $aTable Table name or identifier
     *
     * @return string
     */
    protected function generatePrimaryKeyConstraintName($aTable)
    {
        return $this->generateIdentifier([$aTable], 'PK', $this->getMaxConstraintIdentifierLength())->getQuotedName($this);
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
        if ($startPos == false) {
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
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        if (DateIntervalUnit::QUARTER === $unit) {
            // Firebird does not support QUARTER - convert to month
            $interval = (int)$interval * 3;
            $unit = DateIntervalUnit::MONTH;
        }
        if ($operator == '-') {
            $interval = (int)$interval * -1;
        }
        return 'DATEADD(' . $unit . ', ' . $interval . ', ' . $date . ')';
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

    protected function getTemporaryColumnName($columnName)
    {
        return $this->generateIdentifier('tmp_',$columnName, $this->getMaxIdentifierLength())->getQuotedName($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentitySequenceName($tableName, $columnName)
    {
        return $this->generateIdentifier([$tableName], 'D2IS', $this->getMaxIdentifierLength())->getQuotedName($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentitySequenceTriggerName($tableName, $columnName)
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
    public function supportsSchemas()
    {
        return false;
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
     *
     * @return boolean
     */
    public function supportsLimitOffset()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function prefersIdentityColumns()
    {
        return false;
    }

    /**
     * Adds a "Limit" using the firebird ROWS m TO n syntax
     *
     * @param string $query
     * @param integer $limit limit to numbers of records
     * @param integer $offset starting point
     *
     * @return string
     */
    protected function doModifyLimitQuery($query, $limit, $offset)
    {
        if ((int)$limit === 0 && (int)$offset === 0)
            return $query; // No limitation specified - change nothing

        if ($offset === NULL) {
            // A limit is specified, but no offset, so the syntax ROWS <n> is used
            return $query . ' ROWS 1 TO ' . (int) $limit;
        }
        $from = (int) $offset + 1; // Firebird starts the offset at 1
        if ($limit === NULL) {
            $to = '9000000000000000000'; // should be beyond a reasonable  number of rows
        } else {
            $to = $from + $limit - 1;
        }
        return $query . ' ROWS ' . $from . ' TO ' . $to;
    }

    /**
     * @inheritDoc
     */
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
     * Generates simple sql expressions usually used in metadata-queries
     *
     * @param array $expressions
     * @return string
     */
    protected function makeSimpleMetadataSelectExpression(array $expressions)
    {
        $result = '(';
        $i = 0;
        foreach ($expressions as $f => $v) {
            if ($i > 0) {
                $result .= ' AND ';
            }
            if (($v instanceof AbstractAsset) ||
                    (is_string($v))) {
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
        $result .= ')';
        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * See: How to run a select without table? https://www.firebirdfaq.org/faq30/
     */
    public function getDummySelectSQL()
    {
        $expression = func_num_args() > 0 ? func_get_arg(0) : '1';
        return sprintf('SELECT %s FROM RDB$DATABASE', $expression);
    }

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    public function getDropDatabaseSQL($database)
    {
        throw Exception::notSupported(__METHOD__);
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
     * Combines multiple statements into an execute block statement
     *
     * @param array|string $sql
     * @return string
     */
    protected function getExecuteBlockSql(array $params = [])
    {
        $params = array_merge(
            [
                'blockParams' => [],
                'blockVars' => [],
                'statements' => [],
                'formatLineBreak' => true,
            ],
            $params
        );

        if ($params['formatLineBreak']) {
            $break = "\n";
            $indent = '  ';
        } else {
            $break = ' ';
            $indent = '';
        }
        $result = 'EXECUTE BLOCK ';
        if (isset($params['blockParams']) && is_array($params['blockParams']) && count($params['blockParams']) > 0) {
            $result .= '(';
            $n = 0;
            foreach ($params['blockParams'] as $paramName => $paramDelcaration) {
                if ($n > 0)
                    $result .= ', ';
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
        $result .= "BEGIN" . $break;
        foreach ((array) $params['statements'] as $stm) {
            $result .= $indent . $stm . $break;
        }
        $result .= "END" . $break;
        return $result;
    }

    /**
     * Builds an Execute Block statement with a bunch of Execute Statement calls
     *
     * @param array|string $sql Statement(s) to execute.
     * @param array $params
     * @param array $variableDeclarations
     * @return string
     */
    protected function getExecuteBlockWithExecuteStatementsSql(array $params = [])
    {
        $params = array_merge(
            [
                'blockParams' => [],
                'blockVars' => [],
                'statements' => [],
                'formatLineBreak' => true,
            ],
            $params
        );
        $statements = [];
        foreach ((array) $params['statements'] as $s) {
            $statements[] = $this->getExecuteStatementPSql($s) . ';';
        }
        $params['statements'] = $statements;
        return $this->getExecuteBlockSql($params);
    }

    /**
     * Generates a PSQL-Statement to drop all views of a table
     *
     * Note: This statement needs a variable TMP_VIEW_NAME VARCHAR(255) declared
     *
     * @param string $tableNameVarName Variable used in the stored procedure or block to identify the related table name
     * @return string
     */
    public function getDropAllViewsOfTablePSqlSnippet($table, $inBlock = false)
    {
        $result = 'FOR SELECT TRIM(v.RDB$VIEW_NAME) ' .
                'FROM RDB$VIEW_RELATIONS v, RDB$RELATIONS r ' .
                'WHERE ' .
                'TRIM(UPPER(v.RDB$RELATION_NAME)) = TRIM(UPPER(' . $this->quoteStringLiteral($this->unquotedIdentifierName($table)) . ')) AND ' .
                'v.RDB$RELATION_NAME = r.RDB$RELATION_NAME AND ' .
                '(r.RDB$SYSTEM_FLAG IS NULL or r.RDB$SYSTEM_FLAG = 0) AND ' .
                '(r.RDB$RELATION_TYPE = 0) INTO :TMP_VIEW_NAME DO BEGIN ' .
                'EXECUTE STATEMENT \'DROP VIEW "\'||:TMP_VIEW_NAME||\'"\'; END';

        if ($inBlock) {
            $result = $this->getExecuteBlockSql(
                [
                    'statements' => $result,
                    'formatLineBreak' => false,
                    'blockVars' => [
                        'TMP_VIEW_NAME' => 'varchar(255)',
                    ]
                ]
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
                'SET GENERATOR '  . $sequence->getQuotedName($this) . ' TO ' . $sequence->getInitialValue()
            ],
            'formatLineBreak' => true,
        ]);

    }


    /**
     * Returns the insert SQL for an empty insert statement.
     *
     * @param string $quotedTableName
     * @param string $quotedIdentifierColumnName
     *
     * @return string
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
     * Generates a execute statement PSQL-Statement
     *
     * @param string $aStatement
     * @return string
     */
    protected function getExecuteStatementPSql($aStatement)
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
     *
     * @param string $aTrigger
     * @return string
     */
    protected function getDropTriggerSql($aTrigger)
    {
        return 'DROP TRIGGER ' . $this->getQuotedNameOf($aTrigger);
    }

    protected function getDropTriggerIfExistsPSql($aTrigger, $inBlock = false)
    {
        $result = sprintf(
            'IF (EXISTS (SELECT 1 FROM RDB$TRIGGERS WHERE %s)) THEN BEGIN %s; END',
            $this->makeSimpleMetadataSelectExpression([
                'RDB$TRIGGER_NAME' => $aTrigger,
                'RDB$SYSTEM_FLAG' => 0,
            ]),
            $this->getExecuteStatementPSql($this->getDropTriggerSql($aTrigger))
        );
        if ($inBlock) {
            return $this->getExecuteBlockSql([
                'statements' => $result,
                'formatLineBreak' => false,
            ]);
        }
        return $result;
    }

    protected function getDropSequenceIfExistsPSql($aSequence, $inBlock = false)
    {
        $result = sprintf(
            'IF (EXISTS(SELECT 1 
                              FROM RDB$GENERATORS 
                              WHERE (UPPER(TRIM(RDB$GENERATOR_NAME)) = UPPER(\'' . $this->unquotedIdentifierName($aSequence) . '\') 
                                AND (RDB$SYSTEM_FLAG IS NULL OR RDB$SYSTEM_FLAG = 0) 
                                )
                              )) THEN BEGIN %s; END',
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

    protected function getCombinedSqlStatements($sql, $aSeparator)
    {
        if (is_array($sql)) {
            $result = '';
            foreach ($sql as $stm) {
                $result .= is_array($stm) ? $this->getCombinedSqlStatements($stm, $aSeparator) : $stm . $aSeparator;
            }
            return $result;
        }
        return $sql . $aSeparator;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if (!($sequence instanceof Sequence)) {
            $sequence = new Sequence($sequence);
        }
        $sequenceName = $this->getQuotedNameOf($sequence);
        if (stripos($sequenceName, '_D2IS')) {
            // Seems to be a autoinc-sequence. Try to drop trigger before
            $triggerName = str_replace('_D2IS', '_D2IT', $sequenceName);
            return $this->getExecuteBlockWithExecuteStatementsSql([
                'statements' => [
                    $this->getDropTriggerIfExistsPSql($triggerName, true),
                    $this->getDropSequenceIfExistsPsql($sequence, true),
                ],
                'formatLineBreak' => false,
            ]);
        }
        return $dropSequenceSql;
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
     *
     * @param string $sequenceName
     * @return string
     */
    public function getSequenceNextValFunctionSQL($sequenceName)
    {
        return 'NEXT VALUE FOR ' . $sequenceName;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $sequenceName
     * @return string
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        return 'SELECT ' . $this->getSequenceNextValFunctionSQL($sequenceName) . ' FROM RDB$DATABASE';
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
        return parent::getSetTransactionIsolationSQL($level);
    }

    /**
     * {@inheritDoc}
     */
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
     *
     * @param string $table
     */
    public function getDropTableSQL($table)
    {
        $dropTriggerIfExistsPSql = $this->getDropTriggerIfExistsPSql($this->getIdentitySequenceTriggerName($table, null), true);
        $dropRelatedViewsPSql = $this->getDropAllViewsOfTablePSqlSnippet($table, true);
        $dropAutoincrementSql = $this->getDropAutoincrementSql($table);
        $dropTableSql =
        $this->getExecuteBlockWithExecuteStatementsSql([
            'statements' => [
                 parent::getDropTableSQL($table)
            ],
            'formatLineBreak' => false,
        ]);
        return $this->getExecuteBlockWithExecuteStatementsSql([
            'statements' => [
                $dropRelatedViewsPSql,
                $dropAutoincrementSql,
                $dropTableSql,
            ]
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'DELETE FROM ' . $tableName;
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
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
                $newNullFlag = $newColumn->getNotnull() ? 1 : 'NULL';
                $sql[] = 'UPDATE RDB$RELATION_FIELDS SET RDB$NULL_FLAG = ' .
                        $newNullFlag . ' ' .
                        'WHERE UPPER(RDB$FIELD_NAME) = ' .
                        'UPPER(\'' . $columnDiff->getOldColumnName()->getName() . '\') AND ' .
                        'UPPER(RDB$RELATION_NAME) = UPPER(\'' . $diff->getName($this)->getName() . '\')';
            }

            if ($columnDiff->hasAutoIncrementChanged()) {
                if ($newColumn->getAutoincrement()) {
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

    /**
     * {@inheritDoc}
     *
     * Actually Firebird can store up to 32K bytes in a varchar, but we assume UTF8, thus the limit is 8190
     */
    public function getVarcharMaxLength()
    {
        return 8190;
    }

    /**
     * {@inheritDoc}
     *
     * Varchars character set binary are used for small blob/binary fields.
     */
    public function getBinaryMaxLength()
    {
        return 8190;
    }

    /**
     * {@inheritDoc}
     */
    protected function getReservedKeywordsClass()
    {
        return FirebirdInterbaseKeywords::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $column)
    {
        return 'SMALLINT';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */

    /**
     * If it fits into a varchar, a varchar is used.
     *
     * @param array $field
     *
     * @return string
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        if (isset($field['length']) && is_numeric($field['length']) &&
                $field['length'] <= $this->getVarcharMaxLength()) {
            return 'VARCHAR(' . $field['length'] . ')';
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

    /**
     * @inheritDoc
     */
    public function getDateTypeDeclarationSQL(array $column)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        if ($fixed) {
            if ($length) {
                return 'CHAR(' . $length . ')';
            }
            return 'CHAR(255)';
        }
        if ($length) {
            return 'VARCHAR(' . $length . ')';
        }
        return 'VARCHAR(255)';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $charset   name of the charset
     *
     * @return string  DBMS specific SQL code portion needed to set the CHARACTER SET
     *                 of a field declaration.
     */
    public function getColumnCharsetDeclarationSQL($charset)
    {
        if ($charset !== '') {
            return ' CHARACTER SET ' . $charset;
        }
        return '';
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
            if ($length) {
                return 'CHAR(' . $length . ')';
            }
            return 'CHAR(' . $this->getBinaryDefaultLength() . ')';
        }
        if ($length) {
            return 'VARCHAR(' . $length . ')';
        }
        return 'VARCHAR(' . $this->getBinaryDefaultLength() . ')';
    }

    /**
     * {@inheritDoc}
     * @param string $name
     * @param array $column
     */
    public function getColumnDeclarationSQL($name, array $column)
    {
        if (isset($column['type']) && $column['type']->getName() === Types::BINARY) {
            $column['charset'] = 'octets';
            // $column['collation'] = 'octets';
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
        if (!$this->hasNativeBooleanType) {
            foreach ($table->getColumns() as $column) {
                if($column->getType()->getName() === Types::BOOLEAN) {
                    $column->setComment(($column->getComment() ?? '') . $this->getDoctrineTypeComment(Type::getType(Types::BOOLEAN)));
                    if (!$this->useSmallIntBoolean) {
                        $column->setType(Type::getType(Types::STRING));
                        $column->setLength(1);
                    }
                }
            }
        }
        return parent::getCreateTableSQL($table, $createFlags);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($name, array $columns, array $options = [])
    {
        $this->checkIdentifierLength($name, $this->getMaxIdentifierLength());

        $isTemporary = (isset($options['temporary']) && !empty($options['temporary']));

        $indexes = $options['indexes'] ?? [];
        $options['indexes'] = [];


        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (!empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $constraintName => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($constraintName, $definition);
            }
        }

        if (isset($options['primary']) && !empty($options['primary'])) {
            $columnListSql .= ', CONSTRAINT ' . $this->generatePrimaryKeyConstraintName($name) . ' PRIMARY KEY (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
                $columnListSql .= ', ' . $this->getIndexDeclarationSQL($index, $definition);
            }
        }

        $query = 'CREATE ' .
                ($isTemporary ? $this->getTemporaryTableSQL() . ' ' : '') .
                'TABLE ' . $name;

        $query .= ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (!empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        if ($isTemporary) {
            $query .= ' ON COMMIT PRESERVE ROWS';   // Session level temporary tables
        }

        $sql = [];
        $sql[] = $query;

        // Create sequences and a trigger for autoinc-fields if necessary

        foreach ($columns as $columnName => $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence']);
            }
            if (isset($column['autoincrement']) && $column['autoincrement'] ||
                    (isset($column['autoinc']) && $column['autoinc'])) {
                $sql = array_merge($sql, $this->getCreateAutoincrementSql($columnName, $name));
            }
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

    public function getCreateAutoincrementSql($column, $tableName)
    {
        $sql = [];

        if (!$column instanceof AbstractAsset)
            $column = new Identifier($column);

        $tableName = new Identifier($tableName);
        $sequenceName = $this->getIdentitySequenceName($tableName, $column);
        $triggerName = $this->getIdentitySequenceTriggerName($tableName, $column);
        $sequence = new Sequence($sequenceName, 1, 1);


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

    /**
     * Returns the SQL statements to drop the autoincrement for the given table name.
     *
     * @param string $table The table name to drop the autoincrement for.
     *
     * @return string
     */
    public function getDropAutoincrementSql($table)
    {
        $table = $this->normalizeIdentifier($table);
        $autoincrementIdentifierName = $this->getAutoincrementIdentifierName($table);
        $identitySequenceName = $this->getIdentitySequenceName(
            $table->isQuoted() ? $table->getQuotedName($this) : $table->getName(),
            ''
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
     *
     */
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
            TRIM(cl.RDB$COLLATION_NAME) as "COLLATION_NAME"
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
                     "FIELD_DESCRIPTION"
            ORDER BY "FIELD_POSITION"
___query___;
        return str_replace(':TABLE', $this->unquotedIdentifierName($table), $query);
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
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
      WHERE rc.RDB$CONSTRAINT_TYPE = 'FOREIGN KEY' and UPPER(i.RDB$RELATION_NAME) = UPPER(':TABLE')
      ORDER BY rc.RDB$CONSTRAINT_NAME, s.RDB$FIELD_POSITION
___query___;

        return str_replace(':TABLE', $this->unquotedIdentifierName($table), $query);
    }

    /**
     * {@inheritDoc}
     *
     * @license New BSD License
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaOracleReader.html
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
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
     WHERE UPPER(RDB$INDICES.RDB$RELATION_NAME) = UPPER(':TABLE')
     ORDER BY RDB$INDICES.RDB$INDEX_NAME, RDB$RELATION_CONSTRAINTS.RDB$CONSTRAINT_NAME, RDB$INDEX_SEGMENTS.RDB$FIELD_POSITION
___query___;
        return str_replace(':TABLE', $this->unquotedIdentifierName($table), $query);
    }

    /**
     * {@inheritDoc}
     *
     * Firebird return column names in upper case by default
     */
    public function getSQLResultCasing($column)
    {
        return strtoupper((string) $column);
    }

    protected function unquotedIdentifierName($name)
    {
        $name instanceof AbstractAsset || $name = new Identifier($name);
        return $name->getName();
    }

    /**
     * Returns a quoted name if necessary
     *
     * @param string|Identifier $name
     * @return string
     */
    protected function getQuotedNameOf($name)
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
    protected function normalizeIdentifier($name)
    {
        if ($name instanceof AbstractAsset) {
            $identifier = new Identifier($name->getQuotedName($this));
        } else {
            $identifier = new Identifier($name);
        }


        return $identifier->isQuoted() ? $identifier : new Identifier(strtoupper($identifier->getName()));
    }

    private function getAutoincrementIdentifierName(Identifier $table)
    {
        $identifierName = $table->getName() . '_AI_PK';
        return $table->isQuoted()
            ? $this->quoteSingleIdentifier($identifierName)
            : $identifierName;
    }

    public function getCurrentDatabaseExpression(): string
    {
        return 'rdb$get_context(\'SYSTEM\', \'DB_NAME\')';
    }

    /**
     * @inheritDoc
     */
    public function getRenameTableSQL(string $oldName, string $newName): array
    {
        throw Exception::notSupported(__METHOD__ . ' Cannot rename tables because firebird does not support it');
        // return parent::getRenameTableSQL($oldName, $newName);
    }

    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new FirebirdInterbaseSchemaManager($connection, $this);
    }

    protected function getOldColumnComment(ColumnDiff $columnDiff): ?string
    {
        $oldColumn = $columnDiff->getOldColumn();

        if ($oldColumn !== null) {
            return $this->getColumnComment($oldColumn);
        }

        return null;
    }


    public function getLengthExpression($column)
    {
        $max = $this->getVarcharMaxLength();
        return $column === '?' ? 'CHAR_LENGTH(CAST(? AS VARCHAR('.$max.')))' : 'CHAR_LENGTH(' . $column . ')';
    }

    /**
     * @inheritDoc
     */
    public function supportsColumnCollation()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getNowExpression()
    {
        return 'CURRENT_TIMESTAMP';
    }

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new DefaultSelectSQLBuilder($this, 'FOR UPDATE', null);
    }

    public function getListTableConstraintsSQL($table)
    {
        $table = new Identifier($table);
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
                if (!is_bool($value)) {
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
     * @param $value
     * @return int|string
     */
    private function getBooleanDatabaseValue($value)
    {
        if ($this->hasNativeBooleanType) {
            return (bool) $value;
        }
        return $this->useSmallIntBoolean ? (int) $value : ( $value ? $this->charTrue : $this->charFalse);
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
}
