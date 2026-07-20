<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PdoMysqlDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as PdoSqliteDriver;
use Doctrine\DBAL\Driver\PgSQL\Driver as PgsqlDriver;
use Doctrine\DBAL\Driver\SQLite3\Driver as Sqlite3Driver;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\TestWith;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

use function strtolower;

use const PHP_VERSION_ID;

class ResultMetadataTest extends FunctionalTestCase
{
    public function testColumnNameWithResults(): void
    {
        // Firebird's Result class does not implement the optional getColumnName()
        // method declared in the driver Result interface. Skip on Firebird.
        if (! TestUtil::isDriverClassOneOf(Sqlite3Driver::class, PdoSqliteDriver::class, PgsqlDriver::class, PdoMysqlDriver::class)) {
            self::markTestSkipped('The Firebird Result does not implement getColumnName().');
        }

        $sql = 'SELECT test_int, test_int AS alternate_name FROM result_metadata_table';

        $result = $this->connection->executeQuery($sql);

        self::assertEquals(2, $result->columnCount());
        // Depending on the platform, field names might have different case than in the SQL
        // query (for instance, Oracle turns unquoted identifiers into upper case).
        self::assertEquals('test_int', strtolower($result->getColumnName(0)));
        self::assertEquals('alternate_name', strtolower($result->getColumnName(1)));
    }

    #[TestWith([2])]
    #[TestWith([-1])]
    public function testColumnNameWithInvalidIndex(int $index): void
    {
        // Firebird's Result class does not implement the optional getColumnName()
        // method declared in the driver Result interface. DBAL's Result wrapper
        // would throw a plain LogicException instead of InvalidColumnIndex. Skip.
        if (! TestUtil::isDriverClassOneOf(Sqlite3Driver::class, PdoSqliteDriver::class, PgsqlDriver::class, PdoMysqlDriver::class)) {
            self::markTestSkipped('The Firebird Result does not implement getColumnName().');
        }

        $sql = 'SELECT test_int, test_int AS alternate_name FROM result_metadata_table';

        $result = $this->connection->executeQuery($sql);

        // Consume the result set to avoid issues with unprocessed buffer between tests
        $result->fetchAllAssociative();

        $this->expectException(InvalidColumnIndex::class);

        $result->getColumnName($index);
    }

    public function testColumnNameAfterFree(): void
    {
        // Whether a freed result reports an invalid column index is driver-specific.
        if (! TestUtil::isDriverClassOneOf(Sqlite3Driver::class, PdoSqliteDriver::class, PgsqlDriver::class, PdoMysqlDriver::class)) {
            self::markTestSkipped('This driver does not report an invalid column index for a freed result.');
        }

        // pdo_sqlite's getColumnMeta() returns false for a freed statement only since the fix
        // for https://github.com/php/php-src/issues/17837, shipped in PHP 8.3.18 and 8.4.5.
        if (
            TestUtil::isDriverClassOneOf(PdoSqliteDriver::class)
            && (PHP_VERSION_ID < 80318 || (PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80405))
        ) {
            self::markTestSkipped('pdo_sqlite reports getColumnMeta() failure only since PHP 8.3.18/8.4.5.');
        }

        $result = $this->getFreedResult();

        $this->expectException(InvalidColumnIndex::class);

        $result->getColumnName(0);
    }

    public function testColumnCountAfterFree(): void
    {
        // Whether a freed result reports zero columns is driver-specific.
        // Firebird's Result::free() nulls the result resource, after which
        // columnCount() returns 0 (matches the behaviour of sqlite3 and pgsql).
        if (! TestUtil::isDriverClassOneOf(Sqlite3Driver::class, PgsqlDriver::class, Driver::class)) {
            self::markTestSkipped('This driver does not report zero columns for a freed result.');
        }

        $result = $this->getFreedResult();

        self::assertSame(0, $result->columnCount());
    }

    public function testColumnNameWithoutResults(): void
    {
        // Firebird's Result class does not implement the optional getColumnName()
        // method declared in the driver Result interface. Skip on Firebird.
        if (! TestUtil::isDriverClassOneOf(Sqlite3Driver::class, PdoSqliteDriver::class, PgsqlDriver::class, PdoMysqlDriver::class)) {
            self::markTestSkipped('The Firebird Result does not implement getColumnName().');
        }

        $sql = 'SELECT test_int, test_int AS alternate_name FROM result_metadata_table WHERE 1 = 0';

        $result = $this->connection->executeQuery($sql);

        self::assertEquals(2, $result->columnCount());
        self::assertEquals('test_int', strtolower($result->getColumnName(0)));
        self::assertEquals('alternate_name', strtolower($result->getColumnName(1)));
    }

    protected function setUp(): void
    {
        $table = Table::editor()
            ->setUnquotedName('result_metadata_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test_int')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('test_int')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->insert('result_metadata_table', ['test_int' => 1]);
    }

    private function getFreedResult(): Result
    {
        $result = $this->connection->executeQuery(
            $this->connection->getDatabasePlatform()
                ->getDummySelectSQL(),
        );

        $result->free();

        return $result;
    }
}
