<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

final class ConcatExpressionTest extends FunctionalTestCase
{
    /** @param list<string> $arguments */
    #[DataProvider('expressionProvider')]
    public function testConcatExpression(array $arguments, string $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($platform->getConcatExpression(...$arguments));

        self::assertSame($expected, $this->connection->fetchOne($query));
    }

    /** @return iterable<string,array{list<string>,string}> */
    public static function expressionProvider(): iterable
    {
        yield 'strings' => [["'foo'", "'bar'"], 'foobar'];
        yield 'numbers and a hyphen' => [['2010', "'-'", '2019'], '2010-2019'];
    }
}
