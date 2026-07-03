<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ConnectionWrapper;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;
use Satag\DoctrineFirebirdDriver\ORM\Mapping\FirebirdQuoteStrategy;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use PHPUnit\Framework\Attributes\Large;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Throwable;

use function array_filter;
use function array_walk;
use function implode;
use function is_string;

use const PHP_EOL;

#[Large]
abstract class AbstractIntegrationTestCase extends FunctionalTestCase
{
    public const DEFAULT_DATABASE_FILE_PATH = '/firebird/data/music_library.fdb';
    public const DEFAULT_DATABASE_USERNAME  = 'SYSDBA';
    public const DEFAULT_DATABASE_PASSWORD  = 'masterkey';

    protected $_entityManager;
    protected $_platform;

    /**
     * Shared connection for integration tests.
     *
     * Reused across tests for performance (avoids repeated connection overhead).
     * php-firebird v8.2.0 had a bug preventing two connections to the same DB file
     * in one process (empty DB path, false health check) — fixed in v11.1.0.
     */
    private static Connection|null $integrationConnection = null;

    /**
     * Guard flag: install database schema + seed data only ONCE per process.
     *
     * Multiple test classes extend AbstractIntegrationTestCase. Each calls
     * setUpBeforeClass() which invokes installFirebirdDatabase(). Without this
     * guard, the second call fails to DROP tables (Firebird metadata lock from
     * the still-open PHP connection) but the INSERT statements succeed, doubling
     * the seed data. Transaction rollback in tearDown() keeps each test isolated.
     */
    private static bool $databaseInstalled = false;

    /**
     * Install database schema ONCE per test class (not per test method).
     */
    public static function setUpBeforeClass(): void
    {
        // initializeDatabase() creates the DB file AND opens a connection.
        // We grab that connection and hold it. Never create a second one.
        // CRITICAL: Do NOT pass $className here. Passing it adds a unique hash
        // to the DB filename (test_<hash>.fdb) and skips setting $runInitialized.
        // The subsequent getConnection() call would then call initializeDatabase()
        // again WITHOUT className, recreating a DIFFERENT file (test.fdb) and
        // overwriting $effectiveDbName. Seed data would go to the hashed file
        // while tests connect to test.fdb — 0 rows.
        TestUtil::initializeDatabase(true);
        self::$integrationConnection = TestUtil::getConnection();

        parent::setUpBeforeClass();

        static::installFirebirdDatabase(self::$integrationConnection, [], static::class);
    }

    /**
     * Override parent getConnection() to return our shared connection.
     * This prevents parent's #[Before] connect() from calling TestUtil again
     * (which could trigger a second broken connection).
     */
    protected static function getConnection(): Connection
    {
        return self::$integrationConnection;
    }

    public function setUp(): void
    {
        // Initialize EntityManager (without reinstalling database)
        $this->setUpEntityManager();

        // Verify Firebird transaction is valid before starting DBAL transaction
        $fbirdConnection = $this->getFirebirdConnection();
        if ($fbirdConnection !== null && ! $fbirdConnection->isTransactionValid()) {
            try {
                $this->connection->executeQuery('SELECT 1 FROM RDB$DATABASE');
            } catch (Throwable) {
                // Let beginTransaction fail naturally if connection is invalid
            }
        }

        // Start transaction to isolate test changes (rollback in tearDown)
        try {
            $this->connection->beginTransaction();
        } catch (Throwable $e) {
            // If beginTransaction fails (e.g. invalid native resource after GC),
            // attempt recovery by getting a fresh connection.
            TestUtil::resetSharedConnection();
            self::$integrationConnection = TestUtil::getConnection();
            $this->connection = self::$integrationConnection;
            $this->setUpEntityManager();

            // Retry once with the fresh connection
            $this->connection->beginTransaction();
        }
    }

    protected function setUpEntityManager(): void
    {
        $doctrineConfiguration = static::getSetUpDoctrineConfiguration($this->connection);

        // Only set savepoint behavior when no transaction is active.
        // DBAL throws if this is called while a transaction is open.
        if (! $this->connection->isTransactionActive()) {
            $this->connection->setNestTransactionsWithSavepoints(true);
        }

        $eventManager = new EventManager();

        $this->_entityManager = new EntityManager($this->connection, $doctrineConfiguration, $eventManager);

        $this->_platform = $this->_entityManager->getConnection()->getDatabasePlatform();
    }

    public function tearDown(): void
    {
        // Rollback transaction to revert any test changes (isolation pattern)
        if ($this->connection->isTransactionActive()) {
            try {
                $this->connection->rollBack();
            } catch (Throwable) {
                // Ignore rollback errors - connection may have been reset
            }
        }

        // Don't mark connection not reusable - we're using transaction isolation
    }

    protected static function installFirebirdDatabase(Connection $connection, array $configurationArray, string|null $className = null): void
    {
        // Skip re-installation if already done in this process.
        if (self::$databaseInstalled) {
            return;
        }

        // Cross-process guard: each CI step runs a separate PHPUnit process,
        // so static $databaseInstalled resets. Check the DB directly: if seed
        // data already exists with the correct count, skip re-installation.
        try {
            $albumCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM "ALBUM"');
            if ($albumCount === 2) {
                self::$databaseInstalled = true;
                echo "[DIAG] Database already seeded (ALBUM rows: {$albumCount}), skipping re-installation\n";

                return;
            }
        } catch (Throwable) {
            // Table doesn't exist yet - proceed with installation
        }

        // Clean existing objects via PHP connection (autocommit commits each statement).
        // Uses EXECUTE BLOCK with WHEN ANY DO so individual failures don't abort.
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

        // Build schema
        $schema = new Schema();
        $tAlbum = $schema->createTable('ALBUM');
        $tAlbum->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tAlbum->addColumn('timeCreated', 'datetime', ['notnull' => true]);
        $tAlbum->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tAlbum->addColumn('artist_id', 'integer', ['notnull' => false]);
        $tAlbum->setPrimaryKey(['id']);

        $tAlbumSongmap = $schema->createTable('Album_SongMap');
        $tAlbumSongmap->addColumn('album_id', 'integer', ['notnull' => true]);
        $tAlbumSongmap->addColumn('song_id', 'integer', ['notnull' => true]);

        $tArtist = $schema->createTable('ARTIST');
        $tArtist->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tArtist->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tArtist->addColumn('type_id', 'integer', ['notnull' => false]);
        $tArtist->setPrimaryKey(['id']);

        $tArtistType = $schema->createTable('ARTIST_TYPE');
        $tArtistType->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tArtistType->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tArtistType->setPrimaryKey(['id']);

        $tCasesCascadingremove = $schema->createTable('CASES_CASCADINGREMOVE');
        $tCasesCascadingremove->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tCasesCascadingremove->addColumn('subclass_id', 'integer', ['notnull' => true]);
        $tCasesCascadingremove->setPrimaryKey(['id']);

        $tCasesCascadingremoveSubclass = $schema->createTable('CASES_CASCADINGREMOVE_SUBCLASS');
        $tCasesCascadingremoveSubclass->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tCasesCascadingremoveSubclass->setPrimaryKey(['id']);

        $tGenre = $schema->createTable('GENRE');
        $tGenre->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tGenre->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tGenre->setPrimaryKey(['id']);

        $tSong = $schema->createTable('SONG');
        $tSong->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tSong->addColumn('timeCreated', 'datetime', ['notnull' => true]);
        $tSong->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tSong->addColumn('genre_id', 'integer', ['notnull' => true]);
        $tSong->addColumn('artist_id', 'integer', ['notnull' => true]);
        $tSong->addColumn('durationInSeconds', 'integer', ['notnull' => true]);
        $tSong->addColumn('tophit', 'boolean', ['notnull' => true]);
        $tSong->setPrimaryKey(['id']);

        $tAlbum->addForeignKeyConstraint($tArtist, ['artist_id'], ['id'], [], 'FK_Album_artist_id');
        $tAlbumSongmap->addForeignKeyConstraint($tAlbum, ['album_id'], ['id'], [], 'FK_Album_SongMap_album_id');
        $tAlbumSongmap->addForeignKeyConstraint($tSong, ['song_id'], ['id'], [], 'FK_Album_Songmap_song_id');
        $tAlbumSongmap->addUniqueConstraint(['album_id', 'song_id'], 'UK_Album_SongMap');
        $tCasesCascadingremove->addForeignKeyConstraint($tCasesCascadingremoveSubclass, ['subclass_id'], ['id'], [], 'UK_CASES_CASCREM_SUBCLASS_id');
        $tSong->addForeignKeyConstraint($tGenre, ['genre_id'], ['id'], [], 'FK_Song_genre_id');
        $tSong->addForeignKeyConstraint($tArtist, ['artist_id'], ['id'], [], 'FK_Song_artist_id');

        // Execute DDL via PHP connection (autocommit commits each statement).
        $platform     = $connection->getDatabasePlatform();
        $ddlStatements = $schema->toSql($platform);

        foreach ($ddlStatements as $ddlStatement) {
            try {
                $connection->executeStatement($ddlStatement);
            } catch (Throwable $e) {
                // Tolerate "Index already exists" errors (SQLSTATE 42S11).
                // Firebird auto-creates indexes for FK constraints, but Doctrine's
                // Schema::toSql() also generates explicit CREATE INDEX statements.
                if (! str_contains($e->getMessage(), '42S11')) {
                    throw $e;
                }
            }
        }

        // Seed data via PHP connection (autocommit commits each INSERT).
        $seedGroups = [
            'ARTIST_TYPE' => [
                ['name' => 'Unknown'], ['name' => 'Solo'], ['name' => 'Duo'],
                ['name' => 'Trio'], ['name' => 'Quartet'], ['name' => 'Band'],
            ],
            'ARTIST' => [
                ['name' => 'Unknown', 'type_id' => 1],
                ['name' => 'Britney Spears', 'type_id' => 2],
                ['name' => 'Nickelback', 'type_id' => 6],
                ['name' => 'AC/DC', 'type_id' => 6],
            ],
            'GENRE' => [
                ['name' => 'Unclassified genre'], ['name' => 'Rock'],
                ['name' => 'Pop'], ['name' => 'Classical'],
            ],
            'ALBUM' => [
                ['timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'artist_id' => 2],
                ['timeCreated' => '2017-01-01 15:00:00', 'name' => 'Dark Horse', 'artist_id' => 3],
            ],
            'SONG' => [
                ['timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 211, 'tophit' => false],
                ['timeCreated' => '2017-01-01 15:00:00', 'name' => '(You Drive Me) Crazy', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 200, 'tophit' => true],
            ],
            'Album_SongMap' => [
                ['album_id' => 1, 'song_id' => 1],
                ['album_id' => 1, 'song_id' => 2],
            ],
        ];

        foreach ($seedGroups as $table => $rows) {
            foreach ($rows as $row) {
                $columns = [];
                $values  = [];
                foreach ($row as $col => $val) {
                    $columns[] = strtoupper($col);
                    if (is_string($val)) {
                        $values[] = "'" . str_replace("'", "''", $val) . "'";
                    } elseif (is_bool($val)) {
                        $values[] = $val ? 'TRUE' : 'FALSE';
                    } else {
                        $values[] = (string) $val;
                    }
                }

                $connection->executeStatement(
                    'INSERT INTO ' . strtoupper($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')',
                );
            }
        }

        // Verify seed data is visible
        $albumCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM "ALBUM"');
        echo "[DIAG] PHP seeding complete. ALBUM rows: {$albumCount}\n";
        if ($albumCount === 0) {
            throw new \RuntimeException('Seed data verification failed: 0 rows in ALBUM');
        }

        self::$databaseInstalled = true;
    }

    /**
     * Override parent disconnect() to NOT close the shared connection.
     * Integration tests use transaction rollback for test isolation.
     * Closing the connection here would force reconnection on the next test.
     */
    #[After]
    final protected function disconnect(): void
    {
        // No-op: keep TestUtil::$sharedConnection alive for performance
    }

    protected static function statementArrayToText(array $statements): string
    {
        $statements = array_filter($statements, static fn ($statement) => is_string($statement));
        if ($statements !== []) {
            $indent = '    ';
            array_walk($statements, static function (&$v) use ($indent): void {
                $v = $indent . $v;
            });

            return PHP_EOL . implode(PHP_EOL, $statements);
        }

        return '';
    }

    protected static function getSetUpDoctrineConfiguration(Connection $connection): Configuration
    {
        $cache                 = new ArrayAdapter();
        $proxyDir              = ROOT_PATH . '/var/doctrine-proxies';
        $doctrineConfiguration = ORMSetup::createAttributeMetadataConfiguration(
            [ROOT_PATH . '/Test/Resource/Entity'],
            true,
            $proxyDir . '-annotations',
            $cache,
        );
        $doctrineConfiguration->setProxyNamespace('DoctrineFirebirdDriver\Proxies');
        if ($connection->getDatabasePlatform() instanceof Firebird3Platform) {
            $doctrineConfiguration->setIdentityGenerationPreferences([
                FirebirdPlatform::class => ClassMetadata::GENERATOR_TYPE_IDENTITY,
            ]);
        }

        $doctrineConfiguration->setQuoteStrategy(new FirebirdQuoteStrategy());

        return $doctrineConfiguration;
    }

    protected static function getSetUpDoctrineConfigurationArray(array $overrideConfigs = []): array
    {
        $params = TestUtil::getConnectionParams();

        return [
            'host' => $params['host'],
            'dbname' => $params['dbname'],
            'user' => self::DEFAULT_DATABASE_USERNAME,
            'password' => self::DEFAULT_DATABASE_PASSWORD,
            'charset' => 'UTF-8',
            'driverClass' => Driver::class,
        ];
    }

    private function stopIfOver(int $seconds, string $cmd): void
    {
        static $timer;
        if (! $timer) {
            $timer = new Timer();
            $timer->start();
        }

        if (($took = $timer->stop()->asSeconds()) > $seconds) {
            $timer->start();
            // addWarning is removed in PHPUnit 10
            // $this->addWarning("Execution time for $cmd took {$took} seconds exceeding maximum execute Time of  {$seconds} seconds.");
        }

        $timer->start();
    }
}
