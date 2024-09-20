<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Query;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDb1010Platform;
use Doctrine\DBAL\Platforms\MariaDb1060Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\Functional\TestUtil;


final class QueryBuilderTest extends \Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('for_update');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('for_update', ['id' => 1]);
        $this->connection->insert('for_update', ['id' => 2]);
    }

    protected function tearDown(): void
    {
        if (! $this->connection->isTransactionActive()) {
            return;
        }

        $this->connection->rollBack();
    }

    public function testForUpdateOrdinary(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->forUpdate();

        self::assertEquals([1, 2], $qb1->fetchFirstColumn());
    }

    public function testForUpdateSkipLockedWhenSupported(): void
    {
        if (! $this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform does not support SKIP LOCKED.');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->where('id = 1')
            ->forUpdate();

        $this->connection->beginTransaction();

        self::assertEquals([1], $qb1->fetchFirstColumn());

        $params = \Satag\DoctrineFirebirdDriver\Test\TestUtil::getConnectionParams();

        $connection2 = DriverManager::getConnection($params);

        $qb2 = $connection2->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update')
            ->orderBy('id')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::assertEquals([2], $qb2->fetchFirstColumn());
    }

    public function testForUpdateSkipLockedWhenNotSupported(): void
    {
        if ($this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform supports SKIP LOCKED.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('for_update')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::expectException(Exception::class);
        $qb->executeQuery();
    }

    private function platformSupportsSkipLocked(): bool
    {

        return false;


    }
}
