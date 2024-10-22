<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

class AutoIncrementColumnTest extends FunctionalTestCase
{
    private bool $shouldDisableIdentityInsert = false;

    public function testInsertIdentityValue(): void
    {
        $this->connection->insert('auto_increment_table', ['id' => 2]);
        self::assertSame(2, $this->connection->fetchOne('SELECT id FROM auto_increment_table'));
    }

    protected function setUp(): void
    {
        $table = new Table('auto_increment_table');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        if (! $this->shouldDisableIdentityInsert) {
            return;
        }

        $this->connection->executeStatement('SET IDENTITY_INSERT auto_increment_table OFF');
    }
}
