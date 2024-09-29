<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms;

use Doctrine\Deprecations\Deprecation;

class Firebird5Platform extends Firebird4Platform
{
    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4749',
            'Firebird4Platform::getName() is deprecated. Identify platforms by their class.',
        );

        return 'Firebird5';
    }
}
