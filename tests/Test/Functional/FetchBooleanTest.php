<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

class FetchBooleanTest extends FunctionalTestCase
{
    #[DataProvider('booleanLiteralProvider')]
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

    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof Firebird3Platform) {
            return;
        }

        self::markTestSkipped('Only Firebird 3+ supports boolean values natively');
    }
}
