<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms\SQL\Builder;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\ForUpdate;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\SelectQuery;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;

use function implode;

final class FirebirdSelectSQLBuilder implements SelectSQLBuilder
{
    /** @internal The SQL builder should be instantiated only by database platforms. */
    public function __construct(private readonly AbstractPlatform $platform, private readonly string|null $forUpdateSQL, private readonly string|null $skipLockedSQL)
    {
    }

    /** @throws Exception */
    public function buildSQL(SelectQuery $query): string
    {
        $parts = ['SELECT'];

        if ($query->isDistinct()) {
            $parts[] = 'DISTINCT';
        }

        $parts[] = implode(', ', $query->getColumns());

        $from = $query->getFrom();

        if ($from !== []) {
            $parts[] = 'FROM ' . implode(', ', $from);
        }

        $where = $query->getWhere();

        if ($where !== null) {
            $parts[] = 'WHERE ' . $where;
        }

        $forUpdate = $query->getForUpdate();

        if ($forUpdate instanceof ForUpdate) {
            if ($this->forUpdateSQL === null) {
                throw Exception::notSupported('FOR UPDATE');
            }

            $parts[] =  $this->forUpdateSQL;

            if ($forUpdate->getConflictResolutionMode() === ConflictResolutionMode::SKIP_LOCKED) {
                if ($this->skipLockedSQL === null) {
                    throw Exception::notSupported('SKIP LOCKED');
                }

                $parts[] = ' ' . $this->skipLockedSQL;
            }
        }

        $groupBy = $query->getGroupBy();

        if ($groupBy !== []) {
            $parts[] = 'GROUP BY ' . implode(', ', $groupBy);
        }

        $having = $query->getHaving();

        if ($having !== null) {
            $parts[] = 'HAVING ' . $having;
        }

        $orderBy = $query->getOrderBy();

        if ($orderBy !== []) {
            $parts[] = 'ORDER BY ' . implode(', ', $orderBy);
        }

        $sql   = implode(' ', $parts);
        $limit = $query->getLimit();

        if ($limit->isDefined()) {
            $sql = $this->platform->modifyLimitQuery($sql, $limit->getMaxResults(), $limit->getFirstResult());
        }

        return $sql;
    }
}
