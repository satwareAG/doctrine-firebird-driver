<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\TransactionIsolationLevel;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionObject;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\FirebirdConnectString;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use Satag\DoctrineFirebirdDriver\Test\Integration\ModifyingIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;
use UnexpectedValueException;

class ConnectionTest extends ModifyingIntegrationTestCase
{
    public function testBasics(): void
    {
        // DBAL4: $_conn stores the driver Connection directly; no getWrappedConnection() needed.
        $reflConn = new ReflectionObject($this->connection);
        $prop = $reflConn->getProperty('_conn');
        $connection = $prop->getValue($this->connection);

        self::assertIsObject($connection);
        self::assertInstanceOf(Connection::class, $connection);
        self::assertNull($connection->getAttribute(-1));
        self::assertSame(TransactionIsolationLevel::READ_COMMITTED, $connection->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL));
        self::assertInstanceOf(\Firebird\Connection::class, $connection->getNativeConnection());
        self::assertSame("'''foo'''", $connection->quote("'foo'"));
        self::assertSame('foo/3333:bar', (string) FirebirdConnectString::fromConnectionParameters([
            'host' => 'foo',
            'dbname' => 'bar',
            'port' => 3333,
        ]));
    }

    public function testLastInsertIdWorks(): void
    {
        // Use null to retrieve the last inserted identity value via fbird_last_insert_id()
        // or the IDENTITY generator lookup from the driver — works on all Firebird versions.
        // The test database may already contain albums from other test classes, so we compare
        // against the entity ID rather than a hardcoded value.
        $albumA = new Entity\Album('Foo');
        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $idA = $this->_entityManager->getConnection()->lastInsertId();
        self::assertIsInt($idA);
        self::assertGreaterThan(0, $idA);
        self::assertSame($albumA->getId(), $idA);

        $albumB = new Entity\Album('Foo');
        $this->_entityManager->persist($albumB);
        $this->_entityManager->flush();
        $idB = $this->_entityManager->getConnection()->lastInsertId();
        self::assertIsInt($idB);
        self::assertGreaterThan($idA, $idB);
        self::assertSame($albumB->getId(), $idB);
    }

    public function testLastInsertIdThrowsExceptionWhenArgumentNameIsInvalid(): void
    {
        // DBAL4: lastInsertId() accepts no $name parameter; argument validation was removed
        self::markTestSkipped('DBAL4: lastInsertId() no longer accepts a $name argument; validation tests not applicable.');
    }

    public function testLastInsertIdThrowsExceptionWhenArgumentNameContainsInvalidCharacters(): void
    {
        // DBAL4: lastInsertId() accepts no $name parameter; argument validation was removed
        self::markTestSkipped('DBAL4: lastInsertId() no longer accepts a $name argument; validation tests not applicable.');
    }


    public function testBeginTransaction(): void
    {
        // DBAL4: $_conn stores the driver Connection directly; no getWrappedConnection() needed.
        $reflConn = new ReflectionObject($this->connection);
        $prop = $reflConn->getProperty('_conn');
        $connection = $prop->getValue($this->connection);

        // Access TransactionManager via Reflection, then use its public API
        $reflectionObject = new ReflectionObject($connection);
        $tmProperty = $reflectionObject->getProperty('transactionManager');
        $transactionManager = $tmProperty->getValue($connection);

        $level = $transactionManager->getLevel();
        $transactionA = $transactionManager->getActiveTransaction();
        self::assertSame(0, $level);
        self::assertInstanceOf(\Firebird\Transaction::class, $transactionA);

        $connection->beginTransaction();
        $level = $transactionManager->getLevel();
        $transactionB = $transactionManager->getActiveTransaction();
        self::assertSame(1, $level);
        self::assertInstanceOf(\Firebird\Transaction::class, $transactionB);
        self::assertNotSame($transactionA, $transactionB);
    }

    public function testGenerateConnectStringThrowsExceptionWhenArrayIsMalformed(): void
    {
        $this->expectExceptionMessage('The "host" and "dbname" parameters are required for Connection');
        $this->expectException(Exception\HostDbnameRequired::class);
        FirebirdConnectString::fromConnectionParameters([]);
    }
}
