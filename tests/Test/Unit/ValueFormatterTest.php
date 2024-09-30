<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\ValueFormatter;
use stdClass;

use function fopen;

class ValueFormatterTest extends TestCase
{
    public function testEscapeAndQuote(): void
    {
        self::assertSame('""', ValueFormatter::escapeAndQuote(''));
        self::assertSame('"2"', ValueFormatter::escapeAndQuote('2'));
        self::assertSame('"2\""', ValueFormatter::escapeAndQuote('2"'));
        self::assertSame('"2\\\\"', ValueFormatter::escapeAndQuote('2\\'));
    }

    public function testCast(): void
    {
        self::assertSame('null', ValueFormatter::cast(null));
        self::assertSame('true', ValueFormatter::cast(true));
        self::assertSame('false', ValueFormatter::cast(false));
        self::assertSame('42', ValueFormatter::cast(42));
        self::assertSame('3.14', ValueFormatter::cast(3.14));
        self::assertSame('"foo"', ValueFormatter::cast('foo'));
        self::assertSame('Array(2)', ValueFormatter::cast([1, 2]));
        self::assertSame('\stdClass', ValueFormatter::cast(new stdClass()));
        self::assertMatchesRegularExpression('/^#Resource id #\d+$/', ValueFormatter::cast(fopen(__FILE__, 'r')));
    }

    public function testFound(): void
    {
        self::assertSame('(null) null', ValueFormatter::found(null));
        self::assertSame('(boolean) true', ValueFormatter::found(true));
        self::assertSame('(boolean) false', ValueFormatter::found(false));
        self::assertSame('(integer) 42', ValueFormatter::found(42));
        self::assertSame('(float) 3.14', ValueFormatter::found(3.14));
        self::assertSame('(string) "foo"', ValueFormatter::found('foo'));
        self::assertSame('(array) Array(2)', ValueFormatter::found([1, 2]));
        self::assertSame('(object) \\stdClass', ValueFormatter::found(new stdClass()));
        self::assertMatchesRegularExpression('/^\(resource\) Resource id #\d+$/', ValueFormatter::found(fopen(__FILE__, 'r')));
    }
}
