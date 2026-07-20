<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Static helper for extracting renamed columns from a TableDiff.
 *
 * Extracted from Doctrine\DBAL\Tests\Functional\Platform\RenameColumnTest
 * (which extends FunctionalTestCase and is not available outside the DBAL
 * test suite). Only the static {@see getRenamedColumns()} method is needed
 * by {@see AbstractComparatorTestCase}.
 */
final class RenameColumnTestHelper
{
    /**
     * @return array<string, Column>
     */
    public static function getRenamedColumns(TableDiff $tableDiff): array
    {
        $renamed = [];
        foreach ($tableDiff->getChangedColumns() as $diff) {
            if (! $diff->hasNameChanged()) {
                continue;
            }

            $oldColumnName = $diff->getOldColumn()
                ->getObjectName()
                ->toString();

            $renamed[$oldColumnName] = $diff->getNewColumn();
        }

        return $renamed;
    }
}
