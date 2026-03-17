<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Connection;

use Firebird\Database;
use Firebird\DbInfo;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function class_exists;
use function function_exists;
use function is_array;

/**
 * Functional tests for Connection::getConnectionInfo() and Connection::getLimboTransactions().
 *
 * getConnectionInfo() returns connection statistics via the php-firebird v7+ OO API
 * (DbInfo::fromConnection()) or falls back to fbird_connection_info().
 *
 * getLimboTransactions() returns in-doubt transaction IDs from two-phase commit failures.
 * Requires php-firebird v7.0.0+.
 */
class ConnectionInfoTest extends FunctionalTestCase
{
    /**
     * getConnectionInfo() returns a non-false result when the connection is valid.
     */
    public function testGetConnectionInfoReturnsResult(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $info = $conn->getConnectionInfo();

        self::assertNotFalse($info, 'getConnectionInfo() must return a non-false result on a valid connection');
    }

    /**
     * getConnectionInfo() returns a DbInfo object when the OO API is available
     * (php-firebird v7.0.0+).
     */
    public function testGetConnectionInfoReturnsDbInfoWhenOoApiAvailable(): void
    {
        if (! class_exists(DbInfo::class)) {
            self::markTestSkipped('DbInfo OO class not available — requires php-firebird v7.0.0+');
        }

        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $info = $conn->getConnectionInfo();

        self::assertInstanceOf(
            DbInfo::class,
            $info,
            'getConnectionInfo() must return a DbInfo instance when php-firebird v7+ OO API is available',
        );
    }

    /**
     * getConnectionInfo() falls back to an array when only the procedural API is available.
     */
    public function testGetConnectionInfoFallbackReturnsArray(): void
    {
        if (class_exists(DbInfo::class)) {
            self::markTestSkipped('DbInfo OO class is available — OO path is tested by testGetConnectionInfoReturnsDbInfoWhenOoApiAvailable');
        }

        if (! function_exists('fbird_connection_info')) {
            self::markTestSkipped('fbird_connection_info() not available — requires php-firebird extension');
        }

        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $info = $conn->getConnectionInfo();

        self::assertTrue(
            is_array($info),
            'getConnectionInfo() procedural fallback must return an array',
        );
    }

    /**
     * getConnectionInfo() returns the same result on repeated calls (idempotent).
     */
    public function testGetConnectionInfoIsIdempotent(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $info1 = $conn->getConnectionInfo();
        $info2 = $conn->getConnectionInfo();

        self::assertNotFalse($info1, 'First getConnectionInfo() call must succeed');
        self::assertNotFalse($info2, 'Second getConnectionInfo() call must succeed');

        // Both calls must return the same type
        self::assertSame(
            $info1 instanceof DbInfo,
            $info2 instanceof DbInfo,
            'Repeated getConnectionInfo() calls must return the same type',
        );
    }

    /**
     * getLimboTransactions() returns an array (possibly empty) on a healthy database.
     *
     * In a normal test environment there are no limbo transactions, so the result
     * should be an empty array. The important thing is that the call succeeds and
     * returns an array, not false.
     */
    public function testGetLimboTransactionsReturnsArray(): void
    {
        if (! function_exists('fbird_get_limbo_transactions')) {
            self::markTestSkipped('fbird_get_limbo_transactions() not available — requires php-firebird v7.0.0+');
        }

        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $limbo = $conn->getLimboTransactions();

        self::assertIsArray($limbo, 'getLimboTransactions() must return an array on a healthy database');
    }

    /**
     * getLimboTransactions() throws DriverException when the function is not available.
     *
     * This test only runs when fbird_get_limbo_transactions() is NOT present,
     * which would be the case with an older php-firebird build.
     */
    public function testGetLimboTransactionsThrowsWhenFunctionMissing(): void
    {
        if (function_exists('fbird_get_limbo_transactions')) {
            self::markTestSkipped('fbird_get_limbo_transactions() is available — missing-function path cannot be tested');
        }

        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('/fbird_get_limbo_transactions.*php-firebird/i');
        $conn->getLimboTransactions();
    }

    /**
     * getOOWrapper() returns a Database instance wrapping the native connection.
     */
    public function testGetOoWrapperReturnsDatabaseInstance(): void
    {
        if (! class_exists(Database::class)) {
            self::markTestSkipped('Firebird\Database OO class not available — requires php-firebird v7.0.0+');
        }

        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $db = $conn->getOOWrapper();

        self::assertInstanceOf(
            Database::class,
            $db,
            'getOOWrapper() must return a Firebird\Database instance',
        );
    }

    /**
     * isConnectionValid() returns true for an active connection.
     */
    public function testIsConnectionValidReturnsTrueForActiveConnection(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        self::assertTrue($conn->isConnectionValid(), 'isConnectionValid() must return true for an active connection');
    }

    /**
     * isTransactionValid() returns true when an active transaction exists.
     */
    public function testIsTransactionValidReturnsTrueWhenTransactionActive(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        self::assertTrue($conn->isTransactionValid(), 'isTransactionValid() must return true when a transaction is active');
    }

    /**
     * getServerVersion() returns a non-empty version string.
     *
     * fbird_server_info() returns the raw Firebird version string in the format:
     * "LI-V3.0.13.33818 Firebird 3.0" (Linux) or "WI-V4.0.0.2496 Firebird 4.0" (Windows).
     * This is the format expected by DBAL's VersionAwarePlatformDriver.
     */
    public function testGetServerVersionReturnsNonEmptyString(): void
    {
        $conn = $this->getFirebirdConnection();
        self::assertNotNull($conn, 'Firebird connection must be available');

        $version = $conn->getServerVersion();

        self::assertNotEmpty($version, 'getServerVersion() must return a non-empty string');

        // Raw format: "LI-V3.0.13.33818 Firebird 3.0" or "WI-V4.0.0.2496 Firebird 4.0"
        // Must contain a version number somewhere in the string
        self::assertMatchesRegularExpression(
            '/\d+\.\d+/',
            $version,
            'getServerVersion() must contain a version number (e.g. "LI-V3.0.13.33818 Firebird 3.0")',
        );
    }
}
