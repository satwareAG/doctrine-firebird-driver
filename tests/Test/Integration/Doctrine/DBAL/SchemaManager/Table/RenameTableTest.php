<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\SchemaManager\Table;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

/**
 * 
 */
class RenameTableTest extends AbstractIntegrationTestCase
{
    public function testRenameTable()
    {
        $this->expectExceptionMessage("Operation 'Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform::getRenameTableSQL Cannot rename tables because firebird does not support it");
        $connection = $this->_entityManager->getConnection();
        $sm = $connection->createSchemaManager();
        $sm->renameTable('oldName', 'newName');

    }
}
