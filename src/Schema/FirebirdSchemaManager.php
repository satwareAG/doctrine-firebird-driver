<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Schema;

use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\View;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\FirebirdConnectString;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird4Platform;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird5Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

use function array_change_key_case;
use function array_merge;
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
use const IBASE_CREATE;

/**
 * Firebird Schema Manager.
 *
 * @extends AbstractSchemaManager<FirebirdPlatform|Firebird3Platform|Firebird4Platform|Firebird5Platform>
 */
class FirebirdSchemaManager extends AbstractSchemaManager
{
    public const META_FIELD_TYPE_SMALLINT  = 7; // Integer Type
    public const META_FIELD_TYPE_INTEGER   = 8; // Integer Type
    public const META_FIELD_TYPE_FLOAT     = 10;
    public const META_FIELD_TYPE_DATE      = 12;
    public const META_FIELD_TYPE_TIME      = 13;
    public const META_FIELD_TYPE_CHAR      = 14;
    public const META_FIELD_TYPE_BIGINT    = 16; // 64 Bit Integer
    public const META_FIELD_TYPE_DOUBLE    = 27;
    public const META_FIELD_TYPE_TIMESTAMP = 35;
    public const META_FIELD_TYPE_VARCHAR   = 37;
    public const META_FIELD_TYPE_CSTRING   = 40; // XXX Does not exist in Firebird 2.5
    public const META_FIELD_TYPE_BLOB      = 261;

    public Connection $conn;
    /** @var FirebirdPlatform */
    public AbstractPlatform $platform;

    /**
     * @throws Exception
     *
     * @psalm-suppress PossiblyUndefinedArrayOffset
     * @inheritDoc
     */
    public function dropDatabase($database): void
    {
        $params           = $this->conn->getParams();
        $params['dbname'] = $database;

        $dbname =  (string) FirebirdConnectString::fromConnectionParameters($params);

        $connection = @fbird_connect($dbname, $params['user'], $params['password']);
        if (! is_resource($connection)) {
            $code = (int) @fbird_errcode();
            $msg  = (string) @fbird_errmsg();
            if ($code === -902) {
                throw new DatabaseDoesNotExist(new Exception($msg, null, $code), null);
            }

            throw new Exception($msg, null, $code);
        }

        $this->conn->close();
        $result = @fbird_drop_db(
            $connection,
        );
        if (! $result) {
            throw new Exception((string) @fbird_errmsg(), null, (int) @fbird_errcode());
        }

        @fbird_close($connection);
        unset($connection);
    }

    /**
     * {@inheritDoc}
     */
    public function dropTable($name): void
    {
        parent::dropTable($name);
    }

    /**
     * {@inheritDoc}
     */
    public function createDatabase($database): void
    {
        $params           = $this->conn->getParams();
        $params['dbname'] = $database;
        $charset          = $params['charset'] ?? 'UTF8';
        $user             = $params['user'] ?? '';
        $password         = $params['password'] ?? '';
        $pageSize         = $params['driverOptions']['page_size'] ?? '16384';
        $dbname           = (string) FirebirdConnectString::fromConnectionParameters($params);

        /** @psalm-suppress InvalidArgument */
        $result = @fbird_query(
            IBASE_CREATE,
            sprintf(
                "CREATE DATABASE '%s' PAGE_SIZE = %s USER '%s' PASSWORD '%s' DEFAULT CHARACTER SET %s",
                $dbname,
                (int) $pageSize,
                $user,
                $password,
                $charset,
            ),
        );

        if (! is_resource($result)) {
            $code = (int) @fbird_errcode();
            $msg  = (string) @fbird_errmsg();

            throw new Exception($msg, null, $code);
        }

        @fbird_close($result);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@see introspectTable()} instead.
     */
    public function listTableDetails($name)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5595',
            '%s is deprecated. Use introspectTable() instead.',
            __METHOD__,
        );

        return $this->doListTableDetails($name);
    }

    /**
     * @return array<int, string>
     *
     * @psalm-suppress
     */
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

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableDefinition($table): string
    {
        $table = array_change_key_case($table, CASE_LOWER);

        return $this->getQuotedIdentifierName(trim((string) $table['rdb$relation_name']));
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition($view): View
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
    protected function _getPortableDatabaseDefinition($database): string
    {
        return $database['Database'];
    }

    /** {@inheritDoc} */
    protected function _getPortableTableColumnDefinition($tableColumn): Column
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
                if ($tableColumn['FIELD_SUB_TYPE'] === 1) {
                    $type = 'text';
                }
        }

        // Detect binary field by checking the characterset
        if ($tableColumn['CHARACTER_SET_NAME'] === 'OCTETS') {
            $type = 'binary';
        }

        // Override detected type if a type hint is specified

        $type = $this->extractDoctrineTypeFromComment($tableColumn['FIELD_DESCRIPTION'], $type);

        if ($tableColumn['FIELD_DESCRIPTION'] !== null) {
            $options['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['FIELD_DESCRIPTION'], $type);
            if ($options['comment'] === '') {
                $options['comment'] = null;
            }
        }

        if (preg_match('/^.*default\s*\'(.*)\'\s*$/i', (string) $tableColumn['FIELD_DEFAULT_SOURCE'], $matches) === 1) {
            // default definition is a string
            $options['default'] = $matches[1];
        } elseif (preg_match('/^.*DEFAULT\s*(.*)\s*/i', (string) $tableColumn['FIELD_DEFAULT_SOURCE'], $matches) === 1) {
            // Default is numeric or a constant or a function
            $options['default'] = $matches[1];
            if (strtoupper(trim($options['default'])) === 'NULL') {
                $options['default'] = null;
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
                'scale' => null,
                'precision' => null,
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
    protected function _getPortableTableForeignKeysList($tableForeignKeys): array
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
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null): array
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
    protected function fetchTableOptionsByTable(string $databaseName, string|null $tableName = null): array
    {
        $sql = <<<'___query___'
SELECT TRIM(RDB$RELATION_NAME) AS TABLE_NAME, RDB$DESCRIPTION AS COMMENT
FROM RDB$RELATIONS
WHERE RDB$RELATION_TYPE = 0 -- 0 indicates a table, 1 indicates a view

___query___;

        if ($tableName !== null) {
            $sql .= "AND UPPER(RDB\$RELATION_NAME) = UPPER('" . $tableName . "')";
        }

        /** @var array<string,array<string,mixed>> $metadata */
        $metadata = $this->conn->executeQuery($sql)
            ->fetchAllAssociativeIndexed();

        $tableOptions = [];
        foreach ($metadata as $table => $data) {
            $data = array_change_key_case($data, CASE_LOWER);

            $tableOptions[$table] = [
                'comment' => $data['comment'],
            ];
        }

        return $tableOptions;
    }

    protected function normalizeName(string $name): string
    {
        $identifier = new Identifier($name);

        return $identifier->isQuoted() ? $identifier->getName() : strtoupper($name);
    }

    protected function selectTableNames(string $databaseName): Result
    {
        // TODO: Implement selectTableNames() method.
    }

    protected function selectTableColumns(string $databaseName, string|null $tableName = null): Result
    {
        // TODO: Implement selectTableColumns() method.
    }

    protected function selectIndexColumns(string $databaseName, string|null $tableName = null): Result
    {
        // TODO: Implement selectIndexColumns() method.
    }

    protected function selectForeignKeyColumns(string $databaseName, string|null $tableName = null): Result
    {
        // TODO: Implement selectForeignKeyColumns() method.
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        // TODO: Implement _getPortableTableForeignKeyDefinition() method.
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
