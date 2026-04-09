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
        $connection = $this->connection->getWrappedConnection();
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
        // Use null to retrieve the last inserted identity value via fbird_last_insert_id()
        // or the cached RETURNING value from the driver — works on all Firebird versions.
        // Firebird3 uses sequences+triggers; Firebird4/5 use native identity columns.
        // There is no external generator named 'ALBUM_D2IS' on Firebird4/5 native identity.
        $albumA = new Entity\Album('Foo');
        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $idA = $this->_entityManager->getConnection()->lastInsertId();
        self::assertSame(3, $idA);

        $albumB = new Entity\Album('Foo');
        $this->_entityManager->persist($albumB);
        $this->_entityManager->flush();
        $idB = $this->_entityManager->getConnection()->lastInsertId();
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
        $connection = $this->connection->getWrappedConnection();

        // Access TransactionManager via Reflection, then use its public API
        $reflectionObject = new ReflectionObject($connection);
        $tmProperty = $reflectionObject->getProperty('transactionManager');
        $transactionManager = $tmProperty->getValue($connection);

        $level = $transactionManager->getLevel();
        $transactionA = $transactionManager->getActiveTransaction();
        self::assertSame(0, $level);
        self::assertIsResource($transactionA);

        $connection->beginTransaction();
        $level = $transactionManager->getLevel();
        $transactionB = $transactionManager->getActiveTransaction();
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
