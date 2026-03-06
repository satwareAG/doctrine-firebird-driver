<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\ForUpdate;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\Limit;
use Doctrine\DBAL\Query\SelectQuery;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Platforms\SQL\Builder\FirebirdSelectSQLBuilder;

/**
 * Unit tests for FirebirdSelectSQLBuilder.
 */
class SelectSQLBuilderTest extends TestCase
{
    private Firebird3Platform $platform;
    private FirebirdSelectSQLBuilder $builder;

    protected function setUp(): void
    {
        $this->platform = new Firebird3Platform();
        // forUpdateSQL = 'WITH LOCK', skipLockedSQL = null (Firebird doesn't support SKIP LOCKED)
        $this->builder = new FirebirdSelectSQLBuilder($this->platform, 'WITH LOCK', null);
    }

    private function makeQuery(
        bool $distinct = false,
        array $columns = ['*'],
        array $from = [],
        ?string $where = null,
        array $groupBy = [],
        ?string $having = null,
        array $orderBy = [],
        ?int $maxResults = null,
        int $firstResult = 0,
        ?ForUpdate $forUpdate = null,
    ): SelectQuery {
        return new SelectQuery(
            $distinct,
            $columns,
            $from,
            $where,
            $groupBy,
            $having,
            $orderBy,
            new Limit($maxResults, $firstResult),
            $forUpdate,
        );
    }

    public function testSimpleSelectAll(): void
    {
        $query = $this->makeQuery(columns: ['id', 'name'], from: ['users']);
        $sql   = $this->builder->buildSQL($query);
        self::assertSame('SELECT id, name FROM users', $sql);
    }

    public function testSelectDistinct(): void
    {
        $query = $this->makeQuery(distinct: true, columns: ['id'], from: ['users']);
        $sql   = $this->builder->buildSQL($query);
        self::assertSame('SELECT DISTINCT id FROM users', $sql);
    }

    public function testSelectWithWhere(): void
    {
        $query = $this->makeQuery(columns: ['id'], from: ['users'], where: 'id = ?');
        $sql   = $this->builder->buildSQL($query);
        self::assertSame('SELECT id FROM users WHERE id = ?', $sql);
    }

    public function testSelectWithLikeWrapsInCast(): void
    {
        $query = $this->makeQuery(columns: ['id', 'name'], from: ['users'], where: 'name LIKE ?');
        $sql   = $this->builder->buildSQL($query);
        self::assertStringContainsString('CAST(name AS VARCHAR(255)) LIKE ?', $sql);
    }

    public function testSelectWithNotLikeWrapsInCast(): void
    {
        $query = $this->makeQuery(columns: ['id'], from: ['users'], where: 'name NOT LIKE ?');
        $sql   = $this->builder->buildSQL($query);
        self::assertStringContainsString('CAST(name AS VARCHAR(255)) NOT LIKE ?', $sql);
    }

    public function testSelectWithAliasedColumnLike(): void
    {
        $query = $this->makeQuery(columns: ['u.id'], from: ['users u'], where: 'u.name LIKE ?');
        $sql   = $this->builder->buildSQL($query);
        self::assertStringContainsString('CAST(u.name AS VARCHAR(255)) LIKE ?', $sql);
    }

    public function testSelectWithGroupBy(): void
    {
        $query = $this->makeQuery(columns: ['status', 'COUNT(*)'], from: ['users'], groupBy: ['status']);
        $sql   = $this->builder->buildSQL($query);
        self::assertSame('SELECT status, COUNT(*) FROM users GROUP BY status', $sql);
    }

    public function testSelectWithHaving(): void
    {
        $query = $this->makeQuery(
            columns: ['status', 'COUNT(*)'],
            from: ['users'],
            groupBy: ['status'],
            having: 'COUNT(*) > 1',
        );
        $sql = $this->builder->buildSQL($query);
        self::assertSame('SELECT status, COUNT(*) FROM users GROUP BY status HAVING COUNT(*) > 1', $sql);
    }

    public function testSelectWithOrderBy(): void
    {
        $query = $this->makeQuery(columns: ['id', 'name'], from: ['users'], orderBy: ['id ASC', 'name DESC']);
        $sql   = $this->builder->buildSQL($query);
        self::assertSame('SELECT id, name FROM users ORDER BY id ASC, name DESC', $sql);
    }

    public function testSelectWithLimit(): void
    {
        $query = $this->makeQuery(columns: ['id'], from: ['users'], maxResults: 10);
        $sql   = $this->builder->buildSQL($query);
        // Firebird uses ROWS syntax for LIMIT
        self::assertStringContainsString('ROWS', $sql);
        self::assertStringContainsString('10', $sql);
    }

    public function testSelectWithLimitAndOffset(): void
    {
        $query = $this->makeQuery(columns: ['id'], from: ['users'], maxResults: 10, firstResult: 20);
        $sql   = $this->builder->buildSQL($query);
        // Firebird: ROWS (firstResult+1) TO (firstResult+maxResults) = ROWS 21 TO 30
        self::assertStringContainsString('21', $sql);
        self::assertStringContainsString('30', $sql);
    }

    public function testSelectWithForUpdate(): void
    {
        $forUpdate = new ForUpdate(ConflictResolutionMode::ORDINARY);
        $query     = $this->makeQuery(columns: ['id'], from: ['users'], forUpdate: $forUpdate);
        $sql       = $this->builder->buildSQL($query);
        self::assertStringContainsString('WITH LOCK', $sql);
    }

    public function testSelectForUpdateNotSupportedThrowsException(): void
    {
        // Build a builder without forUpdateSQL support
        $builder   = new FirebirdSelectSQLBuilder($this->platform, null, null);
        $forUpdate = new ForUpdate(ConflictResolutionMode::ORDINARY);
        $query     = $this->makeQuery(columns: ['id'], from: ['users'], forUpdate: $forUpdate);

        $this->expectException(Exception::class);
        $builder->buildSQL($query);
    }

    public function testSelectSkipLockedNotSupportedThrowsException(): void
    {
        // Default builder has skipLockedSQL = null
        $forUpdate = new ForUpdate(ConflictResolutionMode::SKIP_LOCKED);
        $query     = $this->makeQuery(columns: ['id'], from: ['users'], forUpdate: $forUpdate);

        $this->expectException(Exception::class);
        $this->builder->buildSQL($query);
    }

    public function testSelectWithNoFrom(): void
    {
        $query = $this->makeQuery(columns: ['1 + 1']);
        $sql   = $this->builder->buildSQL($query);
        self::assertSame('SELECT 1 + 1', $sql);
    }

    public function testLikeCastLengthIsConfigurable(): void
    {
        // Use platform with custom like_cast_length
        $platform = new Firebird3Platform();
        $platform->setConfiguration(
            new \Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration(['like_cast_length' => 4000]),
        );
        $builder = new FirebirdSelectSQLBuilder($platform, 'WITH LOCK', null);
        $query   = $this->makeQuery(columns: ['id'], from: ['t'], where: 'col LIKE ?');
        $sql     = $builder->buildSQL($query);
        self::assertStringContainsString('CAST(col AS VARCHAR(4000)) LIKE ?', $sql);
    }

    public function testSelectSkipLockedWhenSupported(): void
    {
        // Builder with non-null skipLockedSQL covers line 92 (the $sql .= ' ' . $this->skipLockedSQL path)
        $builder   = new FirebirdSelectSQLBuilder($this->platform, 'WITH LOCK', 'SKIP LOCKED');
        $forUpdate = new ForUpdate(ConflictResolutionMode::SKIP_LOCKED);
        $query     = $this->makeQuery(columns: ['id'], from: ['users'], forUpdate: $forUpdate);
        $sql       = $builder->buildSQL($query);

        self::assertStringContainsString('WITH LOCK', $sql);
        self::assertStringContainsString('SKIP LOCKED', $sql);
    }
}
