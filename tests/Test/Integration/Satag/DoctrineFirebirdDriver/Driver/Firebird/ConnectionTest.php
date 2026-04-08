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
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\Integration\ModifyingIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;
use UnexpectedValueException;

class ConnectionTest extends ModifyingIntegrationTestCase
{
    public function testBasics(): void
    {
        // DBAL4: getWrappedConnection() removed from high-level Connection.
        // Access driver connection via reflection on protected $_conn property.
        $reflConn = new ReflectionObject($this->connection);
        $prop = $reflConn->getProperty('_conn');
        $connectionWrapper = $prop->getValue($this->connection);
        $connection = $connectionWrapper->getWrappedConnection();

        self::assertIsObject($connection);
        self::assertInstanceOf(Connection::class, $connection);
        self::assertNull($connection->getAttribute(-1));
        self::assertSame(TransactionIsolationLevel::READ_COMMITTED, $connection->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL));
        self::assertIsResource($connection->getNativeConnection());
        self::assertSame("'''foo'''", $connection->quote("'foo'"));
        self::assertSame('foo/3333:bar', (string) FirebirdConnectString::fromConnectionParameters([
            'host' => 'foo',
            'dbname' => 'bar',
            'port' => 3333,
        ]));
    }

    public function testLastInsertIdWorks(): void
    {
        if ($this->_platform instanceof Firebird3Platform) { // other platforms support Identity Columns
            $lid = null;
        } else {
            $lid = 'ALBUM_D2IS';
            $id  = $this->_entityManager->getConnection()->lastInsertId('ALBUM_D2IS');
            self::assertSame(2, $id); // 2x ALBUM are inserted in database_setup25.sql
        }

        $albumA = new Entity\Album('Foo');
        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $idA = $this->_entityManager->getConnection()->lastInsertId($lid);
        self::assertSame(3, $idA);
        $albumB = new Entity\Album('Foo');
        $this->_entityManager->persist($albumB);
        $this->_entityManager->flush();
        $idB = $this->_entityManager->getConnection()->lastInsertId($lid);
        self::assertSame(4, $idB);
    }

    public function testLastInsertIdThrowsExceptionWhenArgumentNameIsInvalid(): void
    {
        $this->expectExceptionMessage('Argument $name in lastInsertId must be null or a string. Found: (integer) 42');
        $this->expectException(InvalidArgumentException::class);
        $this->_entityManager->getConnection()->lastInsertId(42);
    }

    public function testLastInsertIdThrowsExceptionWhenArgumentNameContainsInvalidCharacters(): void
    {
        $this->expectExceptionMessage('Expects argument $name to match regular expression \'/^\w{1,31}$/\'. Found: (string) "FOO_Ø"');
        $this->expectException(UnexpectedValueException::class);
        $this->_entityManager->getConnection()->lastInsertId('FOO_Ø');
    }


    public function testBeginTransaction(): void
    {
        // DBAL4: getWrappedConnection() removed; use reflection to access driver Connection.
        $reflConn = new ReflectionObject($this->connection);
        $prop = $reflConn->getProperty('_conn');
        $connectionWrapper = $prop->getValue($this->connection);
        $connection = $connectionWrapper->getWrappedConnection();

        $reflectionObject = new ReflectionObject($connection);

        $reflectionPropertyIbaseTransactionLevel = $reflectionObject->getProperty('fbirdTransactionLevel');
        $level = $reflectionPropertyIbaseTransactionLevel->getValue($connection);

        $reflectionPropertyIbaseTransactionLevel = $reflectionObject->getProperty('fbirdTransactionLevel');
        $level                                    = $reflectionPropertyIbaseTransactionLevel->getValue($connection);
        $reflectionPropertyIbaseActiveTransaction = $reflectionObject->getProperty('firebirdActiveTransaction');
        $transactionA = $reflectionPropertyIbaseActiveTransaction->getValue($connection);
        self::assertSame(0, $level);
        self::assertIsResource($transactionA);

        $connection->beginTransaction();
        $level        = $reflectionPropertyIbaseTransactionLevel->getValue($connection);
        $transactionB = $reflectionPropertyIbaseActiveTransaction->getValue($connection);
        self::assertSame(1, $level);
        self::assertIsResource($transactionB);
        self::assertNotSame($transactionA, $transactionB);
    }

    public function testGenerateConnectStringThrowsExceptionWhenArrayIsMalformed(): void
    {
        $this->expectExceptionMessage('The "host" and "dbname" parameters are required for Connection');
        $this->expectException(Exception\HostDbnameRequired::class);
        FirebirdConnectString::fromConnectionParameters([]);
    }
}
