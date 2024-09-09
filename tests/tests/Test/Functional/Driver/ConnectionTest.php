<?php

namespace Kafoso\DoctrineFirebirdDriver\Test\Functional\Driver;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Driver;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception\HostDbnameRequired;
use Kafoso\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;


/**
 * @requires extension interbase
 * @ runTestsInSeparateProcesses
 * */
class ConnectionTest extends FunctionalTestCase
{
    public function testHostnameDbNameIsRequired(): void
    {
        $this->expectException(HostDbnameRequired::class);
        (new Driver())->connect(['persistent' => 'true']);
    }

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
