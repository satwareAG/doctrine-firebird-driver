<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration;

use Satag\DoctrineFirebirdDriver\ORM\FirebirdEntityManager;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Satag\DoctrineFirebirdDriver\Driver\Firebird;
use Satag\DoctrineFirebirdDriver\ORM\EventSubscriber\InsertReturningSubscriber;
use Satag\DoctrineFirebirdDriver\ORM\Mapping\FirebirdQuoteStrategy;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\Functional\TestUtil;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Artist;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;


abstract class AbstractIntegrationTestCase extends FunctionalTestCase
{
    const DEFAULT_DATABASE_FILE_PATH = '/firebird/data/music_library.fdb';
    const DEFAULT_DATABASE_USERNAME = 'SYSDBA';
    const DEFAULT_DATABASE_PASSWORD = 'masterkey';

    protected $_entityManager;
    protected $_platform;

    public function setUp(): void
    {
        $configurationArray = static::getSetUpDoctrineConfigurationArray();
        $this->installFirebirdDatabase($configurationArray);


        $this->connect();
        $doctrineConfiguration = static::getSetUpDoctrineConfiguration($this->connection);
        $this->connection->setNestTransactionsWithSavepoints(true);
        $eventManager = new EventManager();
        if ($this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            $subscriber =  new InsertReturningSubscriber();
            $eventManager->addEventSubscriber($subscriber);


        }


        $this->_entityManager = new FirebirdEntityManager($this->connection, $doctrineConfiguration, $eventManager);


        $this->_platform = $this->_entityManager->getConnection()->getDatabasePlatform();

    }

    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    private function stopIfOver(int $seconds, string $cmd) {
        static $timer;
        if (!$timer) {
            $timer = new Timer();
            $timer->start();
        }


        if (($took = $timer->stop()->asSeconds()) > $seconds) {
            $timer->start();
            $this->addWarning("Execution time for $cmd took {$took} seconds exceeding maximum execute Time of  {$seconds} seconds.");

        }
        $timer->start();

    }
    protected function installFirebirdDatabase(array $configurationArray)
    {
        $this->stopIfOver(999, 'installFirebirdDatabase');
        $sm = $this->connection->createSchemaManager();

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


        $tAlbum->addForeignKeyConstraint($tArtist, ['artist_id'], ['id'], [],'FK_Album_artist_id');
        $tAlbumSongmap->addForeignKeyConstraint($tAlbum, ['album_id'], ['id'], [], 'FK_Album_SongMap_album_id');
        $tAlbumSongmap->addForeignKeyConstraint($tSong, ['song_id'], ['id'], [], 'FK_Album_Songmap_song_id');
        $tAlbumSongmap->addUniqueConstraint(['album_id', 'song_id'],  'UK_Album_SongMap');
        $tCasesCascadingremove->addForeignKeyConstraint($tCasesCascadingremoveSubclass, ['subclass_id'], ['id'], [], 'UK_CASES_CASCREM_SUBCLASS_id');
        $tSong->addForeignKeyConstraint($tGenre, ['genre_id'], ['id'], [], 'FK_Song_genre_id');
        $tSong->addForeignKeyConstraint($tArtist, ['artist_id'], ['id'], [], 'FK_Song_artist_id');

        $queriesRemove = $schema->toDropSql($this->connection->getDatabasePlatform());
        $queriesInsert = $schema->toSql($this->connection->getDatabasePlatform());

        $this->connection->beginTransaction();
        foreach ($queriesRemove as $query) {
            try {
                $this->connection->executeStatement($query);
            } catch (DatabaseObjectNotFoundException $e) {
            } catch (\Exception  $e) {}
        }
        $this->connection->commit();


        $this->connection->beginTransaction();
        foreach ($queriesInsert as $sql) {
            $this->connection->executeStatement($sql);
        }
        $this->connection->commit();

        $this->connection->beginTransaction();
        foreach (['Unknown', 'Solo', 'Duo', 'Trio', 'Quartet', 'Band'] as $id => $name) {
            $this->connection->insert($tArtistType->getName(), ['name' => $name]);
        }

        foreach (['Unknown' => 1, 'Britney Spears' => 2, 'Nickelback' => 6, 'AC/DC' => 6] as $name => $type) {
            $this->connection->insert($tArtist->getName(), ['name' => $name, 'type_id' => $type]);
        }


        foreach (['Unclassified genre', 'Rock', 'Pop', 'Classical'] as $id => $name) {
            $this->connection->insert($tGenre->getName(), ['name' => $name]);
        }

        $this->connection->insert($tAlbum->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'artist_id' => 2]);
        $this->connection->insert($tAlbum->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => 'Dark Horse', 'artist_id' => 3]);

        $this->connection->insert($tSong->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => '...Baby One More Time', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 211, 'tophit' => 0]);
        $this->connection->insert($tSong->getName(), ['timeCreated' => '2017-01-01 15:00:00', 'name' => '(You Drive Me) Crazy', 'genre_id' => 3, 'artist_id' => 2, 'durationInSeconds' => 200, 'tophit' => 1]);

        $this->connection->insert($tAlbumSongmap->getName(), ['album_id' => 1, 'song_id' => 1]);
        $this->connection->insert($tAlbumSongmap->getName(), ['album_id' => 1, 'song_id' => 2]);

        $this->connection->commit();

    }

    /**
     * @return string
     */
    protected static function statementArrayToText(array $statements)
    {
        $statements = array_filter($statements, fn($statement) => is_string($statement));
        if ($statements) {
            $indent = "    ";
            array_walk($statements, function(&$v) use ($indent){
                $v = $indent . $v;
            });
            return PHP_EOL . implode(PHP_EOL, $statements);
        }
        return "";
    }

    /**
     * @return Configuration
     */
    protected static function getSetUpDoctrineConfiguration(Connection $connection): Configuration
    {
        $cache = new ArrayAdapter();
        $proxyDir = ROOT_PATH . '/var/doctrine-proxies';
        $doctrineConfiguration = ORMSetup::createAttributeMetadataConfiguration(
            [ROOT_PATH . '/Test/Resource/Entity'],
            true,
            $proxyDir . '-annotations',
            $cache
        );
        $doctrineConfiguration->setProxyNamespace('DoctrineFirebirdDriver\Proxies');
        if ($connection->getDatabasePlatform() instanceof Firebird3Platform) {
            $doctrineConfiguration->setIdentityGenerationPreferences([
                FirebirdPlatform::class => ClassMetadata::GENERATOR_TYPE_IDENTITY
            ]);
        } else {
            $doctrineConfiguration->setIdentityGenerationPreferences([
                FirebirdPlatform::class => ClassMetadata::GENERATOR_TYPE_IDENTITY
            ]);
        }

        $doctrineConfiguration->setQuoteStrategy(new FirebirdQuoteStrategy());

        return $doctrineConfiguration;
    }

    /**
     * @return array
     */
    protected static function getSetUpDoctrineConfigurationArray(array $overrideConfigs = [])
    {
        $params = TestUtil::getConnectionParams();
        return [
            'host' => $params['host'],
            'dbname' => static::DEFAULT_DATABASE_FILE_PATH,
            'user' => static::DEFAULT_DATABASE_USERNAME,
            'password' => static::DEFAULT_DATABASE_PASSWORD,
            'charset' => 'UTF-8',
            'driverClass' => Firebird\Driver::class
        ];
    }
}
