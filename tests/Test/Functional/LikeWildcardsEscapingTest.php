<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function sprintf;

final class LikeWildcardsEscapingTest extends FunctionalTestCase
{
    public function testFetchLikeExpressionResult(): void
    {
        $string           = '_25% off_ your next purchase \o/ [$̲̅(̲̅5̲̅)̲̅$̲̅] (^̮^)';
        $escapeChar       = '!';
        $databasePlatform = $this->connection->getDatabasePlatform();

        $result = $this->connection->prepare(
            $databasePlatform->getDummySelectSQL(
                sprintf(
                    "(CASE WHEN '%s' LIKE '%s' ESCAPE '%s' THEN 1 ELSE 0 END)",
                    $string,
                    $databasePlatform->escapeStringForLike($string, $escapeChar),
                    $escapeChar,
                ),
            ),
        )->execute();

        self::assertTrue((bool) $result->fetchOne());
    }
}
