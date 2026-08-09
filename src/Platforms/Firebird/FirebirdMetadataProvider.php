<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms\Firebird;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Metadata\PrimaryKeyConstraintColumnRow;
use Doctrine\DBAL\Schema\Metadata\SequenceMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ViewMetadataRow;
use Doctrine\DBAL\Types\Exception\TypesException;
use JsonException;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

use function assert;
use function json_decode;
use function max;
use function preg_match;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Provides low-level metadata for the Firebird platform via the DBAL 4 introspection API.
 *
 * Firebird has no schemas and no in-band database enumeration. System tables are filtered via
 * RDB$SYSTEM_FLAG. All schema-aware methods reject non-null $schemaName (Firebird has no schemas).
 */
final readonly class FirebirdMetadataProvider implements MetadataProvider
{
    /** @internal This class can be instantiated only by a database platform. */
    public function __construct(private Connection $connection, private FirebirdPlatform $platform)
    {
    }

    /** {@inheritDoc} */
    public function getAllDatabaseNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /** {@inheritDoc} */
    public function getAllSchemaNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /** {@inheritDoc} */
    public function getAllTableNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT TRIM(RDB$RELATION_NAME) AS TABLE_NAME
        FROM RDB$RELATIONS
        WHERE (RDB$SYSTEM_FLAG = 0 OR RDB$SYSTEM_FLAG IS NULL)
          AND RDB$VIEW_BLR IS NULL
        ORDER BY RDB$RELATION_NAME
SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new TableMetadataRow(null, $row[0], []);
        }
    }

    /** {@inheritDoc} */
    public function getTableColumnsForAllTables(): iterable
    {
        return $this->getTableColumns(null);
    }

    /** {@inheritDoc} */
    public function getTableColumnsForTable(string|null $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getTableColumns($tableName);
    }

    /** {@inheritDoc} */
    public function getIndexColumnsForAllTables(): iterable
    {
        return $this->getIndexColumns(null);
    }

    /** {@inheritDoc} */
    public function getIndexColumnsForTable(string|null $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getIndexColumns($tableName);
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getPrimaryKeyConstraintColumns(null);
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForTable(
        string|null $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getPrimaryKeyConstraintColumns($tableName);
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getForeignKeyConstraintColumns(null);
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForTable(
        string|null $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getForeignKeyConstraintColumns($tableName);
    }

    /** {@inheritDoc} */
    public function getTableOptionsForAllTables(): iterable
    {
        return $this->getTableOptions(null);
    }

    /** {@inheritDoc} */
    public function getTableOptionsForTable(string|null $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getTableOptions($tableName);
    }

    /** {@inheritDoc} */
    public function getAllViews(): iterable
    {
        $sql = <<<'SQL'
        SELECT TRIM(RDB$RELATION_NAME) AS VIEW_NAME,
               TRIM(RDB$VIEW_SOURCE) AS VIEW_SOURCE
        FROM RDB$RELATIONS
        WHERE (RDB$SYSTEM_FLAG = 0 OR RDB$SYSTEM_FLAG IS NULL)
          AND RDB$RELATION_TYPE = 1
        ORDER BY RDB$RELATION_NAME
SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new ViewMetadataRow(null, $row[0], $row[1] ?? '');
        }
    }

    /** {@inheritDoc} */
    public function getAllSequences(): iterable
    {
        $sql = <<<'SQL'
        SELECT TRIM(rdb$generator_name) AS sequence_name,
               TRIM(RDB$DESCRIPTION) AS comment
        FROM rdb$generators
        WHERE rdb$system_flag is distinct from 1
        ORDER BY rdb$generator_name
SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            $config = $this->parseSequenceConfig((string) $row[1]);

            $cacheSize = $config['cache'] ?? null;

            yield new SequenceMetadataRow(
                schemaName: null,
                sequenceName: $row[0],
                allocationSize: $config['allocationSize'] ?? 1,
                initialValue: $config['initialValue'] ?? 1,
                cacheSize: $cacheSize !== null ? max(0, (int) $cacheSize) : null,
            );
        }
    }

    /**
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     * @throws TypesException
     */
    private function getTableColumns(string|null $tableName): iterable
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
            $joinClause  = "INNER JOIN RDB\$RELATIONS rel ON rel.RDB\$RELATION_NAME = r.RDB\$RELATION_NAME\n"
                         . "     AND (rel.RDB\$SYSTEM_FLAG = 0 OR rel.RDB\$SYSTEM_FLAG IS NULL)\n"
                         . '     AND rel.RDB$VIEW_BLR IS NULL';
            $whereClause = '1=1';
            $params      = [];
        }

        $sql = <<<SQL
SELECT
TRIM(r.RDB\$RELATION_NAME) AS "RDB\$RELATION_NAME",
TRIM(r.RDB\$FIELD_NAME) AS "FIELD_NAME",
TRIM(f.RDB\$FIELD_TYPE) AS "FIELD_TYPE",
TRIM(typ.RDB\$TYPE_NAME) AS "FIELD_TYPE_NAME",
f.RDB\$FIELD_SUB_TYPE AS "FIELD_SUB_TYPE",
f.RDB\$CHARACTER_LENGTH AS "FIELD_CHAR_LENGTH",
f.RDB\$FIELD_PRECISION AS "FIELD_PRECISION",
f.RDB\$FIELD_SCALE AS "FIELD_SCALE",
r.RDB\$FIELD_POSITION AS "FIELD_POSITION",
r.RDB\$NULL_FLAG AS "FIELD_NOT_NULL_FLAG",
r.RDB\$DEFAULT_SOURCE AS "FIELD_DEFAULT_SOURCE",
r.RDB\$DESCRIPTION AS "FIELD_DESCRIPTION",
TRIM(cs.RDB\$CHARACTER_SET_NAME) AS "CHARACTER_SET_NAME"{$identitySelect}
FROM RDB\$RELATION_FIELDS r
{$joinClause}
LEFT OUTER JOIN RDB\$FIELDS f ON r.RDB\$FIELD_SOURCE = f.RDB\$FIELD_NAME
LEFT OUTER JOIN RDB\$TYPES typ ON typ.RDB\$FIELD_NAME = 'RDB\$FIELD_TYPE'
                              AND typ.RDB\$TYPE = f.RDB\$FIELD_TYPE
LEFT OUTER JOIN RDB\$CHARACTER_SETS cs ON cs.RDB\$CHARACTER_SET_ID = f.RDB\$CHARACTER_SET_ID
WHERE {$whereClause}
GROUP BY "RDB\$RELATION_NAME", "FIELD_NAME", "FIELD_TYPE", "FIELD_TYPE_NAME",
         "FIELD_SUB_TYPE", "FIELD_CHAR_LENGTH", "FIELD_PRECISION",          "FIELD_SCALE", "FIELD_POSITION",
         "FIELD_NOT_NULL_FLAG", "FIELD_DEFAULT_SOURCE", "FIELD_DESCRIPTION",
         "CHARACTER_SET_NAME"{$identityGroupBy}
ORDER BY "RDB\$RELATION_NAME", "FIELD_POSITION"
SQL;

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield $this->createTableColumn($row);
        }
    }

    /**
     * @param list<mixed> $row
     *
     * @throws TypesException
     */
    private function createTableColumn(array $row): TableColumnMetadataRow
    {
        [
            $tableName,
            $columnName,
            $fieldType,
            $fieldTypeName,
            $fieldSubType,
            $charLength,
            $precision,
            $fieldScale,
            $notNullFlag,
            $defaultSource,
            $description,
            $characterSetName,
        ] = $row;

        // Identity column only exists on Firebird 3.0+ (appended via $identitySelect)
        $identityType = $row[12] ?? null;

        $dbType = strtolower((string) $fieldTypeName);
        $scale  = $fieldScale !== null ? $fieldScale * -1 : null;
        $type   = $this->platform->getDoctrineTypeMapping($dbType);
        $fixed  = false;

        switch ((int) $fieldType) {
            case 14: // META_FIELD_TYPE_CHAR
                $fixed = true;
                break;
            case 7: // SMALLINT
            case 8: // INTEGER
            case 16: // BIGINT
            case 27: // DOUBLE
            case 10: // FLOAT
                if ((int) $fieldSubType > 0) {
                    $type = 'decimal';
                }

                $charLength = null;
                break;
            case 261: // BLOB
                if ((int) $fieldSubType === 1) {
                    $type = 'text';
                }
        }

        if ($characterSetName === 'OCTETS') {
            $type = 'binary';
        }

        $columnName = (string) $columnName;
        $tableName  = (string) $tableName;
        assert($columnName !== '' && $tableName !== '');

        $editor = Column::editor()
            ->setQuotedName($columnName)
            ->setTypeName($type);

        if ($charLength !== null) {
            $editor->setLength((int) $charLength);
        }

        if ($scale !== null && $precision !== null && (int) $precision !== 0) {
            $editor->setPrecision((int) $precision);
            $editor->setScale((int) $scale);
        } elseif ($type === 'decimal') {
            $editor->setPrecision(10);
            if ($scale !== null) {
                $editor->setScale((int) $scale);
            }
        }

        $editor
            ->setNotNull((bool) $notNullFlag)
            ->setDefaultValue($this->parseDefaultExpression($defaultSource))
            ->setAutoincrement($identityType !== null)
            ->setFixed($fixed);

        if ($description !== null && $description !== '') {
            $editor->setComment($description);
        }

        return new TableColumnMetadataRow(null, $tableName, $editor->create());
    }

    /**
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getIndexColumns(string|null $tableName): iterable
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
    TRIM(RDB\$INDICES.RDB\$RELATION_NAME) AS table_name,
    TRIM(RDB\$INDEX_SEGMENTS.RDB\$FIELD_NAME) AS column_name,
    TRIM(RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_NAME) AS constraint_name,
    TRIM(RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_TYPE) AS constraint_type,
    TRIM(RDB\$INDICES.RDB\$INDEX_NAME) AS index_name,
    RDB\$INDICES.RDB\$UNIQUE_FLAG AS unique_flag,
    TRIM(RDB\$INDICES.RDB\$FOREIGN_KEY) AS foreign_key
FROM RDB\$INDEX_SEGMENTS
LEFT JOIN RDB\$INDICES ON RDB\$INDICES.RDB\$INDEX_NAME = RDB\$INDEX_SEGMENTS.RDB\$INDEX_NAME
LEFT JOIN RDB\$RELATION_CONSTRAINTS ON RDB\$RELATION_CONSTRAINTS.RDB\$INDEX_NAME = RDB\$INDEX_SEGMENTS.RDB\$INDEX_NAME
WHERE {$whereClause}
ORDER BY RDB\$INDICES.RDB\$RELATION_NAME, RDB\$INDICES.RDB\$INDEX_NAME,
         RDB\$INDEX_SEGMENTS.RDB\$FIELD_POSITION
SQL;

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            // Skip PK-backed and FK-backed indexes — they have dedicated providers.
            if ($row[3] === 'PRIMARY KEY' || $row[6] !== null) {
                continue;
            }

            yield new IndexColumnMetadataRow(
                schemaName: null,
                tableName: $row[0],
                indexName: $row[4],
                type: (bool) $row[5] ? IndexType::UNIQUE : IndexType::REGULAR,
                isClustered: false,
                predicate: null,
                columnName: $row[1],
                columnLength: null,
            );
        }
    }

    /**
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function getPrimaryKeyConstraintColumns(string|null $tableName): iterable
    {
        if ($tableName !== null) {
            $whereClause = "RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_TYPE = 'PRIMARY KEY'\n"
                . '  AND UPPER(RDB$INDICES.RDB$RELATION_NAME) = UPPER(?)';
            $params      = [$tableName];
        } else {
            $whereClause = "RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_TYPE = 'PRIMARY KEY'\n"
                . "  AND RDB\$INDICES.RDB\$RELATION_NAME IN (\n"
                . "    SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS\n"
                . "    WHERE (RDB\$SYSTEM_FLAG = 0 OR RDB\$SYSTEM_FLAG IS NULL) AND RDB\$VIEW_BLR IS NULL\n"
                . '  )';
            $params      = [];
        }

        $sql = <<<SQL
SELECT
    TRIM(RDB\$INDICES.RDB\$RELATION_NAME) AS table_name,
    TRIM(RDB\$INDEX_SEGMENTS.RDB\$FIELD_NAME) AS column_name,
    TRIM(RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_NAME) AS constraint_name
FROM RDB\$INDEX_SEGMENTS
LEFT JOIN RDB\$INDICES ON RDB\$INDICES.RDB\$INDEX_NAME = RDB\$INDEX_SEGMENTS.RDB\$INDEX_NAME
LEFT JOIN RDB\$RELATION_CONSTRAINTS ON RDB\$RELATION_CONSTRAINTS.RDB\$INDEX_NAME = RDB\$INDEX_SEGMENTS.RDB\$INDEX_NAME
WHERE {$whereClause}
ORDER BY RDB\$INDICES.RDB\$RELATION_NAME, RDB\$RELATION_CONSTRAINTS.RDB\$CONSTRAINT_NAME,
         RDB\$INDEX_SEGMENTS.RDB\$FIELD_POSITION
SQL;

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new PrimaryKeyConstraintColumnRow(
                schemaName: null,
                tableName: $row[0],
                constraintName: $row[2],
                isClustered: true,
                columnName: $row[1],
            );
        }
    }

    /**
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintColumns(string|null $tableName): iterable
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
    TRIM(i.RDB\$RELATION_NAME) AS table_name,
    TRIM(rc.RDB\$CONSTRAINT_NAME) AS constraint_name,
    TRIM(s.RDB\$FIELD_NAME) AS column_name,
    TRIM(rc.RDB\$DEFERRABLE) AS is_deferrable,
    TRIM(rc.RDB\$INITIALLY_DEFERRED) AS is_deferred,
    TRIM(refc.RDB\$UPDATE_RULE) AS on_update,
    TRIM(refc.RDB\$DELETE_RULE) AS on_delete,
    TRIM(refc.RDB\$MATCH_OPTION) AS match_type,
    TRIM(i2.RDB\$RELATION_NAME) AS references_table,
    TRIM(s2.RDB\$FIELD_NAME) AS references_column
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

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new ForeignKeyConstraintColumnMetadataRow(
                referencingSchemaName: null,
                referencingTableName: $row[0],
                id: null,
                name: $row[1],
                referencedSchemaName: null,
                referencedTableName: $row[8],
                matchType: $this->createMatchType((string) $row[7]),
                onUpdateAction: $this->createReferentialAction((string) $row[5]),
                onDeleteAction: $this->createReferentialAction((string) $row[6]),
                isDeferrable: $row[3] === 'YES',
                isDeferred: $row[4] === 'DEFERRED',
                referencingColumnName: $row[2],
                referencedColumnName: $row[9],
            );
        }
    }

    /**
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function getTableOptions(string|null $tableName): iterable
    {
        $params = [];
        $where  = 'RDB$RELATION_TYPE = 0';

        if ($tableName !== null) {
            $where .= ' AND UPPER(TRIM(RDB$RELATION_NAME)) = UPPER(?)';
            $params = [$tableName];
        }

        $sql = <<<SQL
        SELECT TRIM(RDB\$RELATION_NAME) AS TABLE_NAME,
               RDB\$DESCRIPTION AS COMMENT
        FROM RDB\$RELATIONS
        WHERE {$where}
        ORDER BY RDB\$RELATION_NAME
SQL;

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new TableMetadataRow(null, $row[0], [
                'comment' => $row[1],
            ]);
        }
    }

    /**
     * Parses JSON-encoded sequence configuration from RDB$DESCRIPTION.
     *
     * The driver's getCreateSequenceSQL() encodes allocationSize, initialValue, and cache as JSON
     * in the generator description for round-trip fidelity.
     *
     * @return array{allocationSize?: int, initialValue?: int, cache?: int|null}
     */
    private function parseSequenceConfig(string $comment): array
    {
        if ($comment === '') {
            return [];
        }

        try {
            $decoded = json_decode($comment, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return $decoded ?? [];
    }

    /**
     * Parses a Firebird default value expression from RDB$DEFAULT_SOURCE.
     *
     * Firebird stores defaults as "DEFAULT <expression>". This method strips the DEFAULT keyword
     * and normalizes the value: NULL → null, single-quoted literals → unescaped string,
     * numeric/expression → raw string.
     */
    private function parseDefaultExpression(string|null $expression): mixed
    {
        if ($expression === null) {
            return null;
        }

        $expression = trim($expression);

        // Strip the "DEFAULT" keyword prefix
        if (preg_match('/^DEFAULT\s+(.*)$/i', $expression, $matches) === 1) {
            $expression = trim($matches[1]);
        }

        if ($expression === '' || strtoupper($expression) === 'NULL') {
            return null;
        }

        // Unwrap single-quoted string literals (with '' → ' unescaping)
        if (preg_match("/^'(.*)'$/s", $expression, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }

        return $expression;
    }

    private function createReferentialAction(string $value): ReferentialAction
    {
        // Firebird stores: CASCADE, NO ACTION, SET DEFAULT, SET NULL, RESTRICT.
        // Fallback to NO_ACTION for unexpected/empty values (e.g. from LEFT JOIN misses).
        return ReferentialAction::tryFrom($value) ?? ReferentialAction::NO_ACTION;
    }

    private function createMatchType(string $value): MatchType
    {
        // Firebird stores: SIMPLE, FULL, PARTIAL (FB 4.0+), or possibly empty
        $matchType = MatchType::tryFrom($value);

        return $matchType ?? MatchType::SIMPLE;
    }
}
