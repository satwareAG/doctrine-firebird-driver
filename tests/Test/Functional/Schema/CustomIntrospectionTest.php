<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Satag\DoctrineFirebirdDriver\Test\Functional\Schema\Types\MoneyType;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_map;
use function implode;
use function sprintf;
use function uniqid;

/**
 * Tests introspection of a custom column type with an underlying decimal column
 * on Firebird Platforms
 */
class CustomIntrospectionTest extends FunctionalTestCase
{
    private string $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->table = 'test_c_int_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();

        parent::tearDown();
    }

    public function testCustomColumnIntrospection(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schema        = new Schema([], [], $schemaManager->createSchemaConfig());
        $table         = $schema->createTable($this->table);

        $table->addColumn('id', 'integer');
        $table->addColumn('quantity', 'decimal');
        $table->addColumn('amount', 'money', [
            'notnull' => false,
            'scale' => 2,
            'precision' => 10,
        ]);

        $this->dropAndCreateTable($table);

        $onlineTable = $schemaManager->introspectTable($this->table);
        // Online table will have the unique name, so we compare against the table we created (which also has unique name)
        $diff        = $schemaManager->createComparator()->compareTables($onlineTable, $table);
        $changedCols = array_map(
            static function (ColumnDiff $columnDiff): string|null {
                $column = $columnDiff->getOldColumn();

                return $column?->getName();
            },
            $diff->getModifiedColumns(),
        );

        self::assertTrue($diff->isEmpty(), sprintf(
            'Tables should be identical. Differences detected in %s.',
            implode(', ', $changedCols),
        ));
    }

    public static function setUpBeforeClass(): void
    {
        Type::addType('money', MoneyType::class);
    }
}
