<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Portability\Driver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;
use Kafoso\DoctrineFirebirdDriver\ORM\Mapping\FirebirdQuoteStrategy;
use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

abstract class AbstractIntegrationTestCase extends TestCase
{
    const DEFAULT_DATABASE_FILE_PATH = '/firebird/data/music_library.fdb';
    const DEFAULT_DATABASE_USERNAME = 'SYSDBA';
    const DEFAULT_DATABASE_PASSWORD = 'masterkey';

    protected $_entityManager;
    protected $_platform;

    public function setUp(): void
    {
        $configurationArray = static::getSetUpDoctrineConfigurationArray();
        static::installFirebirdDatabase($configurationArray);
        $doctrineConfiguration = static::getSetUpDoctrineConfiguration();
        $this->_entityManager = static::createEntityManager($doctrineConfiguration, $configurationArray);

        $this->_platform = $this->_entityManager->getConnection()->getDatabasePlatform();
    }

    public function tearDown(): void
    {
        if ($this->_entityManager) {
            $this->_entityManager->getConnection()->close();
        }
    }

    /**
     * @return EntityManager
     */
    protected static function createEntityManager(Configuration $configuration, array $configurationArray)
    {
        $connection = DriverManager::getConnection($configurationArray, $configuration);
        $connection->setNestTransactionsWithSavepoints(true);
        return new EntityManager($connection, $configuration);
    }

    protected static function installFirebirdDatabase(array $configurationArray)
    {
        $output = $result = '';
        if (file_exists(ROOT_PATH . '/tests/databases/music_library.fdb')) {
            $cmd = sprintf(
                "gfix -user SYSDBA -password masterkey -shut -force 0 firebird:/firebird/data/music_library.fdb 2>&1",
            );
            $ret = exec($cmd, $output, $result);
            $cmd = sprintf(
                "isql-fb -u SYSDBA -p masterkey -input %s 2>&1",
                escapeshellarg(ROOT_PATH . "/tests/resources/database_drop.sql")
            );

            $ret = exec($cmd, $output, $result);
        }

        $cmd = sprintf(
            "isql-fb -input %s 2>&1",
            escapeshellarg(ROOT_PATH . "/tests/resources/database_create.sql")
        );
        exec($cmd);

        $cmd = sprintf(
            "isql-fb %s -input %s -password %s -user %s",
            escapeshellarg($configurationArray['host'] . ':'. $configurationArray['dbname']),
            escapeshellarg(ROOT_PATH . "/tests/resources/database_setup.sql"),
            escapeshellarg((string) $configurationArray['password']),
            escapeshellarg((string) $configurationArray['user'])
        );
        exec($cmd);
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
    protected static function getSetUpDoctrineConfiguration(): Configuration
    {
        $cache = new ArrayAdapter();
        $proxyDir = ROOT_PATH . '/var/doctrine-proxies';
        $doctrineConfiguration = ORMSetup::createAttributeMetadataConfiguration(
            [ROOT_PATH . '/tests/resources/Test/AttributeEntity'],
            true,
            $proxyDir . '-annotations',
            $cache
        );
        $doctrineConfiguration->setProxyNamespace('DoctrineFirebirdDriver\Proxies');
        $doctrineConfiguration->setIdentityGenerationPreferences([
            FirebirdInterbasePlatform::class => ClassMetadata::GENERATOR_TYPE_SEQUENCE
            ]);
        $doctrineConfiguration->setQuoteStrategy(new FirebirdQuoteStrategy());
        /*
        $doctrineConfiguration->setMiddlewares([
            new \Doctrine\DBAL\Portability\Middleware(
                \Doctrine\DBAL\Portability\Connection::PORTABILITY_ALL,
                ColumnCase::UPPER
            )
        ]);
        */
        return $doctrineConfiguration;
    }

    /**
     * @return array
     */
    protected static function getSetUpDoctrineConfigurationArray(array $overrideConfigs = [])
    {
        return [
            'host' => 'firebird',
            'dbname' => static::DEFAULT_DATABASE_FILE_PATH,
            'user' => static::DEFAULT_DATABASE_USERNAME,
            'password' => static::DEFAULT_DATABASE_PASSWORD,
            'charset' => 'UTF-8',
            'driverClass' => FirebirdInterbase\Driver::class
        ];
    }
}
