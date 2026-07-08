<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection as FirebirdConnection;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

use function trim;

/**
 * Functional tests for the forceNewConnection and role connection parameters.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/114
 * @see specs/002-force-new-connection/spec.md
 */
class ForceNewConnectionTest extends TestCase
{
    /**
     * Two consecutive connect() calls with forceNewConnection=true MUST yield
     * distinct native Firebird\Connection objects (no reuse).
     */
    public function testForceNewConnectionYieldsDistinctNativeConnections(): void
    {
        $params                                = TestUtil::getConnectionParams();
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;
        $params['forceNewConnection']          = true;

        $conn1 = DriverManager::getConnection($params);
        $conn2 = DriverManager::getConnection($params);

        try {
            $native1 = $this->getNativeConnection($conn1);
            $native2 = $this->getNativeConnection($conn2);

            self::assertNotSame($native1, $native2, 'forceNewConnection should produce distinct native connections');

            // Both connections must be functional
            self::assertSame(1, (int) $conn1->fetchOne('SELECT 1 FROM RDB$DATABASE'));
            self::assertSame(2, (int) $conn2->fetchOne('SELECT 2 FROM RDB$DATABASE'));
        } finally {
            $conn1->close();
            $conn2->close();
        }
    }

    /**
     * Without forceNewConnection, two consecutive connect() calls with identical
     * params MUST reuse the same native connection (php-firebird default behavior).
     */
    public function testDefaultConnectionReusesNativeConnection(): void
    {
        $params                                = TestUtil::getConnectionParams();
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;

        // Do NOT set forceNewConnection - default reuse behavior expected
        $conn1 = DriverManager::getConnection($params);
        $conn2 = DriverManager::getConnection($params);

        try {
            $native1 = $this->getNativeConnection($conn1);
            $native2 = $this->getNativeConnection($conn2);

            self::assertSame($native1, $native2, 'Default behavior should reuse the same native connection');
        } finally {
            $conn1->close();
            $conn2->close();
        }
    }

    /**
     * The role parameter MUST reach fbird_connect(). Verified by querying
     * CURRENT_ROLE: without a role it returns 'NONE', with role='RDB$ADMIN'
     * it returns 'RDB$ADMIN'.
     *
     * Note: RDB$ADMIN is a built-in Firebird role auto-granted to SYSDBA.
     * This test assumes the test DB user is SYSDBA (standard for CI/test
     * environments). A non-SYSDBA user without the role would fail.
     */
    public function testRoleParameterPassedToFirebird(): void
    {
        $params                                = TestUtil::getConnectionParams();
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;

        // Connection WITHOUT role - CURRENT_ROLE should be 'NONE'
        $paramsNoRole                       = $params;
        $paramsNoRole['forceNewConnection'] = true;
        $connNoRole                         = DriverManager::getConnection($paramsNoRole);

        try {
            $roleWithoutParam = $connNoRole->fetchOne('SELECT CURRENT_ROLE FROM RDB$DATABASE');
            self::assertSame('NONE', trim((string) $roleWithoutParam), 'Without role param, CURRENT_ROLE should be NONE');
        } finally {
            $connNoRole->close();
        }

        // Connection WITH role='RDB$ADMIN' - CURRENT_ROLE should be 'RDB$ADMIN'
        $paramsWithRole                       = $params;
        $paramsWithRole['forceNewConnection'] = true;
        $paramsWithRole['role']               = 'RDB$ADMIN';
        $connWithRole                         = DriverManager::getConnection($paramsWithRole);

        try {
            $roleWithParam = $connWithRole->fetchOne('SELECT CURRENT_ROLE FROM RDB$DATABASE');
            self::assertSame('RDB$ADMIN', trim((string) $roleWithParam), 'With role=RDB$ADMIN, CURRENT_ROLE should be RDB$ADMIN');
        } finally {
            $connWithRole->close();
        }
    }

    /**
     * Extract the native Firebird\Connection from a DBAL Connection.
     */
    private function getNativeConnection(Connection $conn): mixed
    {
        $driverConn = $conn->getWrappedConnection();

        self::assertInstanceOf(FirebirdConnection::class, $driverConn);

        return $driverConn->getNativeConnection();
    }
}
