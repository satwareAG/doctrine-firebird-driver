<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use InvalidArgumentException;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetConnectionMiddleware;
use Satag\DoctrineFirebirdDriver\ValueFormatter;

use function is_string;
use function sprintf;

/** @psalm-suppress UnusedClass */
final class ConnectionWrapper extends Connection
{
    /**
     * Returns the underlying Firebird driver connection.
     *
     * DBAL4 removes getWrappedConnection(); this method traverses the
     * middleware chain (if any) to find the raw Firebird\Connection.
     */
    public function getFirebirdDriverConnection(): \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection|null
    {
        if ($this->_conn === null) {
            $this->connect();
        }

        // Direct connection (no middleware wrapping)
        if ($this->_conn instanceof \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection) {
            return $this->_conn;
        }

        // Traverse middleware chain via getWrappedDriverConnection()
        $conn = $this->_conn;
        while ($conn instanceof \Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware) {
            if ($conn instanceof CharsetConnectionMiddleware) {
                $inner = $conn->getWrappedDriverConnection();
                if ($inner instanceof \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection) {
                    return $inner;
                }
                $conn = $inner;
            } else {
                break;
            }
        }

        return null;
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
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
        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function executeStatement($sql, array $params = [], array $types = []): int|string
    {
        return parent::executeStatement($sql, $params, $types);
    }

    #[Override]
    public function lastInsertId(): int|string
    {
        // Delegating to connection for native last_insert_id() / gen_id()
        $connection = $this->getNativeConnection();
        if ($connection instanceof \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection) {
            return $connection->lastInsertId();
        }

        return parent::lastInsertId();
    }

    #[Override]
    /** @return non-empty-string|null */
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

        if ($database === false || $database === '') {
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
     /** @return non-empty-string|null */
     private function resolveDatabaseFromParams(): string|null
    {
        $params = $this->getParams();
        $dbname = $params['dbname'] ?? $params['database'] ?? null;

        if ($dbname === null || $dbname === '' || ! is_string($dbname)) {
            return null;
        }

        return $dbname;
    }
}
