<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;

class FetchBooleanTest extends \Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            return;
        }

        self::markTestSkipped('Only Firebird 3+ supports boolean values natively');
    }

    /** @dataProvider booleanLiteralProvider */
    public function testBooleanConversionSqlLiteral(string $literal, bool $expected): void
    {
        self::assertSame([$expected], $this->connection->fetchNumeric(
            $this->connection->getDatabasePlatform()
                ->getDummySelectSQL($literal),
        ));
    }

    /** @return iterable<array{string, bool}> */
    public static function booleanLiteralProvider(): iterable
    {
        yield ['true', true];
        yield ['false', false];
    }
}
