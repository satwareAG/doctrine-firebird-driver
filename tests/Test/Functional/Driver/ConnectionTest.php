<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * @requires extension interbase
 */
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
        $table = new Table('DBAL2595');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('INSERT INTO DBAL2595 (foo) VALUES (1)');

        $platform = $this->connection->getDatabasePlatform();
        $sequence = $platform->getIdentitySequenceName($table->getName(), 'id');

        self::assertSame(1, $this->connection->lastInsertId($sequence));
    }
}
