<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform\LockMode;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function array_change_key_case;
use function str_contains;

use const CASE_LOWER;

/**
 * Confirms LockMode::NONE produces valid SQL and executes without error on all Firebird versions.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/63
 */
class NoneTest extends FunctionalTestCase
{
    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testLockModeNoneDoesNotThrow(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $sql      = $platform->appendLockHint('lock_none_test', LockMode::NONE);
        $result   = $this->connection->fetchAllAssociative('SELECT id, val FROM ' . $sql);

        self::assertNotEmpty($result);
        // Firebird returns column names in uppercase; use array_change_key_case for portability
        $row = array_change_key_case($result[0], CASE_LOWER);
        self::assertSame(1, (int) $row['id']);
    }

    public function testLockModeNoneGeneratesNoLockClause(): void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $fromClause = $platform->appendLockHint('lock_none_test', LockMode::NONE);

        // LockMode::NONE must not add any lock hint to the FROM clause
        self::assertFalse(str_contains($fromClause, 'WITH LOCK'));
        self::assertFalse(str_contains($fromClause, 'FOR UPDATE'));
        self::assertFalse(str_contains($fromClause, 'LOCK IN SHARE'));
    }

    public function testLockModeNoneCompatibleWithAllFirebirdVersions(): void
    {
        // This test runs on all Firebird versions (no version skip)
        $platform   = $this->connection->getDatabasePlatform();
        $fromClause = $platform->appendLockHint('lock_none_test', LockMode::NONE);

        // Should return the original FROM clause unchanged
        self::assertSame('lock_none_test', $fromClause);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table('lock_none_test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('val', Types::STRING, ['length' => 50]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->insert('lock_none_test', ['id' => 1, 'val' => 'test']);
    }
}
