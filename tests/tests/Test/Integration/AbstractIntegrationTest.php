<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\ORMSetup;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;
use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

abstract class AbstractIntegrationTest extends TestCase
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
        $doctrineConnection = new Connection(
            $configurationArray,
            new FirebirdInterbase\Driver,
            $configuration
        );
        $doctrineConnection->setNestTransactionsWithSavepoints(true);
        return EntityManager::create($doctrineConnection, $configuration);
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
            escapeshellarg($configurationArray['password']),
            escapeshellarg($configurationArray['user'])
        );
        exec($cmd);
    }

    /**
     * @return string
     */
    protected static function statementArrayToText(array $statements)
    {
        $statements = array_filter($statements, function($statement){
            return is_string($statement);
        });
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

        $doctrineConfiguration = new Configuration;
        $cache = new ArrayAdapter();
        $config = new \Doctrine\ORM\Configuration();
        $config->setMetadataCache($cache);
        $driverImpl = ORMSetup::createDefaultAnnotationDriver([ROOT_PATH . '/tests/resources/Test/Entity'], $cache);
        $doctrineConfiguration->setMetadataDriverImpl($driverImpl);
        $doctrineConfiguration->setProxyDir(ROOT_PATH . '/var/doctrine-proxies');
        $doctrineConfiguration->setProxyNamespace('DoctrineFirebirdDriver\Proxies');
        $doctrineConfiguration->setAutoGenerateProxyClasses(true);
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
        ];
    }
}
