<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database;

use Doctrine\DBAL\ConnectionException;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

use function array_keys;
use function implode;
use function md5;
use function strtoupper;
use function substr;

class TransactionTest extends AbstractIntegrationTestCase
{

    public function testWillAutoCommitBottomLevelTransaction(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $connection->exec("INSERT INTO {$tableName} (id) VALUES (42)");
        $connection->close();
        $result = $connection->query("SELECT id FROM {$tableName} WHERE id = 42");
        $value  = $result->fetchOne();
        self::assertSame(42, $value);
    }

    public function testCanSuccessfullyCommitASingleTransactionForInsert(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $connection->beginTransaction();
        $connection->exec("INSERT INTO {$tableName} (id) VALUES (42)");
        $connection->commit();
        $result = $connection->query("SELECT id FROM {$tableName} WHERE id = 42");
        $value  = $result->fetchOne();
        self::assertSame(42, $value);
        try {
            $connection->commit();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyCommitASingleTransactionForUpdate(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $connection->exec("INSERT INTO {$tableName} (id) VALUES (42)");
        $connection->beginTransaction();
        $connection->exec("UPDATE {$tableName} SET id = 43 WHERE id = 42");
        $connection->commit();
        $resultA = $connection->query("SELECT id FROM {$tableName} WHERE id = 42");
        $valueA  = $resultA->fetchOne();
        self::assertFalse($valueA);
        $resultB = $connection->query("SELECT id FROM {$tableName} WHERE id = 43");
        $valueB  = $resultB->fetchOne();
        self::assertSame(43, $valueB);
        try {
            $connection->commit();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyCommitMultipleTransactionsForInsert(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $expectedTransactionLevel = 0;
        foreach ([42, 43, 44] as $id) {
            $connection->beginTransaction();
            $expectedTransactionLevel++;
            self::assertSame($expectedTransactionLevel, $connection->getTransactionNestingLevel(), 'Expected transaction level');
            $connection->exec("INSERT INTO {$tableName} (id) VALUES ($id)");
            $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
            $count  = $result->fetchOne();
            self::assertSame($count, $connection->getTransactionNestingLevel(), 'Count vs expected transaction level');
        }

        $connection->commit();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(2, $connection->getTransactionNestingLevel(), 'Transaction level, 3rd');
        self::assertSame(3, $count, 'Count, 3rd');
        $connection->commit();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(1, $connection->getTransactionNestingLevel(), 'Transaction level, 2nd');
        self::assertSame(3, $count, 'Count, 2nd');
        $connection->commit();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(0, $connection->getTransactionNestingLevel(), 'Transaction level, 1st');
        self::assertSame(3, $count, 'Count, 1st');
        try {
            $connection->commit();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyCommitMultipleTransactionsForUpdate(): void
    {
        $map = [
            42 => 52,
            43 => 53,
            44 => 54,
        ];

        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        foreach ($map as $idBefore => $idAfter) {
            $connection->exec("INSERT INTO {$tableName} (id) VALUES ({$idBefore})");
        }

        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 42], ['ID' => 43], ['ID' => 44]], $stmt->fetchAll());

        $expectedTransactionLevel = 0;
        foreach ($map as $idBefore => $idAfter) {
            $connection->beginTransaction();
            $expectedTransactionLevel++;
            self::assertSame($expectedTransactionLevel, $connection->getTransactionNestingLevel(), 'Expected transaction level');
            $connection->exec("UPDATE {$tableName} SET id = {$idAfter} WHERE id = {$idBefore}");
        }

        $stmt = $connection->query("SELECT id FROM {$tableName} WHERE id IN (" . implode(',', array_keys($map)) . ')');
        self::assertSame([], $stmt->fetchAll());

        $expected = [['ID' => 52], ['ID' => 53], ['ID' => 54]]; // We are in inner transaction, so we see them all

        $stmt = $connection->query("SELECT id FROM {$tableName} WHERE id IN (" . implode(',', $map) . ')');
        self::assertSame($expected, $stmt->fetchAll());
        foreach (array_keys($map) as $idBefore) {
            $connection->commit();
            $stmt = $connection->query("SELECT id FROM {$tableName} WHERE id IN (" . implode(',', $map) . ')');
            self::assertSame($expected, $stmt->fetchAll());
        }

        try {
            $connection->commit();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyRollbackASingleTransactionForInsert(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $connection->beginTransaction();
        $connection->exec("INSERT INTO {$tableName} (id) VALUES (42)");
        $connection->rollback();
        $result = $connection->query("SELECT id FROM {$tableName} WHERE id = 42");
        $value  = $result->fetchOne();
        self::assertFalse($value);
        try {
            $connection->rollback();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyRollbackASingleTransactionForUpdate(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $connection->exec("INSERT INTO {$tableName} (id) VALUES (42)");

        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 42]], $stmt->fetchAll());

        $connection->beginTransaction();
        $connection->exec("UPDATE {$tableName} SET id = 52 WHERE id = 42");
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52]], $stmt->fetchAll());
        $connection->rollback();

        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 42]], $stmt->fetchAll());

        try {
            $connection->rollback();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyRollbackMultipleTransactionsForInsert(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $expectedTransactionLevel = 0;
        foreach ([42, 43, 44] as $id) {
            $connection->beginTransaction();
            $expectedTransactionLevel++;
            self::assertSame($expectedTransactionLevel, $connection->getTransactionNestingLevel(), 'Expected transaction level');
            $connection->exec("INSERT INTO {$tableName} (id) VALUES ($id)");
            $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
            $count  = $result->fetchOne();
            self::assertSame($count, $connection->getTransactionNestingLevel(), 'Count vs expected transaction level');
        }

        $connection->rollback();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(2, $connection->getTransactionNestingLevel(), 'Transaction level, 3rd');
        self::assertSame(2, $count, 'Count, 3rd');
        $connection->rollback();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(1, $connection->getTransactionNestingLevel(), 'Transaction level, 2nd');
        self::assertSame(1, $count, 'Count, 2nd');
        $connection->rollback();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(0, $connection->getTransactionNestingLevel(), 'Transaction level, 1st');
        self::assertSame(0, $count, 'Count, 1st');
        try {
            $connection->rollback();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyRollbackMultipleTransactionsForUpdate(): void
    {
        $map = [
            42 => 52,
            43 => 53,
            44 => 54,
        ];

        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        foreach ($map as $idBefore => $idAfter) {
            $connection->exec("INSERT INTO {$tableName} (id) VALUES ({$idBefore})");
        }

        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 42], ['ID' => 43], ['ID' => 44]], $stmt->fetchAll());

        $expectedTransactionLevel = 0;
        foreach ($map as $idBefore => $idAfter) {
            $connection->beginTransaction();
            $expectedTransactionLevel++;
            self::assertSame($expectedTransactionLevel, $connection->getTransactionNestingLevel(), 'Expected transaction level');
            $connection->exec("UPDATE {$tableName} SET id = {$idAfter} WHERE id = {$idBefore}");
        }

        $connection->rollback();
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52], ['ID' => 53], ['ID' => 44]], $stmt->fetchAll());
        self::assertSame(2, $connection->getTransactionNestingLevel(), 'Transaction level, 3rd');

        $connection->rollback();
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52], ['ID' => 43], ['ID' => 44]], $stmt->fetchAll());
        self::assertSame(1, $connection->getTransactionNestingLevel(), 'Transaction level, 2nd');

        $connection->rollback();
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 42], ['ID' => 43], ['ID' => 44]], $stmt->fetchAll());
        self::assertSame(0, $connection->getTransactionNestingLevel(), 'Transaction level, 1st');
        try {
            $connection->rollback();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyCommitAndRollbackMultipleTransactionsForInsert(): void
    {
        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        $expectedTransactionLevel = 0;
        foreach ([42, 43, 44, 45] as $id) {
            $connection->beginTransaction();
            $expectedTransactionLevel++;
            self::assertSame($expectedTransactionLevel, $connection->getTransactionNestingLevel(), 'Expected transaction level');
            $connection->exec("INSERT INTO {$tableName} (id) VALUES ($id)");
            $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
            $count  = $result->fetchOne();
            self::assertSame($count, $connection->getTransactionNestingLevel(), 'Count vs expected transaction level');
        }

        $connection->rollback();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(3, $connection->getTransactionNestingLevel(), 'Transaction level, 4th');
        self::assertSame(3, $count, 'Count, 4th');
        $connection->commit();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(2, $connection->getTransactionNestingLevel(), 'Transaction level, 3rd');
        self::assertSame(3, $count, 'Count, 3rd');
        $connection->rollback();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(1, $connection->getTransactionNestingLevel(), 'Transaction level, 2nd');
        self::assertSame(1, $count, 'Count, 2nd');
        $connection->commit();
        $result = $connection->query("SELECT COUNT(id) FROM {$tableName}");
        $count  = $result->fetchOne();
        self::assertSame(0, $connection->getTransactionNestingLevel(), 'Transaction level, 1st');
        self::assertSame(1, $count, 'Count, 1st');
        try {
            $connection->commit();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }

        try {
            $connection->rollback();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }

    public function testCanSuccessfullyCommitAndRollbackMultipleTransactionsForUpdate(): void
    {
        $map = [
            42 => 52,
            43 => 53,
            44 => 54,
            45 => 55,
        ];

        $connection = $this->connection;
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $connection->exec("CREATE TABLE {$tableName} (id INTEGER DEFAULT 0 NOT NULL)");
        foreach ($map as $idBefore => $idAfter) {
            $connection->exec("INSERT INTO {$tableName} (id) VALUES ({$idBefore})");
        }

        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 42], ['ID' => 43], ['ID' => 44], ['ID' => 45]], $stmt->fetchAll());

        $expectedTransactionLevel = 0;
        foreach ($map as $idBefore => $idAfter) {
            $connection->beginTransaction();
            $expectedTransactionLevel++;
            self::assertSame($expectedTransactionLevel, $connection->getTransactionNestingLevel(), 'Expected transaction level');
            $connection->exec("UPDATE {$tableName} SET id = {$idAfter} WHERE id = {$idBefore}");
        }

        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52], ['ID' => 53], ['ID' => 54], ['ID' => 55]], $stmt->fetchAll());

        $connection->rollback();
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52], ['ID' => 53], ['ID' => 54], ['ID' => 45]], $stmt->fetchAll());
        self::assertSame(3, $connection->getTransactionNestingLevel(), 'Transaction level, 4th');

        $connection->commit();
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52], ['ID' => 53], ['ID' => 54], ['ID' => 45]], $stmt->fetchAll());
        self::assertSame(2, $connection->getTransactionNestingLevel(), 'Transaction level, 3rd');

        $connection->rollback();
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52], ['ID' => 43], ['ID' => 44], ['ID' => 45]], $stmt->fetchAll());
        self::assertSame(1, $connection->getTransactionNestingLevel(), 'Transaction level, 2nd');

        $connection->commit();
        $stmt = $connection->query("SELECT id FROM {$tableName}");
        self::assertSame([['ID' => 52], ['ID' => 43], ['ID' => 44], ['ID' => 45]], $stmt->fetchAll());
        self::assertSame(0, $connection->getTransactionNestingLevel(), 'Transaction level, 1st');

        try {
            $connection->commit();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }

        try {
            $connection->rollback();
        } catch (ConnectionException $e) {
            self::assertStringContainsString('There is no active transaction', $e->getMessage());
        }
    }
}
