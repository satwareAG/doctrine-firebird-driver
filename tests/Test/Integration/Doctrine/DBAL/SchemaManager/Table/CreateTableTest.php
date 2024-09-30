<?php

declare(strict_types=1);

namespace Test\Integration\Doctrine\DBAL\SchemaManager\Table;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\StringType;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

use function md5;
use function strtoupper;
use function substr;

class CreateTableTest extends AbstractIntegrationTestCase
{
    public function testCreateTable(): void
    {
        $connection = $this->_entityManager->getConnection();
        $sm         = $connection->getSchemaManager();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $table      = new Table($tableName);
        $table->addColumn('foo', 'string', ['notnull' => false, 'length' => 255]);
        $sm->createTable($table);
        self::assertTrue($sm->tablesExist([$tableName]));
        $foundColumns = $sm->listTableColumns($tableName);
        self::assertCount(1, $foundColumns);
        self::assertArrayHasKey('foo', $foundColumns);
        $foundColumn = $foundColumns['foo'];
        self::assertSame('foo', $foundColumn->getName(), 'Invalid name');
        self::assertInstanceOf(StringType::class, $foundColumn->getType(), 'Invalid type');
        self::assertSame(255, $foundColumn->getLength(), 'Invalid length');
        self::assertSame(10, $foundColumn->getPrecision(), 'Invalid precision');
        self::assertSame(0, $foundColumn->getScale(), 'Invalid scale');
        self::assertFalse($foundColumn->getUnsigned(), 'Invalid unsigned');
        self::assertFalse($foundColumn->getFixed(), 'Invalid fixed');
        self::assertFalse($foundColumn->getNotnull(), 'Invalid notnull');
        self::assertNull($foundColumn->getDefault(), 'Invalid default');
        self::assertFalse($foundColumn->getAutoincrement(), 'Invalid autoincrement');
    }
}
