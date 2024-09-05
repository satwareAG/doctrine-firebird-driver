<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\DriverManager;

/**
  * @runTestsInSeparateProcesses
 */
class FetchBooleanTest extends FunctionalTestCase
{
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
