<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection as FirebirdConnection;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use Throwable;

use function sprintf;
use function trim;

/**
 * Functional tests for the forceNewConnection and role connection parameters.
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/114
 * @see specs/002-force-new-connection/spec.md
 */
class ForceNewConnectionTest extends TestCase
{
    private const ROLE_NAME = 'TEST_ROLE_114';

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
     * The role parameter MUST reach fbird_connect(). Verified by:
     * 1. Creating a custom SQL role via DDL
     * 2. Connecting with that role
     * 3. Querying CURRENT_ROLE (returns the role name, or 'NONE' without a role)
     * 4. Cleaning up the role
     */
    public function testRoleParameterPassedToFirebird(): void
    {
        $params                                = TestUtil::getConnectionParams();
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;

        // Use a privileged connection to create the test role
        $adminConn = DriverManager::getConnection($params);

        try {
            // Drop role if leftover from a previous run
            try {
                $adminConn->executeStatement(sprintf('DROP ROLE "%s"', self::ROLE_NAME));
            } catch (Throwable) {
                // Role doesn't exist yet - expected
            }

            $adminConn->executeStatement(sprintf('CREATE ROLE "%s"', self::ROLE_NAME));
            // CREATE ROLE only inserts into RDB$ROLES - it does NOT auto-grant
            // membership in RDB$USER_PRIVILEGES. SCL_role_granted() (scl.epp:983)
            // requires an "M" privilege record or the role is silently dropped
            // (scl.epp:1143-1144) and CURRENT_ROLE falls back to NONE.
            $adminConn->executeStatement(sprintf('GRANT "%s" TO PUBLIC', self::ROLE_NAME));
        } finally {
            $adminConn->close();
        }

        try {
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

            // Connection WITH role - CURRENT_ROLE should return the role name
            $paramsWithRole                       = $params;
            $paramsWithRole['forceNewConnection'] = true;
            $paramsWithRole['role']               = self::ROLE_NAME;
            $connWithRole                         = DriverManager::getConnection($paramsWithRole);

            try {
                $roleWithParam = $connWithRole->fetchOne('SELECT CURRENT_ROLE FROM RDB$DATABASE');
                self::assertSame(self::ROLE_NAME, trim((string) $roleWithParam), sprintf('With role=%s, CURRENT_ROLE should match', self::ROLE_NAME));
            } finally {
                $connWithRole->close();
            }
        } finally {
            // Cleanup: drop the role
            $cleanupConn = DriverManager::getConnection($params);

            try {
                $cleanupConn->executeStatement(sprintf('DROP ROLE "%s"', self::ROLE_NAME));
            } catch (Throwable) {
                // Cleanup is best-effort
            }

            $cleanupConn->close();
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
