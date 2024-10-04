<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ConnectionWrapper;

use function array_keys;
use function array_map;
use function array_values;
use function implode;
use function in_array;
use function is_string;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * TestUtil is a class with static utility methods used during tests.
 */
class TestUtil
{
    /** Whether the database schema is initialized. */
    private static bool $initialized = false;

    /**
     * Creates a new <b>test</b> database connection using the following parameters
     * of the $GLOBALS array:
     *
     * 'db_driver':   The name of the Doctrine DBAL database driver to use.
     * 'db_user':     The username to use for connecting.
     * 'db_password': The password to use for connecting.
     * 'db_host':     The hostname of the database to connect to.
     * 'db_server':   The server name of the database to connect to
     *                (optional, some vendors allow multiple server instances with different names on the same host).
     * 'db_dbname':   The name of the database to connect to.
     * 'db_port':     The port of the database to connect to.
     *
     * Usually these variables of the $GLOBALS array are filled by PHPUnit based
     * on an XML configuration file. If no such parameters exist, an SQLite
     * in-memory database is used.
     *
     * @return Connection The database connection instance.
     */
    public static function getConnection(): Connection
    {
        if (self::hasRequiredConnectionParams() && ! self::$initialized) {
            self::initializeDatabase();
            self::$initialized = true;
        }

        $params                 = self::getConnectionParams();
        $params['wrapperClass'] = ConnectionWrapper::class;
        $configuration          = self::createConfiguration();

        $configuration->setMiddlewares([
            new \Doctrine\DBAL\Portability\Middleware(0, ColumnCase::UPPER),
        ]);

        return DriverManager::getConnection(
            $params,
            $configuration,
        );
    }

    /** @return mixed[] */
    public static function getConnectionParams(): array
    {
        if (self::hasRequiredConnectionParams()) {
            return self::getTestConnectionParameters();
        }

        return [];
    }

    public static function getPrivilegedConnection(): Connection
    {
        return DriverManager::getConnection(self::getPrivilegedConnectionParameters());
    }

    public static function isDriverClassOneOf(string ...$names): bool
    {
        return in_array(self::getConnectionParams()['driverClass'], $names, true);
    }

    /**
     * Generates a query that will return the given rows without the need to create a temporary table.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public static function generateResultSetQuery(array $rows, AbstractPlatform $platform): string
    {
        return implode(' UNION ALL ', array_map(static fn (array $row): string => $platform->getDummySelectSQL(
            implode(', ', array_map(static function (string $column, $value) use ($platform): string {
                if (is_string($value)) {
                    // We need TRIM here, because Firebird pads all CHAR Types
                    $value = 'TRIM(' . $platform->quoteStringLiteral($value) . ')';
                }

                return $value . $platform->quoteIdentifier($column);
            }, array_keys($row), array_values($row))),
        ), $rows));
    }

    private static function hasRequiredConnectionParams(): bool
    {
        return isset($GLOBALS['db_driver_class']);
    }

    private static function initializeDatabase(): void
    {
        $params     = self::getTestConnectionParameters();
        $connection = DriverManager::getConnection($params);
        $sm         = $connection->createSchemaManager();
        try {
            $sm->dropDatabase($params['dbname']);
        } catch (DatabaseDoesNotExist) {
        }

        $sm->createDatabase($params['dbname']);
        $connection->close();
    }

    private static function createConfiguration(): Configuration
    {
        static $logger = null;
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        if($logger === null) {
            $logger = new Logger('sql_logger');
            $logger
                ->pushProcessor(new MemoryUsageProcessor())
                ->pushHandler(
                    (new StreamHandler(__DIR__ . '/../../../var/sql_query.log', Level::Debug)),
                );
        }

        $configuration->setMiddlewares([
            new Middleware($logger),
        ]);

        return $configuration;
    }

    /** @return mixed[] */
    private static function getPrivilegedConnectionParameters(): array
    {
        if (isset($GLOBALS['tmpdb_driver'])) {
            return self::mapConnectionParameters($GLOBALS, 'tmpdb_');
        }

        return self::mapConnectionParameters($GLOBALS, 'db_');
    }

    /** @return mixed[] */
    private static function getTestConnectionParameters(): array
    {
        return self::mapConnectionParameters($GLOBALS, 'db_');
    }

    /**
     * @param array<string,mixed> $configuration
     *
     * @return array<string,mixed>
     */
    private static function mapConnectionParameters(array $configuration, string $prefix): array
    {
        $parameters = [];

        foreach (
            [
                'driver',
                'user',
                'password',
                'host',
                'dbname',
                'memory',
                'port',
                'server',
                'ssl_key',
                'ssl_cert',
                'ssl_ca',
                'ssl_capath',
                'ssl_cipher',
                'unix_socket',
                'path',
                'charset',
            ] as $parameter
        ) {
            if (! isset($configuration[$prefix . $parameter])) {
                continue;
            }

            $parameters[$parameter] = $configuration[$prefix . $parameter];
        }

        foreach ($configuration as $param => $value) {
            if (! str_starts_with($param, $prefix . 'driver_option_')) {
                continue;
            }

            $parameters['driverOptions'][substr($param, strlen($prefix . 'driver_option_'))] = $value;
        }

        $parameters['driverClass'] = $configuration['db_driver_class'];

        return $parameters;
    }
}
