<?php

namespace Kafoso\DoctrineFirebirdDriver\Test\Functional\Driver;

use Doctrine\DBAL\Driver as DriverInterface;
use Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Driver;
use Kafoso\DoctrineFirebirdDriver\Test\Functional\TestUtil;


/**
 * @ runTestsInSeparateProcesses
 * @requires extension interbase
 */
class DriverTest extends AbstractDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverClassOneOf(Driver::class)) {
            return;
        }

        self::markTestSkipped('This test requires the FirebirdInterbase driver class.');
    }

    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('FirebirdInterbase does not support connecting without database name.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('FirebirdInterbase does not support connecting without database name.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
