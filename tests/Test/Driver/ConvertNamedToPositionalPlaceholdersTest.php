<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Driver;

use Doctrine\DBAL\SQL\Parser;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;

class ConvertNamedToPositionalPlaceholdersTest extends TestCase
{
    /** @param mixed[] $expectedOutputParamsMap */
    #[DataProvider('positionalToNamedPlaceholdersProvider')]
    public function testNamedToPositionalPlaceholders(
        string $inputSQL,
        string $expectedOutputSQL,
        array $expectedOutputParamsMap,
    ): void {
        $parser  = new Parser(false);
        $visitor = new ConvertParameters();

        $parser->parse($inputSQL, $visitor);

        self::assertSame($expectedOutputSQL, $visitor->getSQL());
        self::assertEquals($expectedOutputParamsMap, $visitor->getParameterMap());
    }

    /** @return mixed[][] */
    public static function positionalToNamedPlaceholdersProvider(): Iterator
    {
        yield [
            'SELECT name FROM users WHERE id = :param1',
            'SELECT name FROM users WHERE id = ?',
            [1 => ':param1'],
        ];

        yield [
            'SELECT name FROM users WHERE id = :param1 AND status = :param2',
            'SELECT name FROM users WHERE id = ? AND status = ?',
            [1 => ':param1', 2 => ':param2'],
        ];

        yield [
            "UPDATE users SET name = '???', status = :param1",
            "UPDATE users SET name = '???', status = ?",
            [1 => ':param1'],
        ];

        yield [
            "UPDATE users SET status = :param1, name = '???'",
            "UPDATE users SET status = ?, name = '???'",
            [1 => ':param1'],
        ];

        yield [
            "UPDATE users SET foo = :param1, name = '???', status = :param2",
            "UPDATE users SET foo = ?, name = '???', status = ?",
            [1 => ':param1', 2 => ':param2'],
        ];

        yield [
            'UPDATE users SET name = "???", status = :param1',
            'UPDATE users SET name = "???", status = ?',
            [1 => ':param1'],
        ];

        yield [
            'UPDATE users SET status = :param1, name = "???"',
            'UPDATE users SET status = ?, name = "???"',
            [1 => ':param1'],
        ];

        yield [
            'UPDATE users SET foo = :param1, name = "???", status = :param2',
            'UPDATE users SET foo = ?, name = "???", status = ?',
            [1 => ':param1', 2 => ':param2'],
        ];

        yield [
            'SELECT * FROM users WHERE id = :param1 AND name = "" AND status = :param2',
            'SELECT * FROM users WHERE id = ? AND name = "" AND status = ?',
            [1 => ':param1', 2 => ':param2'],
        ];

        yield [
            "SELECT * FROM users WHERE id = :param1 AND name = '' AND status = :param2",
            "SELECT * FROM users WHERE id = ? AND name = '' AND status = ?",
            [1 => ':param1', 2 => ':param2'],
        ];
    }
}
