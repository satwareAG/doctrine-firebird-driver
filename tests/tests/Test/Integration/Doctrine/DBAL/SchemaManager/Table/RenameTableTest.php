<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\SchemaManager\Table;

use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

/**
 * @runTestsInSeparateProcesses
 */
class RenameTableTest extends AbstractIntegrationTestCase
{
    public function testRenameTable()
    {
        $this->expectExceptionMessage("Operation 'Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform::getRenameTableSQL Cannot rename tables because firebird does not support it");
        $connection = $this->_entityManager->getConnection();
        $sm = $connection->createSchemaManager();
        $sm->renameTable('oldName', 'newName');

    }
}