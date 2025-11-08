<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms\SQL\Builder;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\SelectQuery;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;

use function count;
use function implode;
use function preg_replace_callback;

final class FirebirdSelectSQLBuilder implements SelectSQLBuilder
{
    /**
     * Maximum VARCHAR length for CAST wrapper in LIKE expressions.
     * 
     * This value is used to prevent silent query failures when LIKE parameters
     * exceed the actual VARCHAR field length in Firebird.
     * 
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/16
     */
    private const CAST_VARCHAR_LENGTH = 255;

    /** @internal The SQL builder should be instantiated only by database platforms. */
    public function __construct(private AbstractPlatform $platform, private string|null $forUpdateSQL, private string|null $skipLockedSQL)
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

        if (count($from) > 0) {
            $parts[] = 'FROM ' . implode(', ', $from);
        }

        $where = $query->getWhere();

        if ($where !== null) {
            // Issue #16 Fix: Wrap LIKE column operands in CAST to prevent silent failures
            // when parameter length exceeds VARCHAR field length
            $where = $this->wrapLikeColumnsWithCast($where);
            $parts[] = 'WHERE ' . $where;
        }

        $groupBy = $query->getGroupBy();

        if (count($groupBy) > 0) {
            $parts[] = 'GROUP BY ' . implode(', ', $groupBy);
        }

        $having = $query->getHaving();

        if ($having !== null) {
            $parts[] = 'HAVING ' . $having;
        }

        $orderBy = $query->getOrderBy();

        if (count($orderBy) > 0) {
            $parts[] = 'ORDER BY ' . implode(', ', $orderBy);
        }

        $sql   = implode(' ', $parts);
        $limit = $query->getLimit();

        if ($limit->isDefined()) {
            $sql = $this->platform->modifyLimitQuery($sql, $limit->getMaxResults(), $limit->getFirstResult());
        }

        $forUpdate = $query->getForUpdate();

        if ($forUpdate !== null) {
            if ($this->forUpdateSQL === null) {
                throw Exception::notSupported('FOR UPDATE');
            }

            $sql .=  ' ' .  $this->forUpdateSQL;

            if ($forUpdate->getConflictResolutionMode() === ConflictResolutionMode::SKIP_LOCKED) {
                if ($this->skipLockedSQL === null) {
                    throw Exception::notSupported('SKIP LOCKED');
                }

                $sql .= ' ' . $this->skipLockedSQL;
            }
        }


        return $sql;
    }

    /**
     * Wraps column operands in LIKE expressions with CAST to prevent silent query failures.
     * 
     * Problem: When a LIKE parameter exceeds the VARCHAR field length, Firebird silently
     * fails the entire query (returns empty result set) even when other OR conditions
     * should match. This occurs because Firebird infers the parameter type from the
     * column definition and truncates without error.
     * 
     * Solution: Wrapping the column in CAST(column AS VARCHAR(255)) prevents type
     * inference and allows longer parameters to be safely compared.
     * 
     * Trade-off: CAST prevents index usage, requiring full table scan. For performance-
     * critical queries with large tables, consider filtering by other indexed columns
     * first or using application-level parameter validation.
     * 
     * Handles:
     * - Basic LIKE: column LIKE ?
     * - LIKE NOT: column NOT LIKE ?
     * - Aliased columns: t.column LIKE ?
     * - Qualified columns: schema.table.column LIKE ?
     * 
     * @param string $expression The WHERE clause expression to process
     * @return string The expression with LIKE columns wrapped in CAST
     * 
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/16
     */
    private function wrapLikeColumnsWithCast(string $expression): string
    {
        // Match LIKE expressions with column operands
        // Pattern captures:
        // - Group 1: column name (with optional table/schema prefix and alias)
        // - Group 2: NOT keyword (optional, will be empty string if not matched)
        // - Group 3: parameter placeholder (? or :name)
        $pattern = '/(\w+(?:\.\w+)*)\s+(NOT\s+)?LIKE\s+([?:][\w]*)/i';

        $result = preg_replace_callback($pattern, function ($matches) {
            $column = $matches[1];
            $not = $matches[2];  // 'NOT ' or empty string when optional group doesn't match
            $parameter = $matches[3];

            // Wrap column in CAST to VARCHAR(255)
            return sprintf(
                'CAST(%s AS VARCHAR(%d)) %sLIKE %s',
                $column,
                self::CAST_VARCHAR_LENGTH,
                $not,
                $parameter
            );
        }, $expression);

        // preg_replace_callback returns string|null, but with a valid pattern it will always be string
        assert($result !== null);

        return $result;
    }
}
