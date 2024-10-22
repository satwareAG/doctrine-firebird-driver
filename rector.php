<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withAutoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ])->withSets([
        // Upgrade to the latest PHP version; adjust as newer sets are released
        LevelSetList::UP_TO_PHP_81,
        // Common refactorings and performance improvements
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION, // Security-focused refactorings
        DoctrineSetList::DOCTRINE_DBAL_30,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        // PSR and Symfony rules if you are using Symfony components
        SymfonySetList::SYMFONY_CODE_QUALITY,
    ])
    ->withRules([
        PreferPHPUnitSelfCallRector::class,
    ])
    ->withParallel();// Enable parallel processing for faster refactoring in large projects
