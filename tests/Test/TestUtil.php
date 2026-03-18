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
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Throwable;

use function array_keys;
use function array_map;
use function array_values;
use function file_exists;
use function getenv;
use function implode;
use function in_array;
use function is_string;
use function mkdir;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;
use function unlink;

use const PHP_OS_FAMILY;

/**
 * TestUtil is a class with static utility methods used during tests.
 */
class TestUtil
{
    /** Whether the database schema is initialized. */
    private static bool $initialized = false;

    /** The actual database name being used (after resolving locks). */
    private static string|null $effectiveDbName = null;

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
        $baseParams = self::mapConnectionParameters($GLOBALS, 'db_');
        $baseName   = $baseParams['dbname'];

        // On Windows CI, ensure we use a simple writable path that Firebird likes
        if (PHP_OS_FAMILY === 'Windows' && getenv('CI') && ! str_contains($baseName, '/') && ! str_contains($baseName, '\\')) {
            $tempDir              = 'C:\\firebird_tests';
            $baseName             = $tempDir . '\\' . $baseName;
            $baseParams['dbname'] = $baseName;
            if (! file_exists($tempDir)) {
                @mkdir($tempDir, 0777, true);
            }
        }

        $ext = '';
        if (str_ends_with($baseName, '.fdb')) {
            $baseName = substr($baseName, 0, -4);
            $ext      = '.fdb';
        }

        $maxSlots = 5;
        for ($i = 0; $i < $maxSlots; $i++) {
            $currentName = $i === 0 ? $baseParams['dbname'] : $baseName . '_' . $i . $ext;

            $params           = $baseParams;
            $params['dbname'] = $currentName;
            // Explicitly disable persistence
            $params['persistent']                  = false;
            $params['driverOptions']['persistent'] = false;

            $connection = DriverManager::getConnection($params);
            // Silencing createSchemaManager/dropDatabase because they might trigger connection which warns if DB doesn't exist
            try {
                $sm = @$connection->createSchemaManager();
                try {
                    @$sm->dropDatabase($currentName);
                } catch (DatabaseDoesNotExist) {
                    // Expected
                } catch (Exception $e) {
                    // Fallback: try local unlink if possible
                    if (! str_ends_with($currentName, '.fdb') || ! file_exists($currentName)) {
                        // If we cannot drop/delete, and it's not the last slot, try next slot
                        if ($i < $maxSlots - 1) {
                            $connection->close();
                            continue;
                        }

                        throw $e;
                    }

                    unlink($currentName);
                }

                // If we are here, database is dropped or didn't exist. Now create it.
                $sm->createDatabase($currentName);
                self::$effectiveDbName = $currentName;
                $connection->close();

                return;
            } catch (Throwable $e) {
                $connection->close();
                if ($i === $maxSlots - 1) {
                    throw $e;
                }
            }
        }
    }

    private static function createConfiguration(): Configuration
    {
        static $logger = null;
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        if ($logger === null) {
            $logger = new Logger('sql_logger');
            $logger
                ->pushProcessor(new MemoryUsageProcessor())
                ->pushHandler(
                    new StreamHandler(__DIR__ . '/../../../var/sql_query.log', Level::Debug),
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
        $params = self::mapConnectionParameters($GLOBALS, 'db_');

        if (self::$effectiveDbName !== null) {
            $params['dbname'] = self::$effectiveDbName;
        }

        return $params;
    }

    /**
     * @param array<string,mixed> $configuration
     *
     * @return array<string,mixed>
     */
    private static function mapConnectionParameters(array $configuration, string $prefix): array
    {
        $parameters  = [];
        $driverClass = $configuration['db_driver_class'] ?? 'Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver';

        $dbHost = getenv('DB_HOST') ?: ($configuration['db_host'] ?? $configuration[$prefix . 'host'] ?? '127.0.0.1');

        foreach (
            [
                'driver',
                'user',
                'password',
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
            $envValue = getenv('DB_' . strtoupper($parameter));
            if ($envValue !== false) {
                $parameters[$parameter] = $envValue;
                continue;
            }

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

        $parameters['driverClass'] = $driverClass;
        $parameters['host']        = $dbHost;

        return $parameters;
    }
}
