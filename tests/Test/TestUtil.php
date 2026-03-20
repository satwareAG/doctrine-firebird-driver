<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ConnectionWrapper;
use Throwable;

use function array_keys;
use function array_map;
use function array_values;
use function basename;
use function file_exists;
use function getenv;
use function implode;
use function in_array;
use function is_string;
use function mkdir;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;

use const PHP_OS_FAMILY;

/**
 * TestUtil is a class with static utility methods used during tests.
 */
class TestUtil
{
    /** Whether the database schema is initialized for the current test run. */
    private static bool $runInitialized = false;

    /**
     * Map of class-level initialization flags.
     * Some test classes require a truly fresh database.
     *
     * @var array<string, bool>
     */
    private static array $classInitialized = [];

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
        if (self::hasRequiredConnectionParams() && ! self::$runInitialized) {
            self::initializeDatabase();
            self::$runInitialized = true;
        }

        $params                 = self::getTestConnectionParameters();
        $params['wrapperClass'] = ConnectionWrapper::class;

        // Force non-persistent connections in tests to avoid shutdown crashes
        // and lock issues on both Linux and Windows runners.
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;

        $configuration = self::createConfiguration();

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

    public static function initializeDatabase(bool $force = false, string|null $className = null): void
    {
        // Skip if already initialized for this run, unless $force is true.
        // If $className is provided, we only skip if THAT class was already initialized.
        if (! $force) {
            if ($className !== null && isset(self::$classInitialized[$className])) {
                return;
            }

            if ($className === null && self::$runInitialized) {
                return;
            }
        }

        $baseParams = self::mapConnectionParameters($GLOBALS, 'db_');
        $baseName   = $baseParams['dbname'];

        // On Windows CI, ensure we use a simple writable path that Firebird likes
        if (PHP_OS_FAMILY === 'Windows' && getenv('CI')) {
            $tempDir = 'C:\\firebird_tests';
            if (! str_starts_with($baseName, $tempDir)) {
                // Strip any path and force into tempDir
                $baseName = basename(str_replace('\\', '/', $baseName));
                $baseName = $tempDir . '\\' . $baseName;
            }

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

            // Use a separate connection to drop/create the database
            // On Windows CI, we've already ensured C:\firebird_tests exists and is writable
            $privilegedParams = self::getPrivilegedConnectionParameters();

            // Try connecting to the primary DB name first (it might already exist)
            $privilegedParams['dbname']                      = $baseParams['dbname'];
            $privilegedParams['persistent']                  = false;
            $privilegedParams['driverOptions']['persistent'] = false;

            $privilegedConnection = null;
            try {
                $privilegedConnection = DriverManager::getConnection($privilegedParams);
                // Simple health check
                $privilegedConnection->executeQuery($privilegedConnection->getDatabasePlatform()->getDummySelectSQL());
            } catch (Throwable) {
                // If target DB doesn't exist, connect to a system database to perform CREATE DATABASE
                // On Windows/Linux, 'employee' is usually available or we can use the default security DB
                $privilegedParams['dbname'] = 'employee';
                try {
                    $privilegedConnection = DriverManager::getConnection($privilegedParams);
                } catch (Throwable) {
                    // Final fallback: just use the base name and hope it works for createDatabase
                    $privilegedParams['dbname'] = $baseParams['dbname'];
                    $privilegedConnection       = DriverManager::getConnection($privilegedParams);
                }
            }

            try {
                $sm = $privilegedConnection->createSchemaManager();

                // On Windows, the drop might fail if locks are held.
                // We retry with a small delay.
                for ($retry = 0; $retry < 3; $retry++) {
                    try {
                        @$sm->dropDatabase($currentName);
                        break;
                    } catch (Throwable) {
                        if ($retry === 2) {
                            // Last attempt failed, but createDatabase might still work if drop was partial
                        }
                        usleep(100000); // 100ms
                    }
                }

                // Create the test database
                $sm->createDatabase($currentName);
                self::$effectiveDbName = $currentName;

                // Explicitly close the privileged connection and clear its state
                if ($privilegedConnection->isTransactionActive()) {
                    $privilegedConnection->rollBack();
                }

                $privilegedConnection->close();

                if ($className !== null) {
                    self::$classInitialized[$className] = true;
                } else {
                    self::$runInitialized = true;
                }

                return;
            } catch (Throwable $e) {
                if ($privilegedConnection instanceof Connection) {
                    try {
                        if ($privilegedConnection->isTransactionActive()) {
                            $privilegedConnection->rollBack();
                        }

                        $privilegedConnection->close();
                    } catch (Throwable) {
                        // Ignore cleanup errors
                    }
                }

                if ($i === $maxSlots - 1) {
                    throw $e;
                }
            }
        }
    }

    private static function hasRequiredConnectionParams(): bool
    {
        return isset($GLOBALS['db_driver_class']);
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
        $dbHost      = getenv('DB_HOST') ?: ($configuration['db_host'] ?? $configuration[$prefix . 'host'] ?? '127.0.0.1');

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
