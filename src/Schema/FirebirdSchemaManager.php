<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Schema;

use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\Type;
use Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\FirebirdConnectString;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Throwable;

use function array_change_key_case;
use function array_merge;
use function dirname;
use function fbird_close;
use function fbird_connect;
use function fbird_drop_db;
use function fbird_errcode;
use function fbird_errmsg;
use function fbird_query;
use function is_resource;
use function json_decode;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;
use function strtoupper;
use function trim;

use const CASE_LOWER;
use const CASE_UPPER;
use const FBIRD_CREATE;

/**
 * Firebird Schema Manager.
 *
 * @extends AbstractSchemaManager<FirebirdPlatform|Firebird3Platform|Firebird4Platform|Firebird5Platform>
 */
final class FirebirdSchemaManager extends AbstractSchemaManager
{
    public const META_FIELD_TYPE_SMALLINT = 7;

    public const META_FIELD_TYPE_INTEGER = 8;

    public const META_FIELD_TYPE_FLOAT = 10;

    public const META_FIELD_TYPE_DATE = 12;

    public const META_FIELD_TYPE_TIME = 13;

    public const META_FIELD_TYPE_CHAR = 14;

    public const META_FIELD_TYPE_BIGINT = 16;

    public const META_FIELD_TYPE_DOUBLE = 27;

    public const META_FIELD_TYPE_TIMESTAMP = 35;

    public const META_FIELD_TYPE_VARCHAR = 37;

    public const META_FIELD_TYPE_CSTRING = 40;

    public const META_FIELD_TYPE_BLOB = 261;

    /**
     * @throws Exception
     *
     * @psalm-suppress PossiblyUndefinedArrayOffset
     * @inheritDoc
     */
    #[Override]
    public function dropDatabase(string $database): void
    {
        $params           = $this->connection->getParams();
        $params['dbname'] = $database;

        $dbname =  (string) FirebirdConnectString::fromConnectionParameters($params);

        // Suppress warning since we handle the error explicitly below
        try {
            $connection = @fbird_connect($dbname, $params['user'] ?? '', $params['password'] ?? '');
        } catch (Throwable $e) {
            throw Exception::fromThrowable($e);
        }

        if (! is_resource($connection)) {
            $code = (int) fbird_errcode();
            $msg  = (string) fbird_errmsg();
            if ($code === -902) {
                throw new DatabaseDoesNotExist(new Exception($msg, null, $code), null);
            }

            throw new Exception($msg, null, $code);
        }

        $this->connection->close();
        try {
            $result = fbird_drop_db(
                $connection,
            );
        } catch (Throwable $e) {
            throw Exception::fromThrowable($e);
        }

        if (! $result) {
            throw new Exception((string) fbird_errmsg(), null, (int) fbird_errcode());
        }

        fbird_close($connection);
    }

    #[Override]
    public function createDatabase(string $database): void
    {
        $params  = $this->connection->getParams();
        $charset = $params['charset'] ?? 'UTF8';
        $user    = $params['user'] ?? '';

        // If database name is not an absolute path, resolve it relative to the
        // original database directory to ensure it can be created by Firebird.
        $originalDbname = $params['dbname'] ?? '';
        if ($database !== '' && $database[0] !== '/' && ! str_contains($database, ':')) {
            // Extract directory from original dbname
            $dir = dirname($originalDbname);
            if ($dir !== '' && $dir !== '.') {
                $database = $dir . '/' . $database;
            }
        }

        $params['dbname'] = $database;
        $password         = $params['password'] ?? '';
        $pageSize         = $params['driverOptions']['page_size'] ?? '16384';
        $dbname           = (string) FirebirdConnectString::fromConnectionParameters($params);

        /** @psalm-suppress InvalidArgument */
        try {
            $result = fbird_query(
                FBIRD_CREATE, // @phpstan-ignore-line argument.type
                sprintf(
                    "CREATE DATABASE '%s' PAGE_SIZE = %s USER '%s' PASSWORD '%s' DEFAULT CHARACTER SET %s",
                    $dbname,
                    (int) $pageSize,
                    $user,
                    $password,
                    $charset,
                ),
            );
        } catch (Throwable $e) {
            throw Exception::fromThrowable($e);
        }

        if (! is_resource($result)) {
            $code = (int) fbird_errcode();
            $msg  = (string) fbird_errmsg();

            throw new Exception($msg, null, $code);
        }

        fbird_close($result);
    }

    #[Override]
    public function createComparator(): Comparator
    {
        return new FirebirdComparator($this->platform);
    }

    /**
     * Returns table details including columns, indexes, and foreign keys.
     *
     * @deprecated This method is not part of the DBAL 4 AbstractSchemaManager interface.
     *             Use listTableColumns(), listTableIndexes(), and listTableForeignKeys() separately.
     */
    public function listTableDetails(string $name): Table
    {
        $database       = $this->connection->getDatabase() ?? '';
        $normalizedName = $this->normalizeName($name);
        $tableOptions   = $this->fetchTableOptionsByTable($database, $normalizedName);

        $columns     = $this->listTableColumns($name);
        $foreignKeys = $this->listTableForeignKeys($name);
        $indexes     = $this->listTableIndexes($name);

        $table = new Table($name, $columns, $indexes, [], $foreignKeys);

        if (isset($tableOptions[$normalizedName]['comment'])) {
            $table->addOption('comment', $tableOptions[$normalizedName]['comment']);
            $table->setComment($tableOptions[$normalizedName]['comment']);
        }

        return $table;
    }

    public function tryMethod(string $method, mixed ...$arguments): mixed
    {
        try {
            return $this->$method(...$arguments);
        } catch (Throwable) {
            return null;
        }
    }

    public function extractDoctrineTypeFromComment(string $comment, string $currentType): string
    {
        if (preg_match('(\(DC2Type:([^)]+)\))', $comment, $match) === 1) {
            return $match[1];
        }

        return $currentType;
    }

    /** @return array<int, string> */
    public static function getFieldTypeIdToColumnTypeMap(): array
    {
        return [
            self::META_FIELD_TYPE_CHAR => 'string',
            self::META_FIELD_TYPE_VARCHAR => 'string',
            self::META_FIELD_TYPE_CSTRING => 'string',
            self::META_FIELD_TYPE_BLOB => 'blob',
            self::META_FIELD_TYPE_DATE => 'date',
            self::META_FIELD_TYPE_TIME => 'time',
            self::META_FIELD_TYPE_TIMESTAMP => 'timestamp',
            self::META_FIELD_TYPE_DOUBLE => 'double',
            self::META_FIELD_TYPE_FLOAT => 'float',
            self::META_FIELD_TYPE_BIGINT => 'bigint',
            self::META_FIELD_TYPE_SMALLINT => 'smallint',
            self::META_FIELD_TYPE_INTEGER => 'integer',
        ];
    }

    /* -------------------------------------------------------------------------
     * DBAL 4 abstract method implementations
     * ------------------------------------------------------------------------- */

    /**
     * Selects all user-defined table names from Firebird system tables (RDB$RELATIONS).
     */
    #[Override]
    protected function selectTableNames(string $databaseName): Result
    {
        $sql = <<<'SQL'
SELECT TRIM(RDB$RELATION_NAME) AS RDB$RELATION_NAME
FROM RDB$RELATIONS
WHERE (RDB$SYSTEM_FLAG = 0 OR RDB$SYSTEM_FLAG IS NULL)
  AND RDB$VIEW_BLR IS NULL
ORDER BY RDB$RELATION_NAME
SQL;

        return $this->connection->executeQuery($sql);
    }

    #[Override]
    protected function selectTableColumns(string $databaseName, string|null $tableName = null): Result
    {
        $identitySelect  = '';
        $identityGroupBy = '';
        if ($this->platform instanceof Firebird3Platform) {
            $identitySelect  = ",\nTRIM(r.RDB\$IDENTITY_TYPE) AS \"IDENTITY_TYPE\"";
            $identityGroupBy = ', "IDENTITY_TYPE"';
        }

        if ($tableName !== null) {
            $whereClause = 'UPPER(r.RDB$RELATION_NAME) = UPPER(?)';
            $params      = [$tableName];
            $joinClause  = '';
        } else {
            $joinClause  = "INNER JOIN RDB\$RELATIONS rel ON rel.RDB\$RELATION_NAME = r.RDB\$RELATION_NAME\n     AND (rel.RDB\$SYSTEM_FLAG = 0 OR rel.RDB\$SYSTEM_FLAG IS NULL)\n     AND rel.RDB\$VIEW_BLR IS NULL";
            $whereClause = '1=1';
            $params      = [];
        }

        $sql = <<<SQL
SELECT
TRIM(r.RDB\$RELATION_NAME) AS "RDB\$RELATION_NAME",
TRIM(r.RDB\$FIELD_NAME) AS "FIELD_NAME",
TRIM(f.RDB\$FIELD_NAME) AS "FIELD_DOMAIN",
TRIM(f.RDB\$FIELD_TYPE) AS "FIELD_TYPE",
TRIM(typ.RDB\$TYPE_NAME) AS "FIELD_TYPE_NAME",
f.RDB\$FIELD_SUB_TYPE AS "FIELD_SUB_TYPE",
f.RDB\$FIELD_LENGTH AS "FIELD_LENGTH",
f.RDB\$CHARACTER_LENGTH AS "FIELD_CHAR_LENGTH",
f.RDB\$FIELD_PRECISION AS "FIELD_PRECISION",
f.RDB\$FIELD_SCALE AS "FIELD_SCALE",
MIN(TRIM(rc.RDB\$CONSTRAINT_TYPE)) AS "FIELD_CONSTRAINT_TYPE",
MIN(TRIM(i.RDB\$INDEX_NAME)) AS "FIELD_INDEX_NAME",
r.RDB\$NULL_FLAG AS "FIELD_NOT_NULL_FLAG",
r.RDB\$DEFAULT_SOURCE AS "FIELD_DEFAULT_SOURCE",
r.RDB\$FIELD_POSITION AS "FIELD_POSITION",
r.RDB\$DESCRIPTION AS "FIELD_DESCRIPTION",
f.RDB\$CHARACTER_SET_ID AS "CHARACTER_SET_ID",
TRIM(cs.RDB\$CHARACTER_SET_NAME) AS "CHARACTER_SET_NAME",
f.RDB\$COLLATION_ID AS "COLLATION_ID",
TRIM(cl.RDB\$COLLATION_NAME) AS "COLLATION_NAME"{$identitySelect}
FROM RDB\$RELATION_FIELDS r
{$joinClause}
LEFT OUTER JOIN RDB\$FIELDS f ON r.RDB\$FIELD_SOURCE = f.RDB\$FIELD_NAME
LEFT OUTER JOIN RDB\$INDEX_SEGMENTS s ON s.RDB\$FIELD_NAME = r.RDB\$FIELD_NAME
LEFT OUTER JOIN RDB\$INDICES i ON i.RDB\$INDEX_NAME = s.RDB\$INDEX_NAME
                              AND i.RDB\$RELATION_NAME = r.RDB\$RELATION_NAME
LEFT OUTER JOIN RDB\$RELATION_CONSTRAINTS rc ON rc.RDB\$INDEX_NAME = s.RDB\$INDEX_NAME
                                            AND rc.RDB\$INDEX_NAME = i.RDB\$INDEX_NAME
                                            AND rc.RDB\$RELATION_NAME = i.RDB\$RELATION_NAME
LEFT OUTER JOIN RDB\$REF_CONSTRAINTS REFC ON rc.RDB\$CONSTRAINT_NAME = refc.RDB\$CONSTRAINT_NAME
LEFT OUTER JOIN RDB\$TYPES typ ON typ.RDB\$FIELD_NAME = 'RDB\$FIELD_TYPE'
                              AND typ.RDB\$TYPE = f.RDB\$FIELD_TYPE
LEFT OUTER JOIN RDB\$TYPES sub ON sub.RDB\$FIELD_NAME = 'RDB\$FIELD_SUB_TYPE'
                              AND sub.RDB\$TYPE = f.RDB\$FIELD_SUB_TYPE
LEFT OUTER JOIN RDB\$CHARACTER_SETS cs ON cs.RDB\$CHARACTER_SET_ID = f.RDB\$CHARACTER_SET_ID
LEFT OUTER JOIN RDB\$COLLATIONS cl ON cl.RDB\$CHARACTER_SET_ID = f.RDB\$CHARACTER_SET_ID
                                  AND cl.RDB\$COLLATION_ID = f.RDB\$COLLATION_ID
WHERE {$whereClause}
GROUP BY "RDB\$RELATION_NAME", "FIELD_NAME", "FIELD_DOMAIN", "FIELD_TYPE", "FIELD_TYPE_NAME",
         "FIELD_SUB_TYPE", "FIELD_LENGTH", "FIELD_CHAR_LENGTH", "FIELD_PRECISION", "FIELD_SCALE",
         "FIELD_NOT_NULL_FLAG", "FIELD_DEFAULT_SOURCE", "FIELD_POSITION", "FIELD_DESCRIPTION",
         "CHARACTER_SET_ID", "CHARACTER_SET_NAME", "COLLATION_ID", "COLLATION_NAME"{$identityGroupBy}
ORDER BY "RDB\$RELATION_NAME", "FIELD_POSITION"
SQL;

        return $this->connection->executeQuery($sql, $params);
    }

    #[Override]
    protected function selectIndexColumns(string $databaseName, string|null $tableName = null): Result
    {
        if ($tableName !== null) {
            $whereClause = 'UPPER(RDB$INDICES.RDB$RELATION_NAME) = UPPER(?)';
            $params      = [$tableName];
        } else {
            $whereClause = "RDB\$INDICES.RDB\$RELATION_NAME IN (\n"
                . "    SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS\n"
                . "    WHERE (RDB\$SYSTEM_FLAG = 0 OR RDB\$SYSTEM_FLAG IS NULL) AND RDB\$VIEW_BLR IS NULL\n"
                . ')';
            $params      = [];
        }

        $sql = <<<SQL
SELECT
    TRIM(RDB\$INDICES.RDB\$RELATION_NAME) AS RDB\$RELATION_NAME,
    TRIM(RDB\$INDEX_SEGMENTS.RDB\$FIELD_NAME) AS field_name,
    TRIM(RDB\$INDICES.RDB\$DESCRIPTION) AS description,
    TRIM(RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_NAME) AS constraint_name,
    TRIM(RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_TYPE) AS constraint_type,
    TRIM(RDB\$INDICES.RDB\$INDEX_NAME) AS index_name,
    RDB\$INDICES.RDB\$UNIQUE_FLAG AS unique_flag,
    RDB\$INDICES.RDB\$INDEX_TYPE AS index_type,
    (RDB\$INDEX_SEGMENTS.RDB\$FIELD_POSITION + 1) AS field_position,
    RDB\$INDICES.RDB\$INDEX_INACTIVE AS index_inactive,
    TRIM(RDB\$INDICES.RDB\$FOREIGN_KEY) AS foreign_key
FROM RDB\$INDEX_SEGMENTS
LEFT JOIN RDB\$INDICES ON RDB\$INDICES.RDB\$INDEX_NAME = RDB\$INDEX_SEGMENTS.RDB\$INDEX_NAME
LEFT JOIN RDB\$RELATION_CONSTRAINTS ON RDB\$RELATION_CONSTRAINTS.RDB\$INDEX_NAME = RDB\$INDEX_SEGMENTS.RDB\$INDEX_NAME
WHERE {$whereClause}
ORDER BY RDB\$INDICES.RDB\$RELATION_NAME, RDB\$INDICES.RDB\$INDEX_NAME,
         RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_NAME, RDB\$INDEX_SEGMENTS.RDB\$FIELD_POSITION
SQL;

        return $this->connection->executeQuery($sql, $params);
    }

    #[Override]
    protected function selectForeignKeyColumns(string $databaseName, string|null $tableName = null): Result
    {
        if ($tableName !== null) {
            $whereClause = "rc.RDB\$CONSTRAINT_TYPE = 'FOREIGN KEY' AND UPPER(i.RDB\$RELATION_NAME) = UPPER(?)";
            $params      = [$tableName];
        } else {
            $whereClause = "rc.RDB\$CONSTRAINT_TYPE = 'FOREIGN KEY'\n"
                . "  AND i.RDB\$RELATION_NAME IN (\n"
                . "    SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS\n"
                . "    WHERE (RDB\$SYSTEM_FLAG = 0 OR RDB\$SYSTEM_FLAG IS NULL) AND RDB\$VIEW_BLR IS NULL\n"
                . '  )';
            $params      = [];
        }

        $sql = <<<SQL
SELECT
    TRIM(i.RDB\$RELATION_NAME) AS RDB\$RELATION_NAME,
    TRIM(rc.RDB\$CONSTRAINT_NAME) AS constraint_name,
    TRIM(i.RDB\$RELATION_NAME) AS table_name,
    TRIM(s.RDB\$FIELD_NAME) AS field_name,
    TRIM(i.RDB\$DESCRIPTION) AS description,
    TRIM(rc.RDB\$DEFERRABLE) AS is_deferrable,
    TRIM(rc.RDB\$INITIALLY_DEFERRED) AS is_deferred,
    TRIM(refc.RDB\$UPDATE_RULE) AS on_update,
    TRIM(refc.RDB\$DELETE_RULE) AS on_delete,
    TRIM(refc.RDB\$MATCH_OPTION) AS match_type,
    TRIM(i2.RDB\$RELATION_NAME) AS references_table,
    TRIM(s2.RDB\$FIELD_NAME) AS references_field,
    (s.RDB\$FIELD_POSITION + 1) AS field_position
FROM RDB\$INDEX_SEGMENTS s
LEFT JOIN RDB\$INDICES i ON i.RDB\$INDEX_NAME = s.RDB\$INDEX_NAME
LEFT JOIN RDB\$RELATION_CONSTRAINTS rc ON rc.RDB\$INDEX_NAME = s.RDB\$INDEX_NAME
LEFT JOIN RDB\$REF_CONSTRAINTS refc ON rc.RDB\$CONSTRAINT_NAME = refc.RDB\$CONSTRAINT_NAME
LEFT JOIN RDB\$RELATION_CONSTRAINTS rc2 ON rc2.RDB\$CONSTRAINT_NAME = refc.RDB\$CONST_NAME_UQ
LEFT JOIN RDB\$INDICES i2 ON i2.RDB\$INDEX_NAME = rc2.RDB\$INDEX_NAME
LEFT JOIN RDB\$INDEX_SEGMENTS s2 ON i2.RDB\$INDEX_NAME = s2.RDB\$INDEX_NAME
                                AND s.RDB\$FIELD_POSITION = s2.RDB\$FIELD_POSITION
WHERE {$whereClause}
ORDER BY rc.RDB\$CONSTRAINT_NAME, s.RDB\$FIELD_POSITION
SQL;

        return $this->connection->executeQuery($sql, $params);
    }

    #[Override]
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        // This method is called by the default _getPortableTableForeignKeysList implementation.
        // Our override of _getPortableTableForeignKeysList handles multi-row grouping directly,
        // so this method is only a fallback stub.
        return new ForeignKeyConstraint(
            $tableForeignKey['local'] ?? [],
            $tableForeignKey['foreignTable'] ?? $tableForeignKey['references_table'] ?? '',
            $tableForeignKey['foreign'] ?? [],
            $tableForeignKey['name'] ?? $tableForeignKey['constraint_name'] ?? null,
            [
                'onDelete' => $tableForeignKey['onDelete'] ?? $tableForeignKey['on_delete'] ?? null,
                'onUpdate' => $tableForeignKey['onUpdate'] ?? $tableForeignKey['on_update'] ?? null,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Portable definition methods
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function _getPortableTableDefinition(array $table): string
    {
        $table = array_change_key_case($table, CASE_LOWER);

        return $this->getQuotedIdentifierName(trim((string) $table['rdb$relation_name']));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function _getPortableViewDefinition(array $view): View
    {
        $view = array_change_key_case($view, CASE_LOWER);

        return new View(
            $this->getQuotedIdentifierName(trim((string) $view['rdb$relation_name'])),
            $this->getQuotedIdentifierName(trim((string) $view['rdb$view_source'])),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @todo Read current generator value
     */
    #[Override]
    protected function _getPortableSequenceDefinition($sequence): Sequence
    {
        $sequence = array_change_key_case($sequence, CASE_LOWER);
        $comment  = (string) $sequence['comment'];

        $sequenceConfiguration = json_decode($comment, true) ?? [];

        $allocationSize = $sequenceConfiguration['allocationSize'] ?? 1;
        $initialValue   = $sequenceConfiguration['initialValue'] ?? 1;
        $cache          = $sequenceConfiguration['cache'] ?? null;

        return new Sequence($this->getQuotedIdentifierName(trim(strtolower((string) $sequence['rdb$generator_name']))), $allocationSize, $initialValue, $cache);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        return $database['Database'];
    }

    /** {@inheritDoc} */
    #[Override]
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $options = [];

        $tableColumn = array_change_key_case($tableColumn, CASE_UPPER);

        $dbType = strtolower((string) $tableColumn['FIELD_TYPE_NAME']);

        $fixed = null;

        if (! isset($tableColumn['FIELD_NAME'])) {
            $tableColumn['FIELD_NAME'] = '';
        }

        $tableColumn['FIELD_NAME'] = strtolower((string) $tableColumn['FIELD_NAME']);

        $scale     = isset($tableColumn['FIELD_SCALE']) ? $tableColumn['FIELD_SCALE'] * -1 : null;
        $precision = $tableColumn['FIELD_PRECISION'];
        if ($tableColumn['FIELD_CHAR_LENGTH'] !== null) {
            $options['length'] = $tableColumn['FIELD_CHAR_LENGTH'];
        }

        $type = $this->platform->getDoctrineTypeMapping($dbType);

        switch ($tableColumn['FIELD_TYPE']) {
            case self::META_FIELD_TYPE_CHAR:
                $fixed = true;
                break;
            case self::META_FIELD_TYPE_SMALLINT:
            case self::META_FIELD_TYPE_INTEGER:
            case self::META_FIELD_TYPE_BIGINT:
            case self::META_FIELD_TYPE_DOUBLE:
            case self::META_FIELD_TYPE_FLOAT:
                // Firebirds reflection of the datatype is quite "creative": If a numeric or decimal field is defined,
                // the field-type reflects the internal datattype (e.g, and sub_type specifies, if decimal or numeric
                // has been used. Thus, we need to override the datatype if necessary.
                if ($tableColumn['FIELD_SUB_TYPE'] > 0) {
                    $type = 'decimal';
                }

                $options['length'] = null;
                break;
            case self::META_FIELD_TYPE_BLOB:
                switch ($tableColumn['FIELD_SUB_TYPE']) {
                    case 1:
                        $type = 'text';
                        break;
                }
        }

        // Detect binary field by checking the characterset
        if ($tableColumn['CHARACTER_SET_NAME'] === 'OCTETS') {
            $type = 'binary';
        }

        if (! empty($tableColumn['FIELD_DESCRIPTION'])) {
            $options['comment'] = $tableColumn['FIELD_DESCRIPTION'];
        }

        if (preg_match('/^.*default\s*\'(.*)\'\s*$/i', (string) $tableColumn['FIELD_DEFAULT_SOURCE'], $matches) === 1) {
            // default definition is a string
            $options['default'] = $matches[1];
        } else {
            if (preg_match('/^.*DEFAULT\s*(.*)\s*/i', (string) $tableColumn['FIELD_DEFAULT_SOURCE'], $matches) === 1) {
                // Default is numeric or a constant or a function
                $options['default'] = $matches[1];
                if (strtoupper(trim($options['default'])) === 'NULL') {
                    $options['default'] = null;
                }
            }
        }

        $options['notnull'] = (bool) $tableColumn['FIELD_NOT_NULL_FLAG'];
        // Only available for Firebird 3+
        $options['autoincrement'] = ($tableColumn['IDENTITY_TYPE'] ?? null) !== null;

        $options = array_merge(
            $options,
            [
                'unsigned' => str_contains($dbType, 'unsigned'),
                'fixed' => (bool) $fixed,
                'scale' => 0,
                'precision' => 0,
            ],
        );

        if ($scale !== null && $precision !== null) {
            $options['scale']     = $scale;
            $options['precision'] = $precision;
        }

        return new Column($tableColumn['FIELD_NAME'], Type::getType($type), $options);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $list = [];
        foreach ($tableForeignKeys as $value) {
            $value = array_change_key_case($value, CASE_LOWER);
            if (! isset($list[$value['constraint_name']])) {
                if (! isset($value['on_delete']) || $value['on_delete'] === 'RESTRICT') {
                    $value['on_delete'] = null;
                }

                if (! isset($value['on_update']) || $value['on_update'] === 'RESTRICT') {
                    $value['on_update'] = null;
                }

                $list[$value['constraint_name']] = [
                    'name' => $value['constraint_name'],
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $value['references_table'],
                    'onDelete' => $value['on_delete'],
                    'onUpdate' => $value['on_update'],
                ];
            }

            $list[$value['constraint_name']]['local'][]   = strtolower((string) $value['field_name']);
            $list[$value['constraint_name']]['foreign'][] = strtolower((string) $value['references_field']);
        }

        $result = [];
        foreach ($list as $constraint) {
            $result[] = new ForeignKeyConstraint(
                $constraint['local'],
                $constraint['foreignTable'],
                $constraint['foreign'],
                $constraint['name'],
                [
                    'onDelete' => $constraint['onDelete'],
                    'onUpdate' => $constraint['onUpdate'],
                ],
            );
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $tableIndexes
     */
    #[Override]
    protected function _getPortableTableIndexesList(array $tableIndexes, string $tableName): array
    {
        $mangledData = [];
        foreach ($tableIndexes as $tableIndex) {
            $tableIndex = array_change_key_case($tableIndex, CASE_LOWER);

            if (isset($tableIndex['foreign_key'])) {
                continue;
            }

            $mangledItem = $tableIndex;

            $mangledItem['key_name'] = isset($tableIndex['constraint_name']) && ($tableIndex['constraint_name'] !== '') ? $tableIndex['constraint_name'] : $tableIndex['index_name'];

            $mangledItem['non_unique'] = ! (bool) $tableIndex['unique_flag'];

            $mangledItem['primary'] = ($tableIndex['constraint_type'] === 'PRIMARY KEY');

            if ($tableIndex['index_type']) {
                $mangledItem['options']['descending'] = true;
            }

            $mangledItem['column_name'] = strtolower((string) $tableIndex['field_name']);

            $mangledData[] = $mangledItem;
        }

        return parent::_getPortableTableIndexesList($mangledData, $tableName);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function fetchTableOptionsByTable(string $databaseName, string|null $tableName = null): array
    {
        $sql = <<<'___query___'
SELECT TRIM(RDB$RELATION_NAME) AS TABLE_NAME, RDB$DESCRIPTION AS COMMENT
FROM RDB$RELATIONS
WHERE RDB$RELATION_TYPE = 0 -- 0 indicates a table, 1 indicates a view
___query___;

        if ($tableName !== null) {
            $identifier        = new Identifier($tableName);
            $tableNameForQuery = $identifier->getName();
            $sql              .= " AND (UPPER(RDB\$RELATION_NAME) = UPPER('" . $tableNameForQuery . "') OR TRIM(RDB\$RELATION_NAME) = '" . $tableNameForQuery . "')";
        }

        /** @var array<int,array<string,mixed>> $metadata */
        $metadata = $this->connection->executeQuery($sql)
            ->fetchAllAssociative();

        $tableOptions = [];
        foreach ($metadata as $data) {
            $data  = array_change_key_case($data, CASE_LOWER);
            $table = strtoupper(trim((string) $data['table_name']));

            $tableOptions[$table] = [
                'comment' => $data['comment'],
            ];
        }

        return $tableOptions;
    }

    #[Override]
    protected function normalizeName(string $name): string
    {
        $identifier = new Identifier($name);

        return $identifier->isQuoted() ? $identifier->getName() : strtoupper($name);
    }

    /**
     * Returns the quoted identifier if necessary
     *
     * Firebird converts all nonquoted identifiers to uppercase, thus
     * all lower or mixed case identifiers get quoted here
     *
     * @param string $identifier Identifier
     */
    private function getQuotedIdentifierName(string $identifier): string
    {
        if (preg_match('/[a-z]/', $identifier) === 1) {
            return $this->platform->quoteIdentifier($identifier);
        }

        return $identifier;
    }
}
