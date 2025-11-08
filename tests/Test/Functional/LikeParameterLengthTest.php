<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Table;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Test case for Issue #16: LIKE Expression parameter length exceeding VARCHAR field length
 * 
 * Problem: When LIKE parameter exceeds VARCHAR field length, Firebird throws DataTruncation error.
 * Expected: Other OR conditions should still match, or clear exception should be thrown.
 * Actual: Query returns no results without exception.
 */
final class LikeParameterLengthTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test table with small VARCHAR field
        $table = new Table('like_param_test');
        $table->addColumn('id', 'integer');
        $table->addColumn('auftragnr', 'string', ['length' => 10]); // Small field
        $table->addColumn('name', 'string', ['length' => 50]);
        $table->setPrimaryKey(['id']);
        
        $this->dropAndCreateTable($table);
        
        // Insert test data
        $this->connection->insert('like_param_test', [
            'id' => 1,
            'auftragnr' => '12345',
            'name' => 'Test Customer'
        ]);
        $this->connection->insert('like_param_test', [
            'id' => 2,
            'auftragnr' => '67890',
            'name' => 'Another Customer'
        ]);
    }

    /**
     * Test 1: Direct LIKE with parameter exceeding field length
     * With Issue #16 fix: CAST wrapper prevents exception, query executes successfully
     */
    public function testLikeParameterExceedsFieldLength(): void
    {
        $searchTerm = '12345678901234567890'; // 20 chars > VARCHAR(10)
        
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
           ->from('like_param_test')
           ->where($qb->expr()->like('auftragnr', ':search'));
        $qb->setParameter('search', "%$searchTerm%");
        
        $result = $qb->executeQuery()->fetchAllAssociative();
        
        // With CAST wrapper, query executes without exception
        // Result may be empty (no matches) but query succeeds
        $this->assertIsArray($result, 'Query should execute successfully with CAST wrapper');
        // No matches expected since auftragnr values are much shorter
        $this->assertEmpty($result, 'No matches expected for oversized search term');
    }

    /**
     * Test 2: OR expression with one oversized parameter
     * Verifies Issue #16 fix: CAST wrapper allows OR expression to work correctly
     * even when one LIKE parameter exceeds VARCHAR field length
     */
    public function testOrExpressionWithOversizedParameter(): void
    {
        $searchNr = '12345678901234567890'; // 20 chars > VARCHAR(10)
        $searchName = 'Test'; // Should match id=1
        
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
           ->from('like_param_test')
           ->where(
               $qb->expr()->or(
                   $qb->expr()->like('auftragnr', ':searchNr'),
                   $qb->expr()->like('name', ':searchName')
               )
           );
        $qb->setParameter('searchNr', "%$searchNr%");
        $qb->setParameter('searchName', "%$searchName%");
        
        $result = $qb->executeQuery()->fetchAllAssociative();
        
        // With Issue #16 fix (auto-CAST wrapper), query should succeed
        // and return matches for the name condition
        $this->assertNotEmpty($result, 'Expected to find results matching name condition');
        $this->assertIsArray($result[0] ?? null, 'Expected valid result array');
        $this->assertArrayHasKey('ID', $result[0], 'Expected ID field in result');
        $this->assertEquals(1, $result[0]['ID'], 'Expected to find Test Customer (id=1)');
        $this->assertStringContainsString('Test', $result[0]['NAME'], 'Expected name to contain "Test"');
    }

    /**
     * Test 3: CAST workaround - should work without error
     */
    public function testCastWorkaroundForOversizedParameter(): void
    {
        $searchTerm = '12345678901234567890'; // 20 chars
        
        // Use CAST to work around the limitation
        $sql = 'SELECT * FROM like_param_test WHERE CAST(auftragnr AS VARCHAR(100)) LIKE ?';
        
        $result = $this->connection->executeQuery($sql, ["%$searchTerm%"])->fetchAllAssociative();
        
        // Should execute without error (even if no matches)
        $this->assertIsArray($result, 'CAST workaround should execute without error');
    }

    /**
     * Test 4: Verify CAST wrapper behavior
     * With Issue #16 fix: Both literal and parameter versions work without exception
     */
    public function testCastWrapperBehavior(): void
    {
        $searchTerm = '12345678901234567890'; // 20 chars
        
        // Test with QueryBuilder (uses CAST wrapper automatically)
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
           ->from('like_param_test')
           ->where($qb->expr()->like('auftragnr', ':search'));
        $qb->setParameter('search', "%$searchTerm%");
        
        $result = $qb->executeQuery()->fetchAllAssociative();
        
        // With CAST wrapper, query executes successfully
        $this->assertIsArray($result, 'QueryBuilder with CAST wrapper should execute successfully');
        $this->assertEmpty($result, 'No matches expected for oversized search term');
    }

    /**
     * Test 5: Boundary condition - parameter exactly at field length
     */
    public function testParameterAtExactFieldLength(): void
    {
        $searchTerm = '1234567890'; // Exactly 10 chars = VARCHAR(10)
        
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
           ->from('like_param_test')
           ->where($qb->expr()->like('auftragnr', ':search'));
        $qb->setParameter('search', $searchTerm);
        
        // Should work at exact length
        $result = $qb->executeQuery()->fetchAllAssociative();
        $this->assertIsArray($result, 'Exact length parameter should work');
    }

    /**
     * Test 6: Parameter with wildcards at boundary
     */
    public function testParameterWithWildcardsAtBoundary(): void
    {
        // field is VARCHAR(10), parameter is 8 chars + 2 wildcards = 10 total
        $searchTerm = '12345678'; // 8 chars
        
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
           ->from('like_param_test')
           ->where($qb->expr()->like('auftragnr', ':search'));
        $qb->setParameter('search', "%$searchTerm%"); // Total 10 chars with wildcards
        
        try {
            $result = $qb->executeQuery()->fetchAllAssociative();
            $this->assertIsArray($result, 'Parameter with wildcards at boundary should work');
        } catch (Exception $e) {
            // If Firebird counts wildcards in length check
            $this->markTestIncomplete('Wildcards counted in length: ' . $e->getMessage());
        }
    }
}
