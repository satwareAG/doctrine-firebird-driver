<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration\Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\TransactionIsolationLevel;
use Kafoso\DoctrineFirebirdDriver\Driver\AbstractFirebirdInterbaseDriver;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Connection;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Statement;
use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\Entity;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity;

/**
 * @ runTestsInSeparateProcesses
 */
class ConnectionTest extends AbstractIntegrationTestCase
{
    public function testBasics()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $this->assertIsObject($connection);
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertNull($connection->getAttribute(-1));
        $this->assertSame(
            TransactionIsolationLevel::READ_COMMITTED,
            $connection->getAttribute(AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL)
        );
        $this->assertIsResource($connection->getInterbaseConnectionResource());
        $this->assertFalse($connection->requiresQueryForServerVersion());
        $this->assertInstanceOf(Statement::class, $connection->prepare("foo"));
        $this->assertSame("'''foo'''", $connection->quote("'foo'"));
        $this->assertIsString(
            $connection->getStartTransactionSql(TransactionIsolationLevel::READ_COMMITTED,)
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
        $id = $this->_entityManager->getConnection()->lastInsertId('ALBUM_ID_SEQ');
        $this->assertSame(2, $id); // 2x ALBUM are inserted in database_setup.sql
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albumA = new AttributeEntity\Album("Foo");
        } else {
            $albumA = new Entity\Album("Foo");
        }

        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $idA = $this->_entityManager->getConnection()->lastInsertId('ALBUM_ID_SEQ');
        $this->assertSame(3, $idA);
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albumB = new AttributeEntity\Album("Foo");
        } else {
            $albumB = new Entity\Album("Foo");
        }
        $this->_entityManager->persist($albumB);
        $this->_entityManager->flush();
        $idB = $this->_entityManager->getConnection()->lastInsertId('ALBUM_ID_SEQ');
        $this->assertSame(4, $idB);
    }

    public function testLastInsertIdThrowsExceptionWhenArgumentNameIsInvalid()
    {
        $this->expectExceptionMessage('Argument $name must be null or a string. Found: (integer) 42');
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
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        if (is_int($timeout)) {
            $connection->setAttribute(AbstractFirebirdInterbaseDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT, $timeout);
        }
        $found = $connection->getStartTransactionSql($isolationLevel);
        $this->assertSame($expected, $found);
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
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();
        $connection->getStartTransactionSql(-1);
    }

    public function testBeginTransaction()
    {
        $connection = $this->_entityManager->getConnection()->getWrappedConnection();

        $reflectionObject = new \ReflectionObject($connection);

        $reflectionPropertyIbaseTransactionLevel = $reflectionObject->getProperty("_ibaseTransactionLevel");
        $reflectionPropertyIbaseTransactionLevel->setAccessible(true);
        $level = $reflectionPropertyIbaseTransactionLevel->getValue($connection);

        $reflectionPropertyIbaseTransactionLevel = $reflectionObject->getProperty("_ibaseTransactionLevel");
        $reflectionPropertyIbaseTransactionLevel->setAccessible(true);
        $level = $reflectionPropertyIbaseTransactionLevel->getValue($connection);
        $reflectionPropertyIbaseActiveTransaction = $reflectionObject->getProperty("_ibaseActiveTransaction");
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
        $this->expectExceptionMessage('Argument $params must contain non-empty "host" and "dbname"');
        $this->expectException(\RuntimeException::class);
        Connection::generateConnectString([]);
    }
}
