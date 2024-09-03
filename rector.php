<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withSets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_DBAL_30,
        PHPUnitSetList::PHPUNIT_90,
        LevelSetList::UP_TO_PHP_81
    ])->withPreparedSets(
        deadCode: true,
        codeQuality: true
    );

