<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Ticket;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_change_key_case;
use function array_keys;
use function trim;

use const CASE_LOWER;

/**
 * Regression test for GH-23: Padded alias keys in fetchAssociative.
 *
 * Firebird's fbird_fetch_assoc() returns column names padded with spaces to
 * their declared length. The driver must normalize these keys so that
 * `$row['col']` works instead of `$row['col   ']`.
 *
 * Fixed in commits 9820738 and 6ce5051.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/23
 */
class GH23Test extends FunctionalTestCase
{
    private const TABLE = 'gh23_regression';

    /**
     * Column keys in fetchAssociative must not be padded with trailing spaces.
     */
    public function testFetchAssociativeReturnsUnpaddedKeys(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(50)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
        $this->connection->insert(self::TABLE, ['id' => 1, 'name' => 'test']);

        $row = $this->connection->fetchAssociative(
            'SELECT id, name FROM ' . self::TABLE . ' WHERE id = 1',
        );

        self::assertIsArray($row);

        // Firebird returns uppercase column names — normalise to lowercase for comparison
        $row = array_change_key_case($row, CASE_LOWER);

        // Keys must be exact — no trailing spaces
        self::assertArrayHasKey('id', $row, 'Key "id" must exist without padding');
        self::assertArrayHasKey('name', $row, 'Key "name" must exist without padding');

        // Verify no padded variants exist (after normalisation)
        foreach (array_keys($row) as $key) {
            self::assertSame(trim($key), $key, 'Key \'' . $key . '\' must not have trailing spaces');
        }
    }

    /**
     * Column aliases in SELECT must also be returned without padding.
     */
    public function testFetchAssociativeReturnsUnpaddedAliasKeys(): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT 42 AS my_alias FROM RDB$DATABASE',
        );

        self::assertIsArray($row);

        // Firebird returns uppercase aliases — normalise to lowercase for comparison
        $row = array_change_key_case($row, CASE_LOWER);

        self::assertArrayHasKey('my_alias', $row, 'Alias key must exist without padding');

        foreach (array_keys($row) as $key) {
            self::assertSame(trim($key), $key, 'Alias key \'' . $key . '\' must not have trailing spaces');
        }
    }

    /**
     * fetchAllAssociative must also return unpadded keys for all rows.
     */
    public function testFetchAllAssociativeReturnsUnpaddedKeys(): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('val')->setTypeName(Types::STRING)->setLength(20)->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
        $this->connection->insert(self::TABLE, ['id' => 1, 'val' => 'a']);
        $this->connection->insert(self::TABLE, ['id' => 2, 'val' => 'b']);

        $rows = $this->connection->fetchAllAssociative('SELECT id, val FROM ' . self::TABLE);

        self::assertCount(2, $rows);

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                self::assertSame(trim($key), $key, 'Key \'' . $key . '\' must not have trailing spaces');
            }
        }
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE);

        parent::tearDown();
    }
}
