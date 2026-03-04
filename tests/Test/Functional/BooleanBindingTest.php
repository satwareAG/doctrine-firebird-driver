<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Tests PHP true/false binding as statement parameters with Firebird BOOLEAN columns.
 *
 * Validates that FirebirdBooleanType correctly handles:
 * - Binding PHP true/false as INSERT parameters
 * - Reading back boolean values from SELECT
 * - Using boolean params in WHERE clauses
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/61
 */
class BooleanBindingTest extends FunctionalTestCase
{
    private const TABLE = 'bool_binding_test';

    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('flag', Types::BOOLEAN);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);
    }

    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testBindTrueAsParameter(): void
    {
        $this->connection->insert(self::TABLE, ['id' => 1, 'flag' => true], [
            'id'   => ParameterType::INTEGER,
            'flag' => ParameterType::BOOLEAN,
        ]);

        $result = $this->connection->fetchOne(
            'SELECT flag FROM ' . self::TABLE . ' WHERE id = 1',
        );

        self::assertTrue($this->connection->convertToPHPValue($result, Types::BOOLEAN));
    }

    public function testBindFalseAsParameter(): void
    {
        $this->connection->insert(self::TABLE, ['id' => 2, 'flag' => false], [
            'id'   => ParameterType::INTEGER,
            'flag' => ParameterType::BOOLEAN,
        ]);

        $result = $this->connection->fetchOne(
            'SELECT flag FROM ' . self::TABLE . ' WHERE id = 2',
        );

        self::assertFalse($this->connection->convertToPHPValue($result, Types::BOOLEAN));
    }

    public function testBindBooleanInWhereClause(): void
    {
        $this->connection->insert(self::TABLE, ['id' => 1, 'flag' => true], [
            'id'   => ParameterType::INTEGER,
            'flag' => ParameterType::BOOLEAN,
        ]);
        $this->connection->insert(self::TABLE, ['id' => 2, 'flag' => false], [
            'id'   => ParameterType::INTEGER,
            'flag' => ParameterType::BOOLEAN,
        ]);
        $this->connection->insert(self::TABLE, ['id' => 3, 'flag' => true], [
            'id'   => ParameterType::INTEGER,
            'flag' => ParameterType::BOOLEAN,
        ]);

        // Use parameterized queries to avoid SQL injection and type conversion issues.
        // Direct string interpolation of convertBooleans() result fails for native
        // BOOLEAN columns (Firebird 3+) because PHP bool true becomes string "1".
        $trueCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE flag = ?',
            [true],
            [ParameterType::BOOLEAN],
        );
        self::assertSame(2, (int) $trueCount);

        $falseCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE flag = ?',
            [false],
            [ParameterType::BOOLEAN],
        );
        self::assertSame(1, (int) $falseCount);
    }

    public function testBooleanRoundTripViaPreparedStatement(): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (id, flag) VALUES (?, ?)',
        );

        $stmt->bindValue(1, 10, ParameterType::INTEGER);
        $stmt->bindValue(2, true, ParameterType::BOOLEAN);
        $stmt->executeStatement();

        $stmt->bindValue(1, 11, ParameterType::INTEGER);
        $stmt->bindValue(2, false, ParameterType::BOOLEAN);
        $stmt->executeStatement();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, flag FROM ' . self::TABLE . ' WHERE id IN (10, 11) ORDER BY id',
        );

        self::assertCount(2, $rows);

        // Firebird returns column names in uppercase; normalize for portability
        $row0 = array_change_key_case($rows[0], CASE_LOWER);
        $row1 = array_change_key_case($rows[1], CASE_LOWER);

        // Use convertToPHPValue (not convertFromBoolean) to handle raw DB values
        // Firebird may return "1"/"0" or true/false depending on driver version
        self::assertTrue($this->connection->convertToPHPValue($row0['flag'], Types::BOOLEAN));
        self::assertFalse($this->connection->convertToPHPValue($row1['flag'], Types::BOOLEAN));
    }
}
