<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Platform;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Satag\DoctrineFirebirdDriver\Test\Functional\FunctionalTestCase;


final class BitwiseExpressionTest extends FunctionalTestCase
{
    public function testBitwiseAnd(): void
    {
        $this->assertExpressionEquals('2', static function (AbstractPlatform $platform): string {
            return $platform->getBitAndComparisonExpression('3', '6');
        });
    }

    public function testBitwiseOr(): void
    {
        $this->assertExpressionEquals('7', static function (AbstractPlatform $platform): string {
            return $platform->getBitOrComparisonExpression('3', '6');
        });
    }

    private function assertExpressionEquals(string $expected, callable $expression): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($expression($platform));

        self::assertEquals($expected, $this->connection->fetchOne($query));
    }
}
