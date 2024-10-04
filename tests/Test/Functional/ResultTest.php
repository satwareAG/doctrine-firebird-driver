<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

class ResultTest extends FunctionalTestCase
{
    /** @dataProvider methodProvider */
    public function testExceptionHandling(callable $method, mixed $expected): void
    {
        $result = $this->connection->executeQuery(
            $this->connection->getDatabasePlatform()
                ->getDummySelectSQL(),
        );
        $result->free();

        try {
            // some drivers will trigger a PHP error here which, if not suppressed,
            // would be converted to a PHPUnit exception prior to DBAL throwing its own one
            $value = @$method($result);
        } catch (Exception) {
            // The drivers that enforce the command sequencing internally will throw an exception
            $this->expectNotToPerformAssertions();

            return;
        }

        // Other drivers will silently return an empty result
        self::assertSame($expected, $value);
    }

    // phpcs:disable Squiz.Arrays.ArrayDeclaration.ValueNoNewline

    /** @return iterable<string, array{callable(Result):mixed, mixed}> */
    public static function methodProvider(): iterable
    {
        yield 'fetchNumeric' => [
            static fn (Result $result) => $result->fetchNumeric(),
            false,
        ];

        yield 'fetchAssociative' => [
            static fn (Result $result) => $result->fetchAssociative(),
            false,
        ];

        yield 'fetchOne' => [
            static fn (Result $result) => $result->fetchOne(),
            false,
        ];

        yield 'fetchAllNumeric' => [
            static fn (Result $result): array => $result->fetchAllNumeric(),
            [],
        ];

        yield 'fetchAllAssociative' => [
            static fn (Result $result): array => $result->fetchAllAssociative(),
            [],
        ];

        yield 'fetchFirstColumn' => [
            static fn (Result $result): array => $result->fetchFirstColumn(),
            [],
        ];
    }

    // phpcs:enable
}
