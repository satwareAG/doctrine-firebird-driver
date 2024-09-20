<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;

class GuidTest extends \Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('guid_table');
        $table->addColumn('guid', Types::GUID);

        $this->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $guid = '7c620eda-ea79-11eb-9a03-0242ac130003';

        $result = $this->connection->insert('guid_table', ['guid' => $guid]);
        self::assertSame(1, $result);

        $value = $this->connection->fetchOne('SELECT guid FROM guid_table');

        // the platforms with native UUID support inconsistently format the binary value
        // as a string using the lower or the upper case; this is acceptable since
        // regardless of the case they encode the same binary value
        self::assertEqualsIgnoringCase($guid, $value);
    }
}
