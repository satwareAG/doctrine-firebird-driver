<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver;

use Doctrine\DBAL\Driver as DriverInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;

#[RequiresPhpExtension('interbase')]
class DriverTest extends DriverTestCase
{
    public function testConnectsWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Firebird does not support connecting without database name.');
    }

    public function testReturnsDatabaseNameWithoutDatabaseNameParameter(): void
    {
        self::markTestSkipped('Firebird does not support connecting without database name.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverClassOneOf(Driver::class)) {
            return;
        }

        self::markTestSkipped('This test requires the Firebird driver class.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
