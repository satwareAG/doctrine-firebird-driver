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
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ConnectionWrapper;
use Throwable;

use function array_keys;
use function array_map;
use function array_values;
use function basename;
use function escapeshellarg;
use function fclose;
use function file_exists;
use function file_put_contents;
use function getenv;
use function implode;
use function in_array;
use function is_resource;
use function is_string;
use function md5;
use function mkdir;
use function proc_close;
use function proc_open;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function stream_get_contents;
use function strlen;
use function strtoupper;
use function substr;
use function tempnam;
use function trim;
use function unlink;

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
     * Execute SQL via isql subprocess, bypassing php-firebird driver entirely.
     *
     * Workaround for php-firebird v8.2.0 bug where DDL/DML executed through
     * the PHP connection to a newly created database silently fails (tables
     * not created, rows not inserted) despite no errors thrown.
     *
     * @param string $sql      SQL to execute
     * @param string $dbname   Firebird database path
     * @param string $user     Firebird user
     * @param string $password Firebird password
     * @param string $host     Firebird host (for connect string)
     *
     * @throws RuntimeException If isql returns non-zero exit code.
     */
    public static function runIsql(
        string $sql,
        string $dbname,
        string $user,
        string $password,
        string $host = '127.0.0.1',
    ): void {
        $isqlBin = '/usr/bin/isql-fb';
        if (! file_exists($isqlBin)) {
            $isqlBin = '/opt/firebird/bin/isql';
        }

        if (! file_exists($isqlBin)) {
            throw new RuntimeException('isql not found (checked /usr/bin/isql-fb and /opt/firebird/bin/isql)');
        }

        // Build Firebird connect string: host:/path/to/db.fdb
        $connectString = $host . ':' . $dbname;

        // Write SQL to temp file to avoid shell escaping issues
        $tmpFile = tempnam('/tmp', 'fb_isql_');
        file_put_contents($tmpFile, $sql . "\nCOMMIT;\nQUIT;\n");

        $cmd = sprintf(
            '%s -z -user %s -password %s %s < %s 2>&1',
            escapeshellarg($isqlBin),
            escapeshellarg($user),
            escapeshellarg($password),
            escapeshellarg($connectString),
            escapeshellarg($tmpFile),
        );

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin (unused, we redirect from file)
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($process)) {
            @unlink($tmpFile);

            throw new RuntimeException('Failed to start isql process');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        @unlink($tmpFile);

        $output = trim($stdout . "\n" . $stderr);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'isql failed (exit ' . $exitCode . '): ' . $output,
                $exitCode,
            );
        }

        // isql may return 0 but still have errors in output (e.g., "Statement failed")
        if (str_contains($output, 'Statement failed') || str_contains($output, 'Error:')) {
            throw new RuntimeException('isql reported error: ' . $output);
        }
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
            if (! file_exists($tempDir)) {
                @mkdir($tempDir, 0777, true);
            }

            if (! str_starts_with($baseName, $tempDir)) {
                // Strip any path and force into tempDir
                $baseName = basename(str_replace('\\', '/', $baseName));
                $baseName = $tempDir . '\\' . $baseName;
            }

            // On Windows CI, if we are forcing a recreation for a specific class,
            // use a unique filename to avoid locking issues between test classes.
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

            // Connect directly to $currentName so dropDatabase can reuse the native
            // connection resource (avoids the v8 default-link SIGSEGV bug).
            $privilegedParams['dbname']                      = $currentName;
            $privilegedParams['persistent']                  = false;
            $privilegedParams['driverOptions']['persistent'] = false;

            $privilegedConnection = null;
            try {
                $privilegedConnection = DriverManager::getConnection($privilegedParams);
                // Simple health check — confirms the DB exists and is reachable
                $privilegedConnection->executeQuery($privilegedConnection->getDatabasePlatform()->getDummySelectSQL());
            } catch (Throwable) {
                // DB doesn't exist yet — connect to employee to perform CREATE DATABASE
                $privilegedParams['dbname'] = 'employee';
                try {
                    $privilegedConnection = DriverManager::getConnection($privilegedParams);
                } catch (Throwable) {
                    // Final fallback
                    $privilegedParams['dbname'] = $currentName;
                    $privilegedConnection       = DriverManager::getConnection($privilegedParams);
                }
            }

            try {
                $sm = $privilegedConnection->createSchemaManager();

                // Drop the database if it exists; ignore errors (DB may not exist yet).
                // dropDatabase() reuses the existing native fbird resource and closes
                // $this->_conn internally (v8 default-link fix). After drop, we must
                // reconnect before calling createDatabase.
                try {
                    @$sm->dropDatabase($currentName);
                } catch (Throwable) {
                    // DB may not exist yet — safe to ignore
                }

                // Reconnect after dropDatabase (which closes the underlying connection)
                $privilegedParams['dbname'] = 'employee';
                try {
                    $privilegedConnection = DriverManager::getConnection($privilegedParams);
                } catch (Throwable) {
                    $privilegedParams['dbname'] = $baseParams['dbname'];
                    $privilegedConnection       = DriverManager::getConnection($privilegedParams);
                }

                $sm = $privilegedConnection->createSchemaManager();
                $sm->createDatabase($currentName);
                self::$effectiveDbName = $currentName;

                if ($privilegedConnection->isTransactionActive()) {
                    $privilegedConnection->rollBack();
                }

                // CRITICAL: Do NOT close and recreate the connection.
                // php-firebird v8.2.0 cannot reliably reconnect to a newly
                // created DB file in the same process - the second connection
                // gets empty DB path and false health check.
                // Instead, reconnect NOW to the new database and cache it.
                $privilegedConnection->close();
                $newDbParams                                = $params;
                $newDbParams['wrapperClass']                = ConnectionWrapper::class;
                $newDbParams['persistent']                  = false;
                $newDbParams['driverOptions']['persistent'] = false;
                self::$sharedConnection                     = DriverManager::getConnection(
                    $newDbParams,
                    self::createConfiguration(),
                );
                // Verify the connection actually works
                self::$sharedConnection->executeQuery(
                    self::$sharedConnection->getDatabasePlatform()->getDummySelectSQL(),
                );

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
                    echo 'CRITICAL: Database initialization failed after ' . $maxSlots . ' attempts: ' . $e->getMessage() . "\n";
                    echo $e->getTraceAsString() . "\n";

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
