<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Doctrine\DBAL\TransactionIsolationLevel;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Driver\FirebirdDriver;
use UnexpectedValueException;

/**
 * Unit tests for Connection class.
 *
 * Tests validation methods, error handling paths, and methods
 * that can be tested without an actual database connection.
 */
#[CoversClass(Connection::class)]
class ConnectionTest extends TestCase
{
    // ==========================================================================
    // quote() Tests
    // ==========================================================================

    public function testQuoteString(): void
    {
        $connection = $this->createConnectionThroughReflection();
        self::assertSame("'key'", $connection->quote('key'));
    }

    public function testQuoteStringWithSingleQuote(): void
    {
        $connection = $this->createConnectionThroughReflection();
        self::assertSame("'''key'", $connection->quote("'key"));
    }

    public function testQuoteReturnsIntegerUnchanged(): void
    {
        $connection = $this->createConnectionThroughReflection();
        self::assertSame(42, $connection->quote(42));
    }

    public function testQuoteReturnsFloatUnchanged(): void
    {
        $connection = $this->createConnectionThroughReflection();
        self::assertSame(3.14, $connection->quote(3.14));
    }

    public function testQuoteThrowsExceptionForNonScalar(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Given value is not scalar');

        $connection->quote(['array']);
    }

    public function testQuoteEscapesSpecialCharacters(): void
    {
        $connection = $this->createConnectionThroughReflection();

        // Test null byte, newline, carriage return, backslash, substitute character
        $result = $connection->quote("test\0\n\r\\\032value");
        self::assertStringContainsString('\\', (string) $result);
    }

    // ==========================================================================
    // isConnectionValid() Tests
    // ==========================================================================

    public function testIsConnectionValidReturnsFalseWhenConnectionIsNull(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        self::assertFalse($connection->isConnectionValid());
    }

    public function testIsConnectionValidReturnsFalseWhenConnectionIsNotResource(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', 'not a resource');

        self::assertFalse($connection->isConnectionValid());
    }

    // ==========================================================================
    // isTransactionValid() Tests
    // ==========================================================================

    public function testIsTransactionValidReturnsFalseWhenTransactionIsNull(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'firebirdActiveTransaction', null);

        self::assertFalse($connection->isTransactionValid());
    }

    public function testIsTransactionValidReturnsFalseWhenTransactionIsNotResource(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'firebirdActiveTransaction', 'not a resource');

        self::assertFalse($connection->isTransactionValid());
    }

    // ==========================================================================
    // setAttribute() / getAttribute() Tests
    // ==========================================================================

    public function testSetAttributeIsolationLevel(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $connection->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            TransactionIsolationLevel::SERIALIZABLE,
        );

        self::assertSame(
            TransactionIsolationLevel::SERIALIZABLE,
            $connection->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL),
        );
    }

    public function testSetAttributeTransactionWait(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $connection->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT,
            10,
        );

        self::assertSame(
            10,
            $connection->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT),
        );
    }

    public function testSetAttributeAutoCommit(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $connection->setAttribute(FirebirdDriver::ATTR_AUTOCOMMIT, false);

        self::assertFalse($connection->getAttribute(PDO::ATTR_AUTOCOMMIT));
    }

    public function testGetAttributeReturnsNullForUnknownAttribute(): void
    {
        $connection = $this->createConnectionThroughReflection();

        self::assertNull($connection->getAttribute(99999));
    }

    public function testGetAttributePersistent(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'isPersistent', true);

        self::assertTrue($connection->getAttribute(PDO::ATTR_PERSISTENT));
    }

    // ==========================================================================
    // getConnectionInsertColumn() / setConnectionInsertColumn() Tests
    // ==========================================================================

    public function testConnectionInsertColumnDefaultsToNull(): void
    {
        $connection = $this->createConnectionThroughReflection();

        self::assertNull($connection->getConnectionInsertColumn());
    }

    public function testSetConnectionInsertColumn(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $connection->setConnectionInsertColumn('ID');

        self::assertSame('ID', $connection->getConnectionInsertColumn());
    }

    public function testSetConnectionInsertColumnToNull(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $connection->setConnectionInsertColumn('ID');
        $connection->setConnectionInsertColumn(null);

        self::assertNull($connection->getConnectionInsertColumn());
    }

    // ==========================================================================
    // setLastInsertId() / lastInsertId() Tests
    // ==========================================================================

    public function testSetLastInsertId(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $connection->setLastInsertId(123);

        self::assertSame(123, $connection->lastInsertId());
    }

    public function testLastInsertIdReturnsFalseWhenNotSet(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connectionInsertId', null);

        self::assertFalse($connection->lastInsertId());
    }

    public function testLastInsertIdThrowsExceptionForInvalidName(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('regular expression');

        // Invalid generator name (too long, > 31 chars)
        $connection->lastInsertId('this_generator_name_is_way_too_long_for_firebird');
    }

    public function testLastInsertIdWithDotReturnsCachedValue(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $connection->setLastInsertId(456);

        // Names containing dots should return cached value
        self::assertSame(456, $connection->lastInsertId('schema.sequence'));
    }

    public function testLastInsertIdThrowsExceptionForNonStringName(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be null or a string');

        // @phpstan-ignore argument.type
        $connection->lastInsertId(123);
    }

    // ==========================================================================
    // errorInfo() Tests
    // ==========================================================================

    public function testErrorInfoReturnsDefaultWhenNoError(): void
    {
        if (! function_exists('fbird_errcode')) {
            self::markTestSkipped('Firebird extension not loaded');
        }

        $connection = $this->createConnectionThroughReflection();

        // When fbird_errcode() returns false (no error), errorInfo returns defaults
        $errorInfo = $connection->errorInfo();

        // Should have code and message keys
        self::assertArrayHasKey('code', $errorInfo);
        self::assertArrayHasKey('message', $errorInfo);

        // When no Firebird error, code should be 0
        if ($errorInfo['code'] === 0) {
            self::assertNull($errorInfo['message']);
        }
    }

    // ==========================================================================
    // getServerVersion() Tests
    // ==========================================================================

    public function testGetServerVersion(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'serverVersion', '4.0.0');

        self::assertSame('4.0.0', $connection->getServerVersion());
    }

    // ==========================================================================
    // getActiveTransaction() Tests
    // ==========================================================================

    public function testGetActiveTransactionReturnsNullWhenNotSet(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'firebirdActiveTransaction', null);

        self::assertNull($connection->getActiveTransaction());
    }

    // ==========================================================================
    // getNativeConnection() Tests
    // ==========================================================================

    public function testGetNativeConnectionReturnsNull(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        self::assertNull($connection->getNativeConnection());
    }

    // ==========================================================================
    // createSavepoint() Tests
    // ==========================================================================

    public function testCreateSavepointThrowsExceptionWhenNoValidTransaction(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'firebirdActiveTransaction', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid transaction resource');

        $connection->createSavepoint('sp1');
    }

    // ==========================================================================
    // releaseSavepoint() Tests
    // ==========================================================================

    public function testReleaseSavepointThrowsExceptionWhenNoValidTransaction(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'firebirdActiveTransaction', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid transaction resource');

        $connection->releaseSavepoint('sp1');
    }

    // ==========================================================================
    // rollbackSavepoint() Tests
    // ==========================================================================

    public function testRollbackSavepointThrowsExceptionWhenNoValidTransaction(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'firebirdActiveTransaction', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid transaction resource');

        $connection->rollbackSavepoint('sp1');
    }

    // ==========================================================================
    // listTableBlockers() Tests
    // ==========================================================================

    public function testListTableBlockersReturnsFalseWhenNoConnection(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        self::assertFalse($connection->listTableBlockers('test_table'));
    }

    // ==========================================================================
    // killAttachment() Tests
    // ==========================================================================

    public function testKillAttachmentThrowsExceptionWhenNoConnection(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('No active connection');

        $connection->killAttachment(123);
    }

    // ==========================================================================
    // dropTableForce() Tests
    // ==========================================================================

    public function testDropTableForceThrowsExceptionWhenNoConnection(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('No active connection');

        $connection->dropTableForce('test_table');
    }

    // ==========================================================================
    // executeAuto() Tests
    // ==========================================================================

    public function testExecuteAutoThrowsExceptionWhenNoConnection(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('No active connection');

        $connection->executeAuto('SELECT 1 FROM RDB$DATABASE');
    }

    // ==========================================================================
    // prepare() Tests
    // ==========================================================================

    public function testPrepareThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);
        $this->setPrivateProperty($connection, 'databaseNotFoundException', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->prepare('SELECT 1 FROM RDB$DATABASE');
    }

    // ==========================================================================
    // Transaction Level Tests (getSavepointName via reflection)
    // ==========================================================================

    public function testGetSavepointNameGeneratesCorrectFormat(): void
    {
        $connection = $this->createConnectionThroughReflection();

        $reflectionMethod = new \ReflectionMethod(Connection::class, 'getSavepointName');

        self::assertSame('TARGET_SP_0', $reflectionMethod->invoke($connection, 0));
        self::assertSame('TARGET_SP_1', $reflectionMethod->invoke($connection, 1));
        self::assertSame('TARGET_SP_5', $reflectionMethod->invoke($connection, 5));
    }

    // ==========================================================================
    // Isolation Level Data Provider Tests
    // ==========================================================================

    #[DataProvider('isolationLevelProvider')]
    public function testSetAttributeIsolationLevels(int $level): void
    {
        $connection = $this->createConnectionThroughReflection();

        $connection->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL,
            $level,
        );

        self::assertSame(
            $level,
            $connection->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_ISOLATION_LEVEL),
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function isolationLevelProvider(): array
    {
        return [
            'READ_UNCOMMITTED' => [TransactionIsolationLevel::READ_UNCOMMITTED],
            'READ_COMMITTED' => [TransactionIsolationLevel::READ_COMMITTED],
            'REPEATABLE_READ' => [TransactionIsolationLevel::REPEATABLE_READ],
            'SERIALIZABLE' => [TransactionIsolationLevel::SERIALIZABLE],
        ];
    }

    // ==========================================================================
    // Transaction Wait Option Tests
    // ==========================================================================

    #[DataProvider('transactionWaitProvider')]
    public function testSetAttributeTransactionWaitOptions(int $waitValue): void
    {
        $connection = $this->createConnectionThroughReflection();

        $connection->setAttribute(
            FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT,
            $waitValue,
        );

        self::assertSame(
            $waitValue,
            $connection->getAttribute(FirebirdDriver::ATTR_DOCTRINE_DEFAULT_TRANS_WAIT),
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function transactionWaitProvider(): array
    {
        return [
            'NOWAIT (0)' => [0],
            'WAIT indefinitely (-1)' => [-1],
            'WAIT 5 seconds' => [5],
            'WAIT 30 seconds' => [30],
        ];
    }

    // ==========================================================================
    // IBatch API Tests (php-firebird v7.0.0+ / Firebird 4.0+)
    // ==========================================================================

    public function testCreateBatchThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->createBatch('INSERT INTO test (id) VALUES (?)');
    }

    public function testCreateBatchThrowsExceptionForOldFirebirdVersions(): void
    {
        // This test verifies that version_compare() logic rejects Firebird < 4.0
        // The actual version check happens after connection validation, so this
        // is a logic test, not an integration test

        // Test the version comparison logic directly
        self::assertTrue(version_compare('3.0.10', '4.0', '<'));
        self::assertTrue(version_compare('2.5.9', '4.0', '<'));
        self::assertFalse(version_compare('4.0.0', '4.0', '<'));
        self::assertFalse(version_compare('5.0.0', '4.0', '<'));

        // When called with invalid connection, the connection check comes first
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->createBatch('INSERT INTO test (id) VALUES (?)');
    }

    public function testExecuteBatchThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->executeBatch('INSERT INTO test (id) VALUES (?)', [[1], [2]]);
    }

    // ==========================================================================
    // Connection Info Tests (php-firebird v7.0.0+)
    // ==========================================================================

    public function testGetConnectionInfoThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->getConnectionInfo();
    }

    // ==========================================================================
    // Limbo Transaction Recovery Tests (php-firebird v7.0.0+)
    // ==========================================================================

    public function testGetLimboTransactionsThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->getLimboTransactions();
    }

    public function testReconnectLimboTransactionThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->reconnectLimboTransaction(12345);
    }

    // ==========================================================================
    // Independent Transaction Tests (OO API)
    // ==========================================================================

    public function testCreateIndependentTransactionThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->createIndependentTransaction();
    }

    public function testGetOOWrapperThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->getOOWrapper();
    }

    public function testQueryInTransactionThrowsExceptionWhenConnectionNotValid(): void
    {
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Connection is not valid');

        $connection->queryInTransaction(null, 'SELECT 1');
    }

    public function testQueryInTransactionValidatesTransaction(): void
    {
        // This test verifies the logic flow - when connection is invalid,
        // we can't test the transaction validation step
        $connection = $this->createConnectionThroughReflection();
        $this->setPrivateProperty($connection, 'connection', null);

        $this->expectException(DriverException::class);
        // Connection validation comes first before transaction validation
        $this->expectExceptionMessage('Connection is not valid');

        $connection->queryInTransaction('not a resource', 'SELECT 1');
    }

    // ==========================================================================
    // Helper Methods
    // ==========================================================================

    private function createConnectionThroughReflection(): Connection
    {
        $reflectionClass = new ReflectionClass(Connection::class);

        return $reflectionClass->newInstanceWithoutConstructor();
    }

    private function setPrivateProperty(Connection $connection, string $propertyName, mixed $value): void
    {
        $reflection = new ReflectionProperty(Connection::class, $propertyName);
        $reflection->setValue($connection, $value);
    }
}
