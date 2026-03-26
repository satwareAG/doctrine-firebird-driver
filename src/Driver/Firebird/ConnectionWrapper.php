<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Exception as SchemaException;
use Doctrine\DBAL\Statement;
use InvalidArgumentException;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\ValueFormatter;

use function array_key_exists;
use function crc32;
use function dechex;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;
use function strtoupper;

/** @psalm-suppress UnusedClass */
final class ConnectionWrapper extends Connection
{
    /** @phpstan-ignore property.unusedType (int is assigned via parent::setLastInsertId() path in future use) */
    private int|null $lastInsertIdentityId  = null;
    private string|null $lastInsertSequence = null;

    /**
     * Instance cache to avoid stale static across DB recreations.
     *
     * @var array<string, array<string, string>|null>
     */
    private array $identityColumnCache = [];

    public function extractIdentityColumn(string $sql): string
    {
        $platform = $this->getDatabasePlatform();

        if (! $platform->supportsIdentityColumns() && ! $platform instanceof FirebirdPlatform) {
            return $sql;
        }

        // Instance cache (not static) to avoid stale entries across DB recreations.
        // When setUpBeforeClass drops+recreates the DB and reconnects, a new
        // ConnectionWrapper instance is created and this cache starts empty.
        /** @var array<string, array<string, string>|null> */
        $identityColumnTables = $this->identityColumnCache ?? [];
        $table                = $this->getTableNameFromInsert($sql);
        if ($table !== null) {
            if (! array_key_exists($table, $identityColumnTables)) {
                $identityColumnTables[$table] = $this->getIdentityColumnForTable($table);
            }

            if ($identityColumnTables[$table] === null) {
                $this->addSequenceNameForTable($table);
            }

            if (isset($identityColumnTables[$table]['id'])) {
                $sql .= ' RETURNING ' . $identityColumnTables[$table]['id'] . ' AS "' . $identityColumnTables[$table]['alias'] . '"';
                if ($this->_conn instanceof \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection) {
                    $this->_conn->setConnectionInsertColumn($identityColumnTables[$table]['id']);
                }
            }

            $this->identityColumnCache = $identityColumnTables;
        }

        return $sql;
    }

    public function getTableNameFromInsert(string $sql): string|null
    {
        if (preg_match('/INSERT INTO\s+([a-zA-Z0-9_]+)/i', $sql, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        $sql = $this->extractIdentityColumn($sql);

        return parent::prepare($sql);
    }

    /** @inheritDoc */
    #[Override]
    public function executeQuery(
        string $sql,
        array $params = [],
        $types = [],
        QueryCacheProfile|null $qcp = null,
    ): Result {
        $sql = $this->extractIdentityColumn($sql);

        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function executeStatement($sql, array $params = [], array $types = []): int|string
    {
        $sql = $this->extractIdentityColumn($sql);

        return parent::executeStatement($sql, $params, $types);
    }

    /**
     * @inheritDoc
     * @psalm-suppress DocblockTypeContradiction
     * */
    #[Override]
    public function lastInsertId($name = null): string|int|false
    {
        if ($name !== null && ! is_string($name)) {
            throw new InvalidArgumentException(sprintf('Argument $name in %s must be null or a string. Found: %s', __FUNCTION__, ValueFormatter::found($name)));
        }

        if ($this->lastInsertIdentityId !== null && $name === null) {
            return $this->lastInsertIdentityId;
        }

        if ($this->lastInsertSequence !== null && $name === null) {
            $name = $this->lastInsertSequence;
        }

        return parent::lastInsertId($name);
    }

    #[Override]
    public function getDatabase(): string|null
    {
        // Bypass parent::getDatabase() which has an assert(is_string($database) || $database === null)
        // that fails when fetchOne() returns false (Firebird edge case where the query
        // returns no rows or fails). Instead, execute the same query directly and handle false.
        $query = 'SELECT ' . $this->getDatabasePlatform()->getCurrentDatabaseExpression();

        try {
            $database = $this->fetchOne($query);
        } catch (Exception) {
            return $this->resolveDatabaseFromParams();
        }

        if ($database === false) {
            return $this->resolveDatabaseFromParams();
        }

        return $database;
    }

    /**
     * Extract database name from connection params when the SQL query fails.
     *
     * For Firebird, 'dbname' is a file path (e.g. /var/lib/firebird/data/test.fdb).
     * Return it as-is - the schema manager only requires a non-null string.
     */
    private function resolveDatabaseFromParams(): string|null
    {
        $params = $this->getParams();
        $dbname = $params['dbname'] ?? $params['database'] ?? null;

        if ($dbname === null || $dbname === '' || ! is_string($dbname)) {
            return null;
        }

        return $dbname;
    }

    /**
     * @return array<string,string>|null
     *
     * @throws Exception
     */
    private function getIdentityColumnForTable(string $tableName): array|null
    {
        $schemaManager = $this->createSchemaManager();

        try {
            $columns = $schemaManager->introspectTable($tableName)->getColumns();
        } catch (SchemaException\TableDoesNotExist) {
            return null;
        }

        foreach ($columns as $column) {
            if ($column->getAutoincrement()) {
                 return [
                     'id' => $column->getName(),
                     'alias' => 'ID' . strtoupper(dechex(crc32($tableName)) . '.' . dechex(crc32($column->getName()))),
                 ];
            }
        }

        return null;
    }

    /** @throws Exception */
    private function addSequenceNameForTable(string $tableName): void
    {
        static $tableSequences = [];
        if (! array_key_exists($tableName, $tableSequences)) {
            $schemaManager = $this->createSchemaManager();
            // Get the columns for the table
            /** @phpstan-ignore method.deprecated */
            $sequenceForTable      = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, '');
            $sequences             = $schemaManager->listSequences();
            $_identitySequenceName = null;
            foreach ($sequences as $sequence) {
                if (strtolower($sequence->getName()) !== strtolower($sequenceForTable)) {
                    continue;
                }

                /** @phpstan-ignore method.deprecated */
                $_identitySequenceName    = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, '');
                $this->lastInsertSequence = $_identitySequenceName;
            }

            $tableSequences[$tableName] = $_identitySequenceName;
        } else {
            $this->lastInsertSequence = $tableSequences[$tableName];
        }
    }
}
