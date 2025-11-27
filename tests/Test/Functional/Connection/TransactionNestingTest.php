<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Connection;

use ReflectionClass;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function gc_collect_cycles;
use function uniqid;

class TransactionNestingTest extends FunctionalTestCase
{
    private string $tableName;

    public function testNestedCommitDoesNotPersistUntilOuterCommit(): void
    {
        $connection = $this->getFirebirdConnection();
        $connection->beginTransaction(); // Level 1
        $this->connection->executeStatement('INSERT INTO ' . $this->tableName . " (id, val) VALUES (1, 'outer')");

        $connection->beginTransaction(); // Level 2
        $this->connection->executeStatement('INSERT INTO ' . $this->tableName . " (id, val) VALUES (2, 'inner')");
        $connection->commit(); // Level 1 - Should NOT persist

        $connection->rollBack(); // Level 0 - Should rollback BOTH

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName);
        self::assertEquals(0, $count, 'Nested commit should not have persisted when outer transaction rolled back');
    }

    public function testNestedStructureSuccess(): void
    {
        $connection = $this->getFirebirdConnection();
        $connection->beginTransaction(); // 1
        $this->connection->executeStatement('INSERT INTO ' . $this->tableName . " (id, val) VALUES (1, 'outer')");

        $connection->beginTransaction(); // 2
        $this->connection->executeStatement('INSERT INTO ' . $this->tableName . " (id, val) VALUES (2, 'inner')");
        $connection->commit(); // 1

        $connection->commit(); // 0 - Persist all

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName);
        self::assertEquals(2, $count);
    }

    public function testNestedRollbackDecrementsOnly(): void
    {
        $connection = $this->getFirebirdConnection();
        $connection->beginTransaction(); // 1
        $this->connection->executeStatement('INSERT INTO ' . $this->tableName . " (id, val) VALUES (1, 'outer')");

        $connection->beginTransaction(); // 2
        $this->connection->executeStatement('INSERT INTO ' . $this->tableName . " (id, val) VALUES (2, 'inner')");
        $connection->rollBack(); // 1 - Decrements only, NO DB rollback

        $connection->commit(); // 0 - Persist ALL (including 'inner' because we didn't use savepoints)

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->tableName);
        self::assertEquals(2, $count, 'Nested rollback without savepoints acts as counter decrement');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tableName = 'txn_test_' . uniqid();
        $this->connection->executeStatement('CREATE TABLE ' . $this->tableName . ' (id INT NOT NULL PRIMARY KEY, val VARCHAR(32))');
    }

    protected function tearDown(): void
    {
        // Explicitly collect cycles
        gc_collect_cycles();

        $conn = $this->getFirebirdConnection();
        if ($conn) {
            $ref  = new ReflectionClass($conn);
            $prop = $ref->getProperty('fbirdTransactionLevel');
            $prop->setAccessible(true);
            $level = $prop->getValue($conn);

            while ($level > 0) {
                try {
                    $conn->rollBack();
                } catch (Throwable) {
                    break;
                }

                $level--;
            }
        }

        // Final cleanup
        try {
            $this->getFirebirdConnection()?->exec('DROP TABLE ' . $this->tableName);
            $this->getFirebirdConnection()?->commit();
        } catch (Throwable) {
            try {
                $this->getFirebirdConnection()?->rollBack();
            } catch (Throwable) {
            }
        }

        parent::tearDown();
    }
}
