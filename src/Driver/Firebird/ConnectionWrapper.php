<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use InvalidArgumentException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception\InvalidParamType;
use Satag\DoctrineFirebirdDriver\ValueFormatter;


final class ConnectionWrapper extends \Doctrine\DBAL\Connection
{
    private ?int $lastInsertIdentityId = null;
    private ?string $lastInsertSequence = null;
    private array $lastInsertSequences =  [];


    public function extractIdentityColumn(string $sql): string
    {
        static $identityColumnTables = [];
        $table = $this->getTableNameFromInsert($sql);
        if ($table !== null) {
            if (!isset ($identityColumnTables[$table])) {
                $identityColumnTables[$table] = $this->getIdentityColumnForTable($table);
            }
            if (!$identityColumnTables[$table]) {
                $this->addSequenceNameForTable($table);
            }
        }
        if (isset($identityColumnTables[$table])) {
            $sql .= ' RETURNING ' . $identityColumnTables[$table] . ' AS "' . $table . '.' .  $identityColumnTables[$table] .'"';
            $this->_conn->setConnectionInsertTableColumn($table, $identityColumnTables[$table]);
        }
        return $sql;
    }

    public function getTableNameFromInsert($sql): ?string
    {
        if (preg_match('/INSERT INTO\s+([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }


    public function prepare(string $sql): Statement
    {
        $sql = $this->extractIdentityColumn($sql);
        return parent::prepare($sql);
    }

    /**
     * @inheritDoc
     */
    public function executeQuery(
        string $sql,
        array $params = [],
        $types = [],
        ?QueryCacheProfile $qcp = null
    ): Result {
        $sql = $this->extractIdentityColumn($sql);
        return parent::executeQuery($sql, $params);

    }
    /**
     * {@inheritDoc}
     */
    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $sql = $this->extractIdentityColumn($sql);
        return parent::executeStatement($sql, $params, $types);
    }


    private function getIdentityColumnForTable($tableName): ?string
    {
        $schemaManager = $this->createSchemaManager();

        // Get the columns for the table
        $columns = $schemaManager->listTableColumns($tableName);

        foreach ($columns as $column) {
            if ($column->getAutoincrement()) {
                return $column->getName();
            }
        }

        return null;
    }


    /**
     * @inheritDoc
     */
    public function lastInsertId($name = null)
    {
        if($name !== null && !is_string($name)) {
            throw new InvalidArgumentException(sprintf('Argument $name in %s must be null or a string. Found: %s', __FUNCTION__, ValueFormatter::found($name)));
        }

        if($this->lastInsertIdentityId !== null && $name === null) {
            return $this->lastInsertIdentityId;
        }



        if($this->lastInsertSequence !== null && $name === null) {
            $name = $this->lastInsertSequence;

        }
        return parent::lastInsertId($name);
    }

    private function addSequenceNameForTable(?string $tableName)
    {
        static $tableSequences = [];
        if(!isset($tableSequences[$tableName])) {
            $schemaManager = $this->createSchemaManager();
            // Get the columns for the table
            $sequenceForTable = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, null);
            $sequences = $schemaManager->listSequences();
            $D2IS = null;
            foreach($sequences as $sequence) {
                if (strtolower($sequence->getName()) === strtolower($sequenceForTable)) {
                    $D2IS = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, '');
                    $this->lastInsertSequence = $D2IS;
                }
            }
            $tableSequences[$tableName] = $D2IS;
        } else {
            $this->lastInsertSequence = $tableSequences[$tableName];
        }

    }
}
