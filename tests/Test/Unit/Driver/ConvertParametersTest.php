<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;

/**
 * Unit tests for ConvertParameters visitor.
 *
 * This class converts named parameters to positional placeholders
 * as required by Firebird's parameter binding.
 */
#[CoversClass(ConvertParameters::class)]
final class ConvertParametersTest extends TestCase
{
    // ===== Basic SQL Handling =====

    public function testAcceptOtherAppendsSQL(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('SELECT * FROM ');
        $converter->acceptOther('users');
        
        self::assertSame('SELECT * FROM users', $converter->getSQL());
    }

    public function testGetSQLReturnsEmptyStringWhenNothingAccepted(): void
    {
        $converter = new ConvertParameters();
        
        self::assertSame('', $converter->getSQL());
    }

    public function testGetParameterMapReturnsEmptyArrayWhenNoParameters(): void
    {
        $converter = new ConvertParameters();
        
        self::assertSame([], $converter->getParameterMap());
    }

    // ===== Positional Parameter Tests =====

    public function testAcceptPositionalParameterReplaceWithQuestionMark(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('SELECT * FROM users WHERE id = ');
        $converter->acceptPositionalParameter('?');
        
        self::assertSame('SELECT * FROM users WHERE id = ?', $converter->getSQL());
    }

    public function testAcceptPositionalParameterMapsToIndex(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptPositionalParameter('?');
        
        $paramMap = $converter->getParameterMap();
        self::assertArrayHasKey(1, $paramMap);
        self::assertSame('?', $paramMap[1]);
    }

    public function testMultiplePositionalParametersIncrementIndex(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('SELECT * FROM users WHERE id = ');
        $converter->acceptPositionalParameter('?');
        $converter->acceptOther(' AND status = ');
        $converter->acceptPositionalParameter('?');
        $converter->acceptOther(' AND role = ');
        $converter->acceptPositionalParameter('?');
        
        self::assertSame('SELECT * FROM users WHERE id = ? AND status = ? AND role = ?', $converter->getSQL());
        
        $paramMap = $converter->getParameterMap();
        self::assertCount(3, $paramMap);
        self::assertSame('?', $paramMap[1]);
        self::assertSame('?', $paramMap[2]);
        self::assertSame('?', $paramMap[3]);
    }

    // ===== Named Parameter Tests =====

    public function testAcceptNamedParameterConvertsToPositional(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('SELECT * FROM users WHERE id = ');
        $converter->acceptNamedParameter(':id');
        
        self::assertSame('SELECT * FROM users WHERE id = ?', $converter->getSQL());
    }

    public function testAcceptNamedParameterMapsToIndex(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptNamedParameter(':userId');
        
        $paramMap = $converter->getParameterMap();
        self::assertArrayHasKey(1, $paramMap);
        self::assertSame(':userId', $paramMap[1]);
    }

    public function testMultipleNamedParametersConvertToPositional(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('SELECT * FROM users WHERE name = ');
        $converter->acceptNamedParameter(':name');
        $converter->acceptOther(' AND email = ');
        $converter->acceptNamedParameter(':email');
        
        self::assertSame('SELECT * FROM users WHERE name = ? AND email = ?', $converter->getSQL());
        
        $paramMap = $converter->getParameterMap();
        self::assertCount(2, $paramMap);
        self::assertSame(':name', $paramMap[1]);
        self::assertSame(':email', $paramMap[2]);
    }

    // ===== Mixed Parameter Tests =====

    public function testMixedPositionalAndNamedParameters(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('SELECT * FROM users WHERE id = ');
        $converter->acceptPositionalParameter('?');
        $converter->acceptOther(' AND name = ');
        $converter->acceptNamedParameter(':name');
        $converter->acceptOther(' AND status = ');
        $converter->acceptPositionalParameter('?');
        
        self::assertSame('SELECT * FROM users WHERE id = ? AND name = ? AND status = ?', $converter->getSQL());
        
        $paramMap = $converter->getParameterMap();
        self::assertCount(3, $paramMap);
        self::assertSame('?', $paramMap[1]);
        self::assertSame(':name', $paramMap[2]);
        self::assertSame('?', $paramMap[3]);
    }

    // ===== Complex SQL Tests =====

    #[DataProvider('complexSqlProvider')]
    public function testComplexSqlConversion(array $fragments, string $expectedSql, int $expectedParamCount): void
    {
        $converter = new ConvertParameters();
        
        foreach ($fragments as $fragment) {
            if ($fragment['type'] === 'other') {
                $converter->acceptOther($fragment['sql']);
            } elseif ($fragment['type'] === 'positional') {
                $converter->acceptPositionalParameter($fragment['sql']);
            } elseif ($fragment['type'] === 'named') {
                $converter->acceptNamedParameter($fragment['sql']);
            }
        }
        
        self::assertSame($expectedSql, $converter->getSQL());
        self::assertCount($expectedParamCount, $converter->getParameterMap());
    }

    /** @return iterable<string, array{array<array{type: string, sql: string}>, string, int}> */
    public static function complexSqlProvider(): iterable
    {
        yield 'INSERT with named parameters' => [
            [
                ['type' => 'other', 'sql' => 'INSERT INTO users (name, email, created_at) VALUES ('],
                ['type' => 'named', 'sql' => ':name'],
                ['type' => 'other', 'sql' => ', '],
                ['type' => 'named', 'sql' => ':email'],
                ['type' => 'other', 'sql' => ', '],
                ['type' => 'named', 'sql' => ':created'],
                ['type' => 'other', 'sql' => ')'],
            ],
            'INSERT INTO users (name, email, created_at) VALUES (?, ?, ?)',
            3,
        ];

        yield 'UPDATE with positional parameters' => [
            [
                ['type' => 'other', 'sql' => 'UPDATE users SET name = '],
                ['type' => 'positional', 'sql' => '?'],
                ['type' => 'other', 'sql' => ' WHERE id = '],
                ['type' => 'positional', 'sql' => '?'],
            ],
            'UPDATE users SET name = ? WHERE id = ?',
            2,
        ];

        yield 'SELECT with subquery' => [
            [
                ['type' => 'other', 'sql' => 'SELECT * FROM users WHERE department_id IN (SELECT id FROM departments WHERE name = '],
                ['type' => 'named', 'sql' => ':dept'],
                ['type' => 'other', 'sql' => ')'],
            ],
            'SELECT * FROM users WHERE department_id IN (SELECT id FROM departments WHERE name = ?)',
            1,
        ];

        yield 'DELETE with multiple conditions' => [
            [
                ['type' => 'other', 'sql' => 'DELETE FROM logs WHERE created_at < '],
                ['type' => 'named', 'sql' => ':cutoff'],
                ['type' => 'other', 'sql' => ' AND severity = '],
                ['type' => 'positional', 'sql' => '?'],
            ],
            'DELETE FROM logs WHERE created_at < ? AND severity = ?',
            2,
        ];

        yield 'no parameters' => [
            [
                ['type' => 'other', 'sql' => 'SELECT COUNT(*) FROM users'],
            ],
            'SELECT COUNT(*) FROM users',
            0,
        ];
    }

    // ===== Edge Cases =====

    public function testEmptyOtherFragment(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('');
        
        self::assertSame('', $converter->getSQL());
    }

    public function testOnlyParameters(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptNamedParameter(':a');
        $converter->acceptNamedParameter(':b');
        
        self::assertSame('??', $converter->getSQL());
        self::assertCount(2, $converter->getParameterMap());
    }

    public function testParameterMapPreservesOriginalNamedParameter(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptNamedParameter(':user_id');
        $converter->acceptNamedParameter(':userName');
        $converter->acceptNamedParameter(':USER_EMAIL');
        
        $paramMap = $converter->getParameterMap();
        
        // Verify original names are preserved
        self::assertSame(':user_id', $paramMap[1]);
        self::assertSame(':userName', $paramMap[2]);
        self::assertSame(':USER_EMAIL', $paramMap[3]);
    }

    public function testLargeNumberOfParameters(): void
    {
        $converter = new ConvertParameters();
        
        $converter->acceptOther('INSERT INTO test VALUES (');
        
        for ($i = 1; $i <= 100; $i++) {
            if ($i > 1) {
                $converter->acceptOther(', ');
            }
            $converter->acceptPositionalParameter('?');
        }
        
        $converter->acceptOther(')');
        
        $paramMap = $converter->getParameterMap();
        self::assertCount(100, $paramMap);
        self::assertArrayHasKey(1, $paramMap);
        self::assertArrayHasKey(100, $paramMap);
    }
}
