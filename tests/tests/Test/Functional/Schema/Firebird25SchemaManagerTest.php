<?php

namespace Kafoso\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\Types;
use Kafoso\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Kafoso\DoctrineFirebirdDriver\Platforms\FirebirdInterbasePlatform;
use Kafoso\DoctrineFirebirdDriver\Schema\FirebirdInterbaseSchemaManager;


class Firebird25SchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return !($platform instanceof Firebird3Platform);
    }

    public function testGetBooleanColumn(): void
    {
        $table = new Table('boolean_column_test');
        $table->addColumn('bool', Types::BOOLEAN);
        $table->addColumn('bool_commented', Types::BOOLEAN, ['comment' => "That's a comment"]);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('boolean_column_test');

        self::assertInstanceOf(BooleanType::class, $columns['bool']->getType());
        self::assertInstanceOf(BooleanType::class, $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }

    public function testGetBooleanAsCharColumn(): void
    {
        /**
         * @var $platform FirebirdInterbasePlatform
         */
        $platform = $this->connection->getDatabasePlatform();
        $platform->setUseSmallIntBoolean(false);

        $table = new Table('boolean_column_as_char_test');
        $table->addColumn('bool', Types::BOOLEAN);
        $table->addColumn('bool_commented', Types::BOOLEAN, ['comment' => "That's a comment"]);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('boolean_column_as_char_test');

        self::assertInstanceOf(BooleanType::class, $columns['bool']->getType());
        self::assertInstanceOf(BooleanType::class, $columns['bool_commented']->getType());

        self::assertNull($columns['bool']->getComment());
        self::assertSame("That's a comment", $columns['bool_commented']->getComment());
    }


}
