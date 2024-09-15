<?php

namespace Satag\DoctrineFirebirdDriver\Platforms\SQL\Builder\SelectSQLBuilder;

use Doctrine\DBAL\Query\SelectQuery;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder as SelectSQLBuilderInterface;

class SelectSQLBuilder implements SelectSQLBuilderInterface
{

    /**
     * @inheritDoc
     */
    public function buildSQL(SelectQuery $query): string
    {
        // TODO: Implement buildSQL() method.
    }
}
