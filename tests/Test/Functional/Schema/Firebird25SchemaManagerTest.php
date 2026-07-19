<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

use function assert;

class Firebird25SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * Skip testListDatabases for Firebird in CI environments.
     *
     * Creating databases via fbird_query(FBIRD_CREATE, "CREATE DATABASE...")
     * requires server-side filesystem access. In Docker CI environments,
     * the Firebird server container has a different filesystem than the
     * PHP test container, so database files cannot be created in paths
     * relative to the test database location.
     */
    public function testListDatabases(): void
    {
        self::markTestSkipped(
            'Firebird CREATE DATABASE requires server-side filesystem access, ' .
            'which is not available in containerized CI environments.',
        );
    }

    public function testGetBooleanColumn(): void
    {
        $table = new Table('boolean_column_test');
        $table->addColumn('bool', Types::BOOLEAN);
        $table->addColumn('bool_commented', Types::BOOLEAN, ['comment' => "That's a comment"]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('boolean_column_test');

        self::assertInstanceOf(BooleanType::class, $columns['bool']->getType());
        self::assertInstanceOf(BooleanType::class, $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }

    public function testGetBooleanAsCharColumn(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        assert($platform instanceof FirebirdPlatform);
        $platform->setUseSmallIntBoolean(false);

        $table = new Table('boolean_column_as_char_test');
        $table->addColumn('bool', Types::BOOLEAN);
        $table->addColumn('bool_commented', Types::BOOLEAN, ['comment' => "That's a comment"]);

        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('boolean_column_as_char_test');

        self::assertInstanceOf(BooleanType::class, $columns['bool']->getType());
        self::assertInstanceOf(BooleanType::class, $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }

    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return ! ($platform instanceof Firebird3Platform);
    }
}
