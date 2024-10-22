<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Iterator;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function key;

class QuotingTest extends FunctionalTestCase
{
    /** @dataProvider stringLiteralProvider */
    public function testQuoteStringLiteral(string $string): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL(
            $platform->getTrimExpression($platform->quoteStringLiteral($string)),
        );

        self::assertSame($string, $this->connection->fetchOne($query));
    }

    /** @dataProvider identifierProvider */
    public function testQuoteIdentifier(string $identifier): void
    {
        $platform = $this->connection->getDatabasePlatform();

        /** @link https://docs.oracle.com/cd/B19306_01/server.102/b14200/sql_elements008.htm */
        if ($platform instanceof OraclePlatform && $identifier === '"') {
            self::markTestSkipped('Oracle does not support double quotes in identifiers');
        }

        $query = $platform->getDummySelectSQL(
            'NULL AS ' . $platform->quoteIdentifier($identifier),
        );

        $row = $this->connection->fetchAssociative($query);

        self::assertNotFalse($row);
        self::assertSame($identifier, key($row));
    }

    /** @return mixed[][] */
    public static function stringLiteralProvider(): Iterator
    {
        yield 'backslash' => ['\\'];
        yield 'single-quote' => ["'"];
    }

    /** @return iterable<string,array{0:string}> */
    public static function identifierProvider(): Iterator
    {
        yield '[' => ['['];
        yield ']' => [']'];
        yield '"' => ['"'];
        yield '`' => ['`'];
    }
}
