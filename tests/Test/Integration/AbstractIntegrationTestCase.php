<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Satag\DoctrineFirebirdDriver\Driver\Firebird;
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
     * Install database schema ONCE per test class (not per test method).
     * PERFORMANCE OPTIMIZATION: Avoids reinstalling 8 tables + 6 inserts per test.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = TestUtil::getConnection();
        static::installFirebirdDatabase($connection, []);
        $connection->close();
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
        $this->connection->beginTransaction();
    }

    protected function setUpEntityManager(): void
    {
        $doctrineConfiguration = static::getSetUpDoctrineConfiguration($this->connection);
        $this->connection->setNestTransactionsWithSavepoints(true);
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

    protected static function installFirebirdDatabase(Connection $connection, array $configurationArray, ?self $testCase = null): void
    {
        if ($testCase !== null) {
            $testCase->stopIfOver(999, 'installFirebirdDatabase');
        }
        $connection->createSchemaManager();

        $schema = new Schema();
        $tAlbum = $schema->createTable('Album');
        $tAlbum->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tAlbum->addColumn('timeCreated', 'datetime', ['notnull' => true]);
        $tAlbum->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tAlbum->addColumn('artist_id', 'integer', ['notnull' => false]);
        $tAlbum->setPrimaryKey(['id']);

        $tAlbumSongmap = $schema->createTable('Album_Songmap');
        $tAlbumSongmap->addColumn('album_id', 'integer', ['notnull' => true]);
        $tAlbumSongmap->addColumn('song_id', 'integer', ['notnull' => true]);

        $tArtist = $schema->createTable('Artist');
        $tArtist->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tArtist->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tArtist->addColumn('type_id', 'integer', ['notnull' => false]);
        $tArtist->setPrimaryKey(['id']);

        $tArtistType = $schema->createTable('Artist_Type');
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

        $tGenre = $schema->createTable('Genre');
        $tGenre->addColumn('id', 'integer', ['notnull' => true, 'autoincrement' => true]);
        $tGenre->addColumn('name', 'string', ['notnull' => true, 'length' => 255]);
        $tGenre->setPrimaryKey(['id']);

        $tSong = $schema->createTable('Song');
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

        $platform = $connection->getDatabasePlatform();
        $schemaManager = $connection->createSchemaManager();
        $tablesToDrop = [];
        foreach ($schema->getTables() as $table) {
            if ($schemaManager->tablesExist([$table->getName()])) {
                $tablesToDrop[] = $table;
            }
        }
        $schemaToDrop = new Schema($tablesToDrop);

        $queriesRemove = $schemaToDrop->toDropSql($platform);
        $queriesInsert = $schema->toSql($platform);

        foreach ($queriesRemove as $query) {
            $connection->beginTransaction();
            try {
                $connection->executeStatement($query);
                $connection->commit();
            } catch (DatabaseObjectNotFoundException | Throwable) {
                try {
                    $connection->rollBack();
                } catch (Throwable) {
                }
            }
        }

        foreach ($queriesInsert as $sql) {
            $connection->beginTransaction();
            try {
                $connection->executeStatement($sql);
                $connection->commit();
            } catch (Throwable $e) {
                try {
                    $connection->rollBack();
                } catch (Throwable) {
                }
                throw $e;
            }
        }

        $connection->beginTransaction();
        foreach (['Unknown', 'Solo', 'Duo', 'Trio', 'Quartet', 'Band'] as $name) {
            $connection->insert($tArtistType->getName(), ['name' => $name]);
        }

        foreach (['Unknown' => 1, 'Britney Spears' => 2, 'Nickelback' => 6, 'AC/DC' => 6] as $name => $type) {
            $connection->insert($tArtist->getName(), ['name' => $name, 'type_id' => $type]);
        }

        foreach (['Unclassified genre', 'Rock', 'Pop', 'Classical'] as $name) {
            $connection->insert($tGenre->getName(), ['name' => $name]);
        }

        $connection->insert($tAlbum->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'artist_id' => 2]);
        $connection->insert($tAlbum->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => 'Dark Horse', 'artist_id' => 3]);

        $connection->insert($tSong->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 211, 'tophit' => 0]);
        $connection->insert($tSong->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => '(You Drive Me) Crazy', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 200, 'tophit' => 1]);

        $connection->insert($tAlbumSongmap->getName(), ['album_id' => 1, 'song_id' => 1]);
        $connection->insert($tAlbumSongmap->getName(), ['album_id' => 1, 'song_id' => 2]);

        $connection->commit();
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
            'dbname' => self::DEFAULT_DATABASE_FILE_PATH,
            'user' => self::DEFAULT_DATABASE_USERNAME,
            'password' => self::DEFAULT_DATABASE_PASSWORD,
            'charset' => 'UTF-8',
            'driverClass' => Firebird\Driver::class,
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
