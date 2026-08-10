<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

#[RequiresPhpExtension('interbase')]
class ConnectionTest extends FunctionalTestCase
{
    /**
     * Tests lastInsertId() with fully-qualified sequence name.
     *
     * Note: HostDbnameRequired exception test is covered by Integration/ConnectionTest
     * to avoid duplicate tests.
     */
    public function testLastInsertIdAcceptsFqn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('DBAL2595')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create(),
                Column::editor()->setUnquotedName('foo')->setTypeName(Types::INTEGER)->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('INSERT INTO DBAL2595 (foo) VALUES (1)');

        $platform = $this->connection->getDatabasePlatform();
        $sequence = $platform->getIdentitySequenceName($table->getName(), 'id');

        // DBAL4: lastInsertId() takes no $name; use driver extension
        $fbConn = $this->getFirebirdConnection();
        self::assertNotNull($fbConn);
        self::assertSame(1, $fbConn->lastInsertIdBySequence($sequence));
    }
}
