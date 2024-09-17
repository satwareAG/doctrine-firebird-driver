<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ExpandArrayParameters;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Driver\Exception as DBALException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

final class Firebird3Connection extends \Doctrine\DBAL\Connection
{
    static protected ?int $lastInsertIdentityId = null;

    static protected array $lastInsertIdenties = [];
    private static ?string $lastInsertSequence = null;
    private static array $lastInsertSequences =  [];

    public function prepare(string $sql): Statement
    {
        if ( stripos($sql, 'INSERT INTO') === 0 ) {
            $tableName = $this->getTableNameFromInsert($sql);
            if($this->getDatabasePlatform() instanceof Firebird3Platform) {
                $identityColumn = $this->getIdentityColumnForTable($tableName);
                if($identityColumn !== null) {
                    $sql .= ' RETURNING ' . $identityColumn;
                };
            } else {
                $this->addSequenceNameForTable($tableName);
            }

        }
        return parent::prepare($sql);
    }

    public static function getTableNameFromInsert($sql): ?string
    {
        if (preg_match('/INSERT INTO\s+([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();
        if ($logger !== null) {
            $logger->startQuery($sql, $params, $types);
        }

        try {
            if (count($params) > 0) {
                if ($this->needsArrayParameterConversion($params, $types)) {
                    [$sql, $params, $types] = $this->expandArrayParameters($sql, $params, $types);
                }

                $stmt = $connection->prepare($sql);

                $this->bindParameters($stmt, $params, $types);
                return  $stmt->execute()->rowCount();
            }
            if ( stripos($sql, 'INSERT INTO') === 0 && stripos($sql, ' RETURNING ') === false) {
                $tableName = $this->getTableNameFromInsert($sql);
                $identityColumn = $this->getIdentityColumnForTable($tableName);
                if($identityColumn !== null) {
                    $sql .= ' RETURNING ' . $identityColumn;
                } else {
                    $this->addSequenceNameForTable($tableName);
                }

            }
            return $connection->exec($sql);
        } catch (DBALException $e) {
            throw $this->convertExceptionDuringQuery($e, $sql, $params, $types);
        } finally {
            if ($logger !== null) {
                $logger->stopQuery();
            }
        }


    }

    private function expandArrayParameters(string $sql, array $params, array $types): array
    {
        $this->parser ??= $this->getDatabasePlatform()->createSQLParser();
        $visitor        = new ExpandArrayParameters($params, $types);

        $this->parser->parse($sql, $visitor);

        return [
            $visitor->getSQL(),
            $visitor->getParameters(),
            $visitor->getTypes(),
        ];
    }

    private function bindParameters(DriverStatement $stmt, array $params, array $types): void
    {
        // Check whether parameters are positional or named. Mixing is not allowed.
        if (is_int(key($params))) {
            $bindIndex = 1;

            foreach ($params as $key => $value) {
                if (isset($types[$key])) {
                    $type                  = $types[$key];
                    [$value, $bindingType] = $this->getBindingInfo($value, $type);
                } else {
                    if (array_key_exists($key, $types)) {
                        Deprecation::trigger(
                            'doctrine/dbal',
                            'https://github.com/doctrine/dbal/pull/5550',
                            'Using NULL as prepared statement parameter type is deprecated.'
                            . 'Omit or use ParameterType::STRING instead',
                        );
                    }

                    $bindingType = ParameterType::STRING;
                }

                $stmt->bindValue($bindIndex, $value, $bindingType);

                ++$bindIndex;
            }
        } else {
            // Named parameters
            foreach ($params as $name => $value) {
                if (isset($types[$name])) {
                    $type                  = $types[$name];
                    [$value, $bindingType] = $this->getBindingInfo($value, $type);
                } else {
                    if (array_key_exists($name, $types)) {
                        Deprecation::trigger(
                            'doctrine/dbal',
                            'https://github.com/doctrine/dbal/pull/5550',
                            'Using NULL as prepared statement parameter type is deprecated.'
                            . 'Omit or use ParameterType::STRING instead',
                        );
                    }

                    $bindingType = ParameterType::STRING;
                }

                $stmt->bindValue($name, $value, $bindingType);
            }
        }
    }
    private function needsArrayParameterConversion(array $params, array $types): bool
    {
        if (is_string(key($params))) {
            return true;
        }

        foreach ($types as $type) {
            if (
                $type === ArrayParameterType::INTEGER
                || $type === ArrayParameterType::STRING
                || $type === ArrayParameterType::ASCII
                || $type === ArrayParameterType::BINARY
            ) {
                return true;
            }
        }

        return false;
    }

    public function insert($table, array $data, array $types = [])
    {
        if (count($data) === 0) {
            return $this->executeStatement('INSERT INTO ' . $table . ' () VALUES ()');
        }

        $columns = [];
        $values  = [];
        $set     = [];

        foreach ($data as $columnName => $value) {
            $columns[] = $columnName;
            $values[]  = $value;
            $set[]     = '?';
        }

        $identityColumn = $this->getIdentityColumnForTable($table);
        if (!$identityColumn) {
            $this->addSequenceNameForTable($table);
        }
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ')' .
            ' VALUES (' . implode(', ', $set) . ')';
        return $this->executeStatement(
            $sql . ($identityColumn !== null ? ' RETURNING ' . $identityColumn : ''),
            $values,
            is_string(key($types)) ? $this->extractTypeValues($columns, $types) : $types,
        );
    }
    protected function extractTypeValues(array $columnList, array $types): array
    {
        $typeValues = [];

        foreach ($columnList as $columnName) {
            $typeValues[] = $types[$columnName] ?? ParameterType::STRING;
        }

        return $typeValues;
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

    public static function addLastInsertId(mixed $fetchOne, $sql): void
    {
        $id = (int) $fetchOne;
        $tableName = self::getTableNameFromInsert($sql);
        self::$lastInsertIdenties[$tableName][] = $id;
        self::$lastInsertIdentityId = $id;
    }

    public function lastInsertId($name = null)
    {

        if(self::$lastInsertIdentityId !== null && $name === null) {
            return self::$lastInsertIdentityId;
        }

        if (str_contains((string)$name, '.')) {
            list($table, $column) = preg_split('/\./', $name);
            $tableInsertIds = self::$lastInsertIdenties[$table] ?? [];
            return end($tableInsertIds);
        }

        if(self::$lastInsertSequence !== null && $name === null) {
            $name = self::$lastInsertSequence;

        }
        return parent::lastInsertId($name);
    }

    private function getBindingInfo($value, $type): array
    {
        if (is_string($type)) {
            $type = Type::getType($type);
        }

        if ($type instanceof Type) {
            $value       = $type->convertToDatabaseValue($value, $this->getDatabasePlatform());
            $bindingType = $type->getBindingType();
        } else {
            $bindingType = $type ?? ParameterType::STRING;
        }

        return [$value, $bindingType];
    }

    private function addSequenceNameForTable(?string $tableName)
    {
        $schemaManager = $this->createSchemaManager();

        // Get the columns for the table
        $sequenceForTable = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, null);
        $sequences = $schemaManager->listSequences();
        foreach($sequences as $sequence) {
            if (strtolower($sequence->getName()) === strtolower($sequenceForTable)) {
                $D2IS = $this->getDatabasePlatform()->getIdentitySequenceName($tableName, '');
                self::$lastInsertSequences[$tableName] = $D2IS;
                self::$lastInsertSequence = $D2IS;
            }
        }
    }
}
