<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms;

use Doctrine\Deprecations\Deprecation;
use Override;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\Firebird5Keywords;

final class Firebird5Platform extends Firebird4Platform
{
    #[Override]
    public function getName(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4749',
            'Firebird4Platform::getName() is deprecated. Identify platforms by their class.',
        );

        return 'Firebird5';
    }

    #[Override]
    protected function getReservedKeywordsClass(): string
    {
        return Firebird5Keywords::class;
    }
}
