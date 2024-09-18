<?php
namespace Satag\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Exception;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\Firebird3Keywords;
use Satag\DoctrineFirebirdDriver\Platforms\SQL\Builder\FirebirdSelectSQLBuilder;

/**
 * Firebird 4.0 introduces TIMESTAMP WITH TIME ZONE and TIME WITH TIME ZONE. These data types allow storage of timestamps with associated time zone information.
 */
class Firebird4Platform extends Firebird3Platform
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
        return "Firebird4";
    }

}
