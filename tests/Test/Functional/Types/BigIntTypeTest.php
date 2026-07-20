<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Types;

use Doctrine\DBAL\Driver\Mysqli\Driver as MysqliDriver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PdoMysqlDriver;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const PHP_INT_SIZE;

class BigIntTypeTest extends FunctionalTestCase
{
    #[DataProvider('provideBigIntLiterals')]
    public function testSelectBigInt(string $sqlLiteral, int|string|null $expectedValue): void
    {
        $table = Table::editor()
            ->setUnquotedName('bigint_type_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::SMALLINT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('my_integer')
                    ->setTypeName(Types::BIGINT)
                    ->setNotNull(false)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement(<<<SQL
            INSERT INTO bigint_type_test (id, my_integer)
            VALUES (42, $sqlLiteral)
            SQL);

        self::assertSame(
            $expectedValue,
            $this->connection->convertToPHPValue(
                $this->connection->fetchOne('SELECT my_integer from bigint_type_test WHERE id = 42'),
                Types::BIGINT,
            ),
        );
    }

    public function testUnsignedBigIntOnMySQL(): void
    {
        // Adapted for the Firebird driver: the original DBAL check uses
        // TestUtil::isDriverOneOf('mysqli', 'pdo_mysql'). The Firebird TestUtil
        // exposes isDriverClassOneOf() which compares against the configured
        // driver class. On the Firebird driver, this check is always false and
        // the test is skipped (it is MySQL/MariaDB-specific).
        if (! TestUtil::isDriverClassOneOf(MysqliDriver::class, PdoMysqlDriver::class)) {
            self::markTestSkipped('This test only works on MySQL/MariaDB.');
        }

        $table = Table::editor()
            ->setUnquotedName('bigint_type_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::SMALLINT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('my_integer')
                    ->setTypeName(Types::BIGINT)
                    ->setNotNull(false)
                    ->setUnsigned(true)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        // Insert (2 ** 64) - 1
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO bigint_type_test (id, my_integer)
            VALUES (42, 0xFFFFFFFFFFFFFFFF)
            SQL);

        self::assertSame(
            '18446744073709551615',
            $this->connection->convertToPHPValue(
                $this->connection->fetchOne('SELECT my_integer from bigint_type_test WHERE id = 42'),
                Types::BIGINT,
            ),
        );
    }

    /** @return Generator<string, array{string, int|string|null}> */
    public static function provideBigIntLiterals(): Generator
    {
        yield 'zero' => ['0', 0];
        yield 'null' => ['null', null];
        yield 'positive number' => ['42', 42];
        yield 'negative number' => ['-42', -42];
        yield 'large positive number' => [PHP_INT_SIZE === 4 ? '2147483646' : '9223372036854775806', PHP_INT_MAX - 1];
        yield 'large negative number' => [PHP_INT_SIZE === 4 ? '-2147483647' : '-9223372036854775807', PHP_INT_MIN + 1];
        yield 'largest positive number' => [PHP_INT_SIZE === 4 ? '2147483647' : '9223372036854775807', PHP_INT_MAX];
        yield 'largest negative number' => [PHP_INT_SIZE === 4 ? '-2147483648' : '-9223372036854775808', PHP_INT_MIN];
    }
}
