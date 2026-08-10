<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
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
        $table = Table::editor()
            ->setUnquotedName('auto_increment_table')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        if ($this->shouldDisableIdentityInsert) {
            $this->connection->executeStatement('SET IDENTITY_INSERT auto_increment_table OFF');
        }

        $this->markConnectionNotReusable();
    }
}
