<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use InvalidArgumentException;
use Satag\DoctrineFirebirdDriver\ValueFormatter;

use function crc32;
use function dechex;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;
use function strtoupper;

final class ConnectionWrapper extends Connection
{
    private int|null $lastInsertIdentityId  = null;
    private string|null $lastInsertSequence = null;

    public function extractIdentityColumn(string $sql): string
    {
        static $identityColumnTables = [];
        $table                       = $this->getTableNameFromInsert($sql);
        if ($table !== null) {
            if (! isset($identityColumnTables[$table])) {
                $identityColumnTables[$table] = $this->getIdentityColumnForTable($table);
            }

            if (! $identityColumnTables[$table]) {
                $this->addSequenceNameForTable($table);
            }
        }

        if (isset($identityColumnTables[$table])) {
            $sql .= ' RETURNING ' . $identityColumnTables[$table]['id'] . ' AS "' . $identityColumnTables[$table]['alias'] . '"';
            if ($this->_conn instanceof \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection) {
                $this->_conn->setConnectionInsertTableColumn($table, $identityColumnTables[$table]['id']);
            }
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

    public function prepare(string $sql): Statement
    {
        $sql = $this->extractIdentityColumn($sql);

        return parent::prepare($sql);
    }

    /** @inheritDoc */
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
    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $sql = $this->extractIdentityColumn($sql);

        return parent::executeStatement($sql, $params, $types);
    }

    /** @inheritDoc */
    public function lastInsertId($name = null)
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

    /**
     * {@inheritDoc}
     */
    public function getDatabase()
    {
        static $database = null;
        if ($database === null) {
            $database = parent::getDatabase();
        }

        return $database;
    }

    /**
     * @return array<string,string>|null
     *
     * @throws Exception
     */
    private function getIdentityColumnForTable(string $tableName): array|null
    {
        $schemaManager = $this->createSchemaManager();

        // Get the columns for the table
        $columns = $schemaManager->listTableColumns($tableName);

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
        if (! isset($tableSequences[$tableName])) {
            $schemaManager = $this->createSchemaManager();
            // Get the columns for the table
            $sequenceForTable = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, '');
            $sequences        = $schemaManager->listSequences();
            $D2IS             = null;
            foreach ($sequences as $sequence) {
                if (strtolower($sequence->getName()) !== strtolower($sequenceForTable)) {
                    continue;
                }

                $D2IS                     = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, '');
                $this->lastInsertSequence = $D2IS;
            }

            $tableSequences[$tableName] = $D2IS;
        } else {
            $this->lastInsertSequence = $tableSequences[$tableName];
        }
    }
}
