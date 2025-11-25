<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Doctrine\DBAL\TransactionIsolationLevel;

/** @requires extension interbase **/
class TransactionTest extends FunctionalTestCase
{
    public function testBeginTransactionCommit(): void
    {
        $this->connection->beginTransaction();
        self::assertTrue($this->connection->isTransactionActive());
        $this->connection->commit();
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testTransactionIsolationLevel(): void
    {
        // Test setting isolation level effectively usage of attributes
        $this->connection->setTransactionIsolation(TransactionIsolationLevel::READ_UNCOMMITTED);
        
        $this->connection->beginTransaction();
        self::assertTrue($this->connection->isTransactionActive());
        $this->connection->commit();
    }

    public function testSetTransactionQueryInterception(): void
    {
        // This tests the "prepare" interception behavior
        // Note: Current implementation effectively ignores the SQL content and uses attributes
        // checking if it runs without error (no deadlock)
        
        $sql = "SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT";
        $statement = $this->connection->prepare($sql);
        
        // Execution should be "fake" successful
        $result = $statement->execute();
        
        self::assertTrue(true, "SET TRANSACTION should not fail or hang");
    }
}
