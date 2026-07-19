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
        // data already exists with the correct count AND the junction table
        // is consistent, skip re-installation.
        try {
            $albumCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM "ALBUM"');
            $songMapCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM "Album_SongMap"');
            if ($albumCount === 2 && $songMapCount === 2) {
                self::$databaseInstalled = true;

                return;
            }
        } catch (Throwable) {
            // Table doesn't exist yet - proceed with installation
        }

        // Clean existing objects via PHP connection (autocommit commits each statement).
        // Uses EXECUTE BLOCK with WHEN ANY DO so individual failures don't abort.
        // Each block must be executed separately - Firebird executes one statement per call.
        $cleanupBlocks = [
            "EXECUTE BLOCK AS\n"
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
            . "END",
            "EXECUTE BLOCK AS\n"
            . "  DECLARE tname VARCHAR(63);\n"
            . "BEGIN\n"
            . "  FOR SELECT RDB\$RELATION_NAME FROM RDB\$RELATIONS\n"
            . "      WHERE RDB\$SYSTEM_FLAG = 0 AND RDB\$VIEW_BLR IS NULL\n"
            . "      INTO :tname DO\n"
            . "  BEGIN\n"
            . "    EXECUTE STATEMENT 'DROP TABLE \"' || TRIM(:tname) || '\"';\n"
            . "    WHEN ANY DO BEGIN /* ignore */ END\n"
            . "  END\n"
            . "END",
            "EXECUTE BLOCK AS\n"
            . "  DECLARE gname VARCHAR(63);\n"
            . "BEGIN\n"
            . "  FOR SELECT RDB\$GENERATOR_NAME FROM RDB\$GENERATORS\n"
            . "      WHERE RDB\$SYSTEM_FLAG = 0\n"
            . "      INTO :gname DO\n"
            . "  BEGIN\n"
            . "    EXECUTE STATEMENT 'DROP SEQUENCE \"' || TRIM(:gname) || '\"';\n"
            . "    WHEN ANY DO BEGIN /* ignore */ END\n"
            . "  END\n"
            . "END",
        ];

        foreach ($cleanupBlocks as $block) {
            try {
                $connection->executeStatement($block);
            } catch (Throwable) {
                // Cleanup errors are non-fatal (tables may not exist yet)
            }
        }

        // Explicitly commit any pending DDL from EXECUTE STATEMENT inside blocks.
        // Firebird 3.0 requires explicit commit for DDL executed via EXECUTE STATEMENT.
        try {
            if ($connection->isTransactionActive()) {
                $connection->commit();
            }
        } catch (Throwable) {
        }

        // Clear data from seed tables (child-first to respect FK constraints)
        // before DDL loop. This ensures the seed UPDATE OR INSERT doesn't
        // conflict with stale data from previous Integration-Write test runs.
        $clearTableOrder = ['ALBUM_SONGMAP', 'SONG', 'ALBUM', 'GENRE', 'ARTIST', 'ARTIST_TYPE', 'CASES_CASCADINGREMOVE_SUBCLASS', 'CASES_CASCADINGREMOVE'];
        foreach ($clearTableOrder as $tableName) {
            try {
                $connection->executeStatement('DELETE FROM ' . $tableName);
            } catch (Throwable) {
                // Table may not exist yet - ignore
            }
        }

        // Commit the deletes
        try {
            if ($connection->isTransactionActive()) {
                $connection->commit();
            }
        } catch (Throwable) {
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

        $tAlbum->addForeignKeyConstraint($tArtist->getName(), ['artist_id'], ['id'], [], 'FK_Album_artist_id');
        $tAlbumSongmap->addForeignKeyConstraint($tAlbum->getName(), ['album_id'], ['id'], [], 'FK_Album_SongMap_album_id');
        $tAlbumSongmap->addForeignKeyConstraint($tSong->getName(), ['song_id'], ['id'], [], 'FK_Album_Songmap_song_id');
        $tAlbumSongmap->addUniqueConstraint(['album_id', 'song_id'], 'UK_Album_SongMap');
        $tCasesCascadingremove->addForeignKeyConstraint($tCasesCascadingremoveSubclass->getName(), ['subclass_id'], ['id'], [], 'UK_CASES_CASCREM_SUBCLASS_id');
        $tSong->addForeignKeyConstraint($tGenre->getName(), ['genre_id'], ['id'], [], 'FK_Song_genre_id');
        $tSong->addForeignKeyConstraint($tArtist->getName(), ['artist_id'], ['id'], [], 'FK_Song_artist_id');

        // Execute DDL via PHP connection (autocommit commits each statement).
        $platform     = $connection->getDatabasePlatform();
        $ddlStatements = $schema->toSql($platform);

        foreach ($ddlStatements as $ddlStatement) {
            try {
                $connection->executeStatement($ddlStatement);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                // Tolerate "Index already exists" (SQLSTATE 42S11) and
                // "Table already exists" — can happen when the coverage step
                // runs all suites in one process and functional tests created
                // the same tables before the integration suite.
                if (! str_contains($msg, '42S11') && ! str_contains($msg, 'already exists') && ! str_contains($msg, 'already defined')) {
                    throw $e;
                }
            }
        }

        // Seed data via PHP connection (autocommit commits each statement).
        // Use UPDATE OR INSERT with explicit IDs so seeding works even if
        // tables survived from a previous run (tables may have stale data
        // with different autoincrement IDs).
        $seedGroups = [
            'ARTIST_TYPE' => [
                ['id' => 1, 'name' => 'Unknown'], ['id' => 2, 'name' => 'Solo'], ['id' => 3, 'name' => 'Duo'],
                ['id' => 4, 'name' => 'Trio'], ['id' => 5, 'name' => 'Quartet'], ['id' => 6, 'name' => 'Band'],
            ],
            'ARTIST' => [
                ['id' => 1, 'name' => 'Unknown', 'type_id' => 1],
                ['id' => 2, 'name' => 'Britney Spears', 'type_id' => 2],
                ['id' => 3, 'name' => 'Nickelback', 'type_id' => 6],
                ['id' => 4, 'name' => 'AC/DC', 'type_id' => 6],
            ],
            'GENRE' => [
                ['id' => 1, 'name' => 'Unclassified genre'], ['id' => 2, 'name' => 'Rock'],
                ['id' => 3, 'name' => 'Pop'], ['id' => 4, 'name' => 'Classical'],
            ],
            'ALBUM' => [
                ['id' => 1, 'timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'artist_id' => 2],
                ['id' => 2, 'timeCreated' => '2017-01-01 15:00:00', 'name' => 'Dark Horse', 'artist_id' => 3],
            ],
            'SONG' => [
                ['id' => 1, 'timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 211, 'tophit' => false],
                ['id' => 2, 'timeCreated' => '2017-01-01 15:00:00', 'name' => '(You Drive Me) Crazy', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 200, 'tophit' => true],
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
                $pkCols  = [];
                foreach ($row as $col => $val) {
                    $columns[] = strtoupper($col);
                    if (is_string($val)) {
                        $values[] = "'" . str_replace("'", "''", $val) . "'";
                    } elseif (is_bool($val)) {
                        $values[] = $val ? 'TRUE' : 'FALSE';
                    } else {
                        $values[] = (string) $val;
                    }
                    // Use 'id' as the MATCHING key for tables with autoincrement PK
                    // Use both 'album_id' and 'song_id' for the junction table
                    if ($col === 'id' || $col === 'album_id' || $col === 'song_id') {
                        $pkCols[] = strtoupper($col);
                    }
                }

                // Guard: UPDATE OR INSERT requires a non-empty MATCHING clause
                if ($pkCols === []) {
                    throw new \RuntimeException(
                        sprintf('Seed table "%s" has no PK column (id/album_id/song_id) for MATCHING clause', $table),
                    );
                }

                $sql = 'UPDATE OR INSERT INTO ' . strtoupper($table)
                    . ' (' . implode(', ', $columns) . ')'
                    . ' VALUES (' . implode(', ', $values) . ')'
                    . ' MATCHING (' . implode(', ', $pkCols) . ')';

                try {
                    $connection->executeStatement($sql);
                } catch (Throwable) {
                    // Ignore seed conflicts - tables may be in an inconsistent state
                }
            }
        }

        // Verify seed data is visible
        $albumCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM "ALBUM"');
        if ($albumCount === 0) {
            throw new \RuntimeException('Seed data verification failed: 0 rows in ALBUM');
        }

        // Advance identity generators to match seed data IDs.
        // UPDATE OR INSERT with explicit IDs doesn't advance identity generators,
        // and SET GENERATOR is silently ignored on identity column generators.
        // ALTER TABLE ... ALTER COLUMN ... RESTART WITH is the correct Firebird 3.0+ mechanism.
        // Values are set to max_seed_id + 1 so the next INSERT gets a non-conflicting ID.
        $identityResets = [
            'ARTIST_TYPE' => 7,
            'ARTIST'      => 5,
            'GENRE'       => 5,
            'ALBUM'       => 3,
            'SONG'        => 3,
        ];
        foreach ($identityResets as $table => $nextVal) {
            try {
                $connection->executeStatement(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "ID" RESTART WITH %d',
                    $table,
                    $nextVal,
                ));
            } catch (Throwable) {
                // Table/column may not exist yet
            }
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
