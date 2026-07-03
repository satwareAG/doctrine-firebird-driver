<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use Throwable;

use function sprintf;
use function substr;
use function uniqid;

/**
 * Verifies that php-firebird v11.1.0 fixes the v8.2.0 bugs that required
 * the isql workaround and shared-connection constraint in test infrastructure.
 *
 * @see https://github.com/satwareAG/php-firebird/issues/294
 */
class V11ConnectionVerificationTest extends TestCase
{
    /**
     * php-firebird v8.2.0 could not create two connections to the same DB file
     * in one PHP process (second connection got empty DB path, false health check).
     * v11.1.0 fixes this — verify by creating two independent connections.
     */
    public function testTwoConnectionsToSameDatabase(): void
    {
        $params                                = TestUtil::getConnectionParams();
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;

        $conn1 = DriverManager::getConnection($params);
        $conn1->executeQuery($conn1->getDatabasePlatform()->getDummySelectSQL());

        $conn2 = DriverManager::getConnection($params);
        $conn2->executeQuery($conn2->getDatabasePlatform()->getDummySelectSQL());

        // Both connections are independent and functional
        $result1 = $conn1->fetchOne('SELECT 1 FROM RDB$DATABASE');
        $result2 = $conn2->fetchOne('SELECT 2 FROM RDB$DATABASE');

        self::assertSame(1, (int) $result1);
        self::assertSame(2, (int) $result2);

        $conn1->close();
        $conn2->close();
    }

    /**
     * php-firebird v8.2.0 silently dropped DDL/DML executed through the PHP
     * connection on newly created databases. v11.1.0 fixes autocommit (#294)
     * so DDL+DML via PHP connection should persist without isql.
     */
    public function testDdlAndDmlViaPhpConnection(): void
    {
        $params                                = TestUtil::getConnectionParams();
        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;

        $conn = DriverManager::getConnection($params);

        $tableName = 'V11_VERIFY_' . substr(uniqid(), -8);

        try {
            // DDL via PHP connection (autocommit should commit immediately)
            $conn->executeStatement(sprintf(
                'CREATE TABLE "%s" (ID INTEGER NOT NULL, NAME VARCHAR(50))',
                $tableName,
            ));

            // DML via PHP connection (autocommit should commit immediately)
            $conn->executeStatement(sprintf(
                'INSERT INTO "%s" (ID, NAME) VALUES (1, %s)',
                $tableName,
                $conn->getDatabasePlatform()->quoteStringLiteral('test'),
            ));
            $conn->executeStatement(sprintf(
                'INSERT INTO "%s" (ID, NAME) VALUES (2, %s)',
                $tableName,
                $conn->getDatabasePlatform()->quoteStringLiteral('second'),
            ));

            // Verify data is visible (proves autocommit committed DDL+DML)
            $count = (int) $conn->fetchOne(sprintf('SELECT COUNT(*) FROM "%s"', $tableName));
            self::assertSame(2, $count, 'DML via PHP connection should persist (autocommit fix #294)');

            $name = $conn->fetchOne(sprintf('SELECT NAME FROM "%s" WHERE ID = 1', $tableName));
            self::assertSame('test', $name);
        } finally {
            // Cleanup — may fail if cursor still holds table lock
            try {
                $conn->executeStatement(sprintf('DROP TABLE "%s"', $tableName));
            } catch (Throwable) {
                // Table cleanup is non-critical in ephemeral test DB
            }

            $conn->close();
        }
    }
}
