<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\TransactionIsolationLevel;
use Satag\DoctrineFirebirdDriver\Driver\AbstractFirebirdDriver;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

/**
 *
 */
class ConnectionTest extends AbstractIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->connection = $connection = $this->reConnect();
        $this->wrappedConnection = $connection->getWrappedConnection();
    }
    public function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();
    }
    public function testBasics()
    {
        $connection = $this->wrappedConnection;
        $this->assertIsObject($connection);
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertNull($connection->getAttribute(-1));
        $this->assertSame(
            TransactionIsolationLevel::READ_COMMITTED,
            $connection->getAttribute(AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL)
        );
        $this->assertIsResource($connection->getNativeConnection());
        $this->assertFalse($connection->requiresQueryForServerVersion());
                $this->assertSame("'''foo'''", $connection->quote("'foo'"));
        $this->assertIsString(
            $connection->getStartTransactionSql(TransactionIsolationLevel::READ_UNCOMMITTED)
        );
        $this->assertSame(
            "foo/3333:bar",
            Connection::generateConnectString([
                "host" => "foo",
                "dbname" => "bar",
                "port" => 3333,
            ])
        );
    }

    public function testLastInsertIdWorks()
    {

        if ($this->_platform instanceof Firebird3Platform) { // other platforms support Identity Columns
            $lid = null;
        } else {
            $lid = 'ALBUM_D2IS';
            $id = $this->_entityManager->getConnection()->lastInsertId('ALBUM_D2IS');
            $this->assertSame(2, $id); // 2x ALBUM are inserted in database_setup25.sql
        }

        $albumA = new Entity\Album("Foo");
        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $idA = $this->_entityManager->getConnection()->lastInsertId($lid);
        $this->assertSame(3, $idA);
        $albumB = new Entity\Album("Foo");
        $this->_entityManager->persist($albumB);
        $this->_entityManager->flush();
        $idB = $this->_entityManager->getConnection()->lastInsertId($lid);
        $this->assertSame(4, $idB);
    }

    public function testLastInsertIdThrowsExceptionWhenArgumentNameIsInvalid()
    {
        $this->expectExceptionMessage('Argument $name in lastInsertId must be null or a string. Found: (integer) 42');
        $this->expectException(\InvalidArgumentException::class);
        $this->_entityManager->getConnection()->lastInsertId(42);
    }

    public function testLastInsertIdThrowsExceptionWhenArgumentNameContainsInvalidCharacters()
    {

        $this->expectExceptionMessage('Expects argument $name to match regular expression \'/^\w{1,31}$/\'. Found: (string) "FOO_Ø"');
        $this->expectException(\UnexpectedValueException::class);
        $this->_entityManager->getConnection()->lastInsertId("FOO_Ø");
    }

    /**
     * @dataProvider dataProvider_testGetStartTransactionSqlWorks
     */
    public function testGetStartTransactionSqlWorks($expected, $isolationLevel, $timeout)
    {
        $connection = $this->reConnect(
            [
                AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL => $isolationLevel,
                AbstractFirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT => $timeout ?? 5
            ]);

        $found = $connection->getWrappedConnection()->getStartTransactionSql($isolationLevel);
        $this->assertSame($expected, $found);
        $connection->close();
    }

    public function dataProvider_testGetStartTransactionSqlWorks()
    {
        return [
            [
                "SET TRANSACTION READ WRITE ISOLATION LEVEL READ UNCOMMITTED RECORD_VERSION WAIT LOCK TIMEOUT 5",
                TransactionIsolationLevel::READ_UNCOMMITTED,
                null
            ],
            [
                "SET TRANSACTION READ WRITE ISOLATION LEVEL READ COMMITTED RECORD_VERSION WAIT LOCK TIMEOUT 5",
                TransactionIsolationLevel::READ_COMMITTED,
                null
            ],
            [
                "SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT WAIT LOCK TIMEOUT 5",
                TransactionIsolationLevel::REPEATABLE_READ,
                null
            ],
            [
                "SET TRANSACTION READ WRITE ISOLATION LEVEL SNAPSHOT TABLE STABILITY WAIT LOCK TIMEOUT 5",
                TransactionIsolationLevel::SERIALIZABLE,
                null
            ],
            [
                "SET TRANSACTION READ WRITE ISOLATION LEVEL READ UNCOMMITTED RECORD_VERSION WAIT LOCK TIMEOUT 1",
                TransactionIsolationLevel::READ_UNCOMMITTED,
                1
            ],
            [
                "SET TRANSACTION READ WRITE ISOLATION LEVEL READ UNCOMMITTED RECORD_VERSION WAIT",
                TransactionIsolationLevel::READ_UNCOMMITTED,
                -1
            ],
            [
                "SET TRANSACTION READ WRITE ISOLATION LEVEL READ UNCOMMITTED RECORD_VERSION NO WAIT",
                TransactionIsolationLevel::READ_UNCOMMITTED,
                0
            ],
        ];
    }

    public function testGetStartTransactionSqlThrowsExceptionWhenIsolationLevelIsNotSupported()
    {
        $this->expectExceptionMessage("Isolation level -1 is not supported");
        $this->expectException(Exception::class);
        $connection = $this->wrappedConnection;
        $connection->getStartTransactionSql(-1);
    }

    public function testBeginTransaction()
    {
        $connection = $this->wrappedConnection;

        $reflectionObject = new \ReflectionObject($connection);

        $reflectionPropertyIbaseTransactionLevel = $reflectionObject->getProperty("fbirdTransactionLevel");
        $reflectionPropertyIbaseTransactionLevel->setAccessible(true);
        $level = $reflectionPropertyIbaseTransactionLevel->getValue($connection);

        $reflectionPropertyIbaseTransactionLevel = $reflectionObject->getProperty("fbirdTransactionLevel");
        $reflectionPropertyIbaseTransactionLevel->setAccessible(true);
        $level = $reflectionPropertyIbaseTransactionLevel->getValue($connection);
        $reflectionPropertyIbaseActiveTransaction = $reflectionObject->getProperty("fbirdActiveTransaction");
        $reflectionPropertyIbaseActiveTransaction->setAccessible(true);
        $transactionA = $reflectionPropertyIbaseActiveTransaction->getValue($connection);
        $this->assertSame(0, $level);
        $this->assertIsResource($transactionA);

        $connection->beginTransaction();
        $level = $reflectionPropertyIbaseTransactionLevel->getValue($connection);
        $transactionB = $reflectionPropertyIbaseActiveTransaction->getValue($connection);
        $this->assertSame(1, $level);
        $this->assertIsResource($transactionB);
        $this->assertNotSame($transactionA, $transactionB);
    }

    public function testGenerateConnectStringThrowsExceptionWhenArrayIsMalformed()
    {
        $this->expectExceptionMessage('The "host" and "dbname" parameters are required for Connection');
        $this->expectException(Exception\HostDbnameRequired::class);
        Connection::generateConnectString([]);
    }
}
