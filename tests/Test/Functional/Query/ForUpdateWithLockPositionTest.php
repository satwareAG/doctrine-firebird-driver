<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Query;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function strpos;

/**
 * Test for Issue #17: WITH LOCK must be positioned AFTER ORDER BY and ROWS clauses
 *
 * Firebird expects: SELECT ... FROM ... ORDER BY ... ROWS ... WITH LOCK
 * Bug was: SELECT ... FROM ... WITH LOCK ORDER BY ...
 */
final class ForUpdateWithLockPositionTest extends FunctionalTestCase
{
    /**
     * Test that WITH LOCK is positioned correctly in SQL
     * Expected pattern: ...ORDER BY... ROWS... WITH LOCK
     */
    public function testWithLockPositionedAfterOrderByAndRows(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('test_table')
            ->orderBy('id', 'ASC')
            ->setMaxResults(1)
            ->forUpdate();

        $sql = $qb->getSQL();

        // Verify SQL contains expected clauses in correct order
        self::assertStringContainsString('ORDER BY', $sql, 'SQL should contain ORDER BY');
        self::assertStringContainsString('ROWS', $sql, 'SQL should contain ROWS (from LIMIT)');
        self::assertStringContainsString('WITH LOCK', $sql, 'SQL should contain WITH LOCK');

        // Verify ORDER BY comes before ROWS
        $orderByPos = strpos($sql, 'ORDER BY');
        $rowsPos    = strpos($sql, 'ROWS');
        self::assertLessThan($rowsPos, $orderByPos, 'ORDER BY must come before ROWS');

        // Verify ROWS comes before WITH LOCK (this is the critical test for issue #17)
        $withLockPos = strpos($sql, 'WITH LOCK');
        self::assertLessThan($withLockPos, $rowsPos, 'ROWS must come before WITH LOCK');

        // Overall order verification: ORDER BY < ROWS < WITH LOCK
        self::assertLessThan($rowsPos, $orderByPos, 'ORDER BY must be before ROWS');
        self::assertLessThan($withLockPos, $rowsPos, 'ROWS must be before WITH LOCK');
    }

    /**
     * Test the exact scenario from issue #17
     * Query: messenger_messages with ORDER BY available_at + ROWS 1 TO 1 + WITH LOCK
     */
    public function testMessengerMessagesScenario(): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('m.*')
            ->from('messenger_messages', 'm')
            ->orderBy('available_at', 'ASC')
            ->setMaxResults(1)
            ->forUpdate();

        $sql = $qb->getSQL();

        // Expected pattern: SELECT m.* FROM messenger_messages m ORDER BY available_at ASC ROWS 1 TO 1 WITH LOCK
        $expectedPattern = '/ORDER BY.*ROWS.*WITH LOCK/s';
        self::assertMatchesRegularExpression(
            $expectedPattern,
            $sql,
            'SQL must have ORDER BY before ROWS before WITH LOCK',
        );

        // Ensure WITH LOCK is NOT before ORDER BY (the bug reported in #17)
        $wrongPattern = '/WITH LOCK.*ORDER BY/s';
        self::assertDoesNotMatchRegularExpression(
            $wrongPattern,
            $sql,
            'SQL must NOT have WITH LOCK before ORDER BY (Issue #17 bug)',
        );
    }

    protected function setUp(): void
    {
        $table1 = new Table('test_table');
        $table1->addColumn('id', Types::INTEGER);
        $table1->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table1);

        $table2 = new Table('messenger_messages');
        $table2->addColumn('id', Types::INTEGER);
        $table2->addColumn('available_at', Types::DATETIME_MUTABLE);
        $table2->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table2);

        // Insert test data
        $this->connection->insert('test_table', ['id' => 1]);
        $this->connection->insert('test_table', ['id' => 2]);
    }
}
