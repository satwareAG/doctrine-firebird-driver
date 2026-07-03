<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Portability\Middleware;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
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
use function md5;
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

    /** Cached shared connection - reused across tests to avoid reconnection issues. */
    private static Connection|null $sharedConnection = null;

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
        // Return cached connection if still valid (avoids reconnection issues
        // with php-firebird where a second connect to the same DB file fails
        // with empty DB path and false health check).
        if (self::$sharedConnection !== null) {
            try {
                self::$sharedConnection->fetchOne('SELECT 1 FROM RDB$DATABASE');

                return self::$sharedConnection;
            } catch (Throwable) {
                self::$sharedConnection = null;
            }
        }

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
            new Middleware(0, ColumnCase::UPPER),
        ]);

        self::$sharedConnection = DriverManager::getConnection(
            $params,
            $configuration,
        );

        return self::$sharedConnection;
    }

    /**
     * Reset the shared connection cache without calling close().
     * Used when the underlying native resource has been invalidated
     * and we need a fresh connection on next getConnection() call.
     */
    public static function resetSharedConnection(): void
    {
        self::$sharedConnection = null;
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

    /**
     * Create/recreate the test database file.
     * Public so integration tests can call it independently of getConnection().
     */
    public static function initializeDatabase(bool $force = false, string|null $className = null): void
    {
        // Skip if already initialized for this run, unless $force is true.
        if (! $force) {
            if ($className !== null && isset(self::$classInitialized[$className])) {
                return;
            }

            if ($className === null && self::$runInitialized) {
                return;
            }
        }

        $baseParams = self::mapConnectionParameters($GLOBALS, 'db_');

        // On Windows CI, ensure we use a simple writable path that Firebird likes
        if (PHP_OS_FAMILY === 'Windows' && getenv('CI')) {
            $tempDir = 'C:\\firebird_tests';
            if (! file_exists($tempDir)) {
                @mkdir($tempDir, 0777, true);
            }

            $baseName = $baseParams['dbname'];
            if (! str_starts_with($baseName, $tempDir)) {
                $baseName = basename(str_replace('\\', '/', $baseName));
                $baseName = $tempDir . '\\' . $baseName;
            }

            if ($force && $className !== null) {
                $uniqueId = substr(md5($className), 0, 8);
                $ext      = str_ends_with($baseName, '.fdb') ? '.fdb' : '';
                if ($ext !== '') {
                    $baseName = substr($baseName, 0, -4);
                }

                $baseName .= '_' . $uniqueId . $ext;
            }

            $baseParams['dbname'] = $baseName;
        }

        // Connect to the database. If it doesn't exist, create it.
        $params                                = $baseParams;
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;
        $params['wrapperClass']                = ConnectionWrapper::class;

        $connection = null;
        try {
            $connection = DriverManager::getConnection($params, self::createConfiguration());
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (Throwable) {
            // DB doesn't exist yet — create it via a privileged connection
            if ($connection !== null) {
                try {
                    $connection->close();
                } catch (Throwable) {
                }
            }

            $privilegedParams                                = self::getPrivilegedConnectionParameters();
            $privilegedParams['dbname']                      = $baseParams['dbname'];
            $privilegedParams['persistent']                  = false;
            $privilegedParams['driverOptions']['persistent'] = false;

            try {
                $privilegedConnection = DriverManager::getConnection($privilegedParams);
                $sm                   = $privilegedConnection->createSchemaManager();
                $sm->createDatabase($baseParams['dbname']);

                if ($privilegedConnection->isTransactionActive()) {
                    $privilegedConnection->rollBack();
                }

                $privilegedConnection->close();
            } catch (Throwable) {
                // DB may already exist (race) or creation failed — fall through
            }

            // Connect to the freshly created DB
            $connection = DriverManager::getConnection($params, self::createConfiguration());
        }

        // When force=true (integration tests), clean all user objects so
        // installFirebirdDatabase() can recreate schema from scratch.
        if ($force) {
            $cleanupSql = "EXECUTE BLOCK AS\n"
                . "  DECLARE cname VARCHAR(63);\n"
                . "  DECLARE tname VARCHAR(63);\n"
                . "BEGIN\n"
                . "  FOR SELECT rc.RDB\$CONSTRAINT_NAME, rc.RDB\$RELATION_NAME\n"
                . "      FROM RDB\$RELATION_CONSTRAINTS rc\n"
                . "      WHERE rc.RDB\$CONSTRAINT_TYPE = 'FOREIGN KEY'\n"
                . "      INTO :cname, :tname DO\n"
                . "  BEGIN\n"
                . "    EXECUTE STATEMENT 'ALTER TABLE \"' || TRIM(:tname) || '\" DROP CONSTRAINT \"' || TRIM(:cname) || '\"';\n"
                . "    WHEN ANY DO BEGIN /* ignore */ END\n"
                . "  END\n"
                . "END;\n"
                . "EXECUTE BLOCK AS\n"
                . "  DECLARE tname VARCHAR(63);\n"
                . "BEGIN\n"
                . "  FOR SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS\n"
                . "      WHERE RDB\$SYSTEM_FLAG = 0 AND RDB\$VIEW_BLR IS NULL\n"
                . "      INTO :tname DO\n"
                . "  BEGIN\n"
                . "    EXECUTE STATEMENT 'DROP TABLE \"' || TRIM(:tname) || '\"';\n"
                . "    WHEN ANY DO BEGIN /* ignore */ END\n"
                . "  END\n"
                . "END;\n"
                . "EXECUTE BLOCK AS\n"
                . "  DECLARE gname VARCHAR(63);\n"
                . "BEGIN\n"
                . "  FOR SELECT RDB\$GENERATOR_NAME FROM RDB\$GENERATORS\n"
                . "      WHERE RDB\$SYSTEM_FLAG = 0\n"
                . "      INTO :gname DO\n"
                . "  BEGIN\n"
                . "    EXECUTE STATEMENT 'DROP SEQUENCE \"' || TRIM(:gname) || '\"';\n"
                . "    WHEN ANY DO BEGIN /* ignore */ END\n"
                . "  END\n"
                . "END;\n";

            try {
                $connection->executeStatement($cleanupSql);
            } catch (Throwable) {
                // Cleanup errors are non-fatal (tables may not exist yet)
            }
        }

        self::$effectiveDbName  = $baseParams['dbname'];
        self::$sharedConnection = $connection;

        if ($className !== null) {
            self::$classInitialized[$className] = true;
        } else {
            self::$runInitialized = true;
        }
    }

    private static function hasRequiredConnectionParams(): bool
    {
        return isset($GLOBALS['db_driver_class']);
    }

    private static function createConfiguration(): Configuration
    {
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        // Logging middleware disabled - DG\BypassFinals\MutatingWrapper in
        // Docker test env breaks stream_open for log files.

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
