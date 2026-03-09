<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Statement;

/**
 * Unit tests for Statement class.
 *
 * Tests cover:
 * - Constructor behavior with valid/invalid resources
 * - Destructor cleanup
 * - bindValue() with various parameter types
 * - bindParam() deprecated functionality
 * - SQL detection methods (DML, INSERT, RETURNING)
 * - Parameter binding error handling
 */
#[CoversClass(Statement::class)]
class StatementTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        // Connection is final, so use reflection to create instance without constructor
        $reflection = new ReflectionClass(Connection::class);
        $this->connection = $reflection->newInstanceWithoutConstructor();
    }

    // ====================================
    // Constructor Tests
    // ====================================

    #[Test]
    public function constructorWithValidStatementResource(): void
    {
        // Create a fake resource to simulate a prepared statement
        // Using a stream resource as a stand-in since we can't create actual Firebird resources
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);

        // With valid resource, constructor skips checkLastApiCall
        $statement = new Statement($this->connection, $resource, [], 'SELECT 1');

        $this->assertInstanceOf(Statement::class, $statement);

        fclose($resource);
    }

    #[Test]
    public function constructorWithFalseStatementCallsCheckLastApiCall(): void
    {
        // When statement is false, checkLastApiCall is called
        // We verify the Statement is still created (checkLastApiCall doesn't throw
        // with a reflection-created Connection that has no fbird handle)
        $statement = new Statement($this->connection, false, [], '');

        // Verify the statement was stored (even though it's false)
        $reflection = new ReflectionClass($statement);
        $statementProp = $reflection->getProperty('statement');
        $this->assertFalse($statementProp->getValue($statement));
    }

    #[Test]
    public function constructorWithNullStatementCallsCheckLastApiCall(): void
    {
        // When statement is null, checkLastApiCall is called
        // We verify the Statement is still created (checkLastApiCall doesn't throw
        // with a reflection-created Connection that has no fbird handle)
        $statement = new Statement($this->connection, null, [], '');

        // Verify the statement was stored (as null)
        $reflection = new ReflectionClass($statement);
        $statementProp = $reflection->getProperty('statement');
        $this->assertNull($statementProp->getValue($statement));
    }

    #[Test]
    public function constructorWithDmlSqlSetsFlagsCorrectly(): void
    {
        $resource = fopen('php://memory', 'r+');
        
        $statement = new Statement(
            $this->connection,
            $resource,
            [],
            'INSERT INTO test (id) VALUES (1) RETURNING id'
        );

        // Verify flags via reflection
        $reflection = new ReflectionClass($statement);
        
        $isDmlProp = $reflection->getProperty('isDml');
        $this->assertTrue($isDmlProp->getValue($statement));

        $isInsertProp = $reflection->getProperty('isInsert');
        $this->assertTrue($isInsertProp->getValue($statement));

        $hasReturningProp = $reflection->getProperty('hasReturning');
        $this->assertTrue($hasReturningProp->getValue($statement));

        fclose($resource);
    }

    // ====================================
    // bindValue Tests
    // ====================================

    #[Test]
    public function bindValueWithPositionalParameterSuccess(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $result = $statement->bindValue(1, 'test_value', ParameterType::STRING);

        $this->assertTrue($result);
    }

    #[Test]
    public function bindValueWithNamedParameterSuccess(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => ':name']);

        $result = $statement->bindValue(':name', 'test_value', ParameterType::STRING);

        $this->assertTrue($result);
    }

    #[Test]
    public function bindValueWithInvalidPositionalParameterThrowsException(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Positional Parameter 99 not found in the parameter map');

        $statement->bindValue(99, 'value', ParameterType::STRING);
    }

    #[Test]
    public function bindValueWithInvalidNamedParameterThrowsException(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => ':name']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Named Parameter :invalid not found in the parameter map');

        $statement->bindValue(':invalid', 'value', ParameterType::STRING);
    }

    #[Test]
    #[DataProvider('parameterTypeProvider')]
    public function bindValueWithDifferentTypes(int $type, mixed $value): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $result = $statement->bindValue(1, $value, $type);

        $this->assertTrue($result);
    }

    /**
     * @return array<string, array{int, mixed}>
     */
    public static function parameterTypeProvider(): array
    {
        return [
            'string' => [ParameterType::STRING, 'test_string'],
            'integer' => [ParameterType::INTEGER, 42],
            'null' => [ParameterType::NULL, null],
            'boolean true' => [ParameterType::BOOLEAN, true],
            'boolean false' => [ParameterType::BOOLEAN, false],
            'binary' => [ParameterType::BINARY, 'binary_data'],
            'ascii' => [ParameterType::ASCII, 'ascii_text'],
        ];
    }

    #[Test]
    public function bindValueOverwritesPreviousBinding(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $statement->bindValue(1, 'first_value', ParameterType::STRING);
        $result = $statement->bindValue(1, 'second_value', ParameterType::STRING);

        $this->assertTrue($result);
        
        // Verify via reflection that the new value is stored
        $reflection = new ReflectionClass($statement);
        $boundValuesProp = $reflection->getProperty('boundValues');
        $boundValues = $boundValuesProp->getValue($statement);
        
        $this->assertSame('second_value', $boundValues[1]);
    }

    #[Test]
    public function bindValueWithLargeObjectConvertsStreamToString(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);
        
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'stream_content');
        rewind($stream);

        $result = $statement->bindValue(1, $stream, ParameterType::LARGE_OBJECT);

        $this->assertTrue($result);
        
        // Verify the stream was converted to string
        $reflection = new ReflectionClass($statement);
        $bindingsProp = $reflection->getProperty('queryParamBindings');
        $bindings = $bindingsProp->getValue($statement);
        
        // The binding should now be a string
        $this->assertSame('stream_content', $bindings[1]);
    }

    #[Test]
    public function bindValueWithLargeObjectNullValue(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $result = $statement->bindValue(1, null, ParameterType::LARGE_OBJECT);

        $this->assertTrue($result);
    }

    // ====================================
    // bindParam Tests (Deprecated)
    // ====================================

    #[Test]
    public function bindParamWithPositionalParameter(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $variable = 'test_value';
        $result = @$statement->bindParam(1, $variable, ParameterType::STRING);

        $this->assertTrue($result);
    }

    #[Test]
    public function bindParamStoresReference(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $variable = 'initial_value';
        @$statement->bindParam(1, $variable, ParameterType::STRING);

        // Change the variable
        $variable = 'changed_value';

        // Verify the bound value reflects the change (by reference)
        $reflection = new ReflectionClass($statement);
        $boundValuesProp = $reflection->getProperty('boundValues');
        $boundValues = $boundValuesProp->getValue($statement);
        
        $this->assertSame('changed_value', $boundValues[1]);
    }

    #[Test]
    public function bindParamWithInvalidParameterThrowsException(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Positional Parameter 99 not found');

        $variable = 'value';
        @$statement->bindParam(99, $variable, ParameterType::STRING);
    }

    // ====================================
    // SQL Detection Methods Tests
    // ====================================

    #[Test]
    #[DataProvider('dmlStatementProvider')]
    public function detectDmlStatementRecognizesCorrectly(string $sql, bool $expected): void
    {
        $method = $this->getPrivateMethod('detectDmlStatement');
        $statement = $this->createStatementWithoutConstructor();

        $result = $method->invoke($statement, $sql);

        $this->assertSame($expected, $result, "Failed for SQL: $sql");
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function dmlStatementProvider(): array
    {
        return [
            // DML statements (should return true)
            'simple insert' => ['INSERT INTO test VALUES (1)', true],
            'insert with columns' => ['INSERT INTO test (col) VALUES (1)', true],
            'update' => ['UPDATE test SET col = 1', true],
            'delete' => ['DELETE FROM test WHERE id = 1', true],
            'merge' => ['MERGE INTO test USING source ON test.id = source.id', true],
            'execute procedure' => ['EXECUTE PROCEDURE my_proc', true],
            'insert with leading whitespace' => ['  INSERT INTO test VALUES (1)', true],
            'insert with comment' => ['/* comment */ INSERT INTO test VALUES (1)', true],
            'insert with newlines' => ["\n\nINSERT INTO test VALUES (1)", true],
            'insert lowercase' => ['insert into test values (1)', true],
            'insert mixed case' => ['InSeRt INTO test VALUES (1)', true],
            'with clause insert' => ['WITH cte AS (SELECT 1) INSERT INTO test SELECT * FROM cte', true],
            'create table' => ['CREATE TABLE test (id INT)', true],
            'drop table' => ['DROP TABLE test', true],
            'alter table' => ['ALTER TABLE test ADD col INT', true],
            'recreate table' => ['RECREATE TABLE test (id INT)', true],

            // DDL statements (should return true for metadata commit)
            'create index' => ['CREATE INDEX idx_test ON test (id)', true],
            'drop index' => ['DROP INDEX idx_test', true],
            'create view' => ['CREATE VIEW v_test AS SELECT * FROM test', true],
            'drop view' => ['DROP VIEW v_test', true],
            'create procedure' => ['CREATE PROCEDURE p_test AS BEGIN END', true],
            'drop procedure' => ['DROP PROCEDURE p_test', true],
            'create trigger' => ['CREATE TRIGGER t_test FOR test BEFORE INSERT AS BEGIN END', true],
            'drop trigger' => ['DROP TRIGGER t_test', true],
            'create generator' => ['CREATE GENERATOR gen_test', true],
            'drop generator' => ['DROP GENERATOR gen_test', true],
            'create domain' => ['CREATE DOMAIN d_test AS INT', true],
            'drop domain' => ['DROP DOMAIN d_test', true],

            // Non-DML statements (should return false)
            'select' => ['SELECT * FROM test', false],
            'select with subquery insert' => ['SELECT * FROM (INSERT INTO test VALUES (1))', false],
            'empty string' => ['', false],
            'whitespace only' => ['   ', false],
        ];
    }

    #[Test]
    #[DataProvider('insertStatementProvider')]
    public function detectInsertStatementRecognizesCorrectly(string $sql, bool $expected): void
    {
        $method = $this->getPrivateMethod('detectInsertStatement');
        $statement = $this->createStatementWithoutConstructor();

        $result = $method->invoke($statement, $sql);

        $this->assertSame($expected, $result, "Failed for SQL: $sql");
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function insertStatementProvider(): array
    {
        return [
            // INSERT statements (should return true)
            'simple insert' => ['INSERT INTO test VALUES (1)', true],
            'insert with columns' => ['INSERT INTO test (col) VALUES (1)', true],
            'insert lowercase' => ['insert into test values (1)', true],
            'insert with comment' => ['/* comment */ INSERT INTO test VALUES (1)', true],
            'with clause insert' => ['WITH cte AS (SELECT 1) INSERT INTO test SELECT * FROM cte', true],
            
            // Non-INSERT statements (should return false)
            'update' => ['UPDATE test SET col = 1', false],
            'delete' => ['DELETE FROM test', false],
            'select' => ['SELECT * FROM test', false],
            'select with insert in string' => ["SELECT 'INSERT' FROM test", false],
        ];
    }

    #[Test]
    #[DataProvider('returningClauseProvider')]
    public function detectReturningClauseRecognizesCorrectly(string $sql, bool $expected): void
    {
        $method = $this->getPrivateMethod('detectReturningClause');
        $statement = $this->createStatementWithoutConstructor();

        $result = $method->invoke($statement, $sql);

        $this->assertSame($expected, $result, "Failed for SQL: $sql");
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function returningClauseProvider(): array
    {
        return [
            // With RETURNING clause (should return true)
            'insert with returning' => ['INSERT INTO test (col) VALUES (1) RETURNING id', true],
            'update with returning' => ['UPDATE test SET col = 1 RETURNING col', true],
            'delete with returning' => ['DELETE FROM test WHERE id = 1 RETURNING id', true],
            'returning lowercase' => ['insert into test values (1) returning id', true],
            'returning with multiple columns' => ['INSERT INTO test VALUES (1) RETURNING id, col1, col2', true],
            
            // Without RETURNING clause (should return false)
            'simple insert' => ['INSERT INTO test VALUES (1)', false],
            'simple update' => ['UPDATE test SET col = 1', false],
            'simple delete' => ['DELETE FROM test', false],
            'select' => ['SELECT * FROM test', false],
        ];
    }

    // ====================================
    // Destructor Tests
    // ====================================

    #[Test]
    public function destructorHandlesNullStatementProperty(): void
    {
        // Create statement via reflection to test destructor with null statement
        $statement = $this->createStatementWithoutConstructor();
        
        // Set statement property to null via reflection
        $reflection = new ReflectionClass($statement);
        $statementProp = $reflection->getProperty('statement');
        $statementProp->setValue($statement, null);
        
        // Destructor should handle null gracefully
        unset($statement);
        
        $this->assertTrue(true); // If we get here, destructor didn't throw
    }

    // ====================================
    // Parameter Map Tests
    // ====================================

    #[Test]
    public function bindValueWithMultiplePositionalParameters(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?', 2 => '?', 3 => '?']);

        $this->assertTrue($statement->bindValue(1, 'value1', ParameterType::STRING));
        $this->assertTrue($statement->bindValue(2, 42, ParameterType::INTEGER));
        $this->assertTrue($statement->bindValue(3, null, ParameterType::NULL));
    }

    #[Test]
    public function bindValueWithMultipleNamedParameters(): void
    {
        $statement = $this->createStatementWithParameterMap([
            1 => ':name',
            2 => ':value',
            3 => ':active',
        ]);

        $this->assertTrue($statement->bindValue(':name', 'John', ParameterType::STRING));
        $this->assertTrue($statement->bindValue(':value', 100, ParameterType::INTEGER));
        $this->assertTrue($statement->bindValue(':active', true, ParameterType::BOOLEAN));
    }

    // ====================================
    // Edge Cases
    // ====================================

    #[Test]
    public function bindValueWithEmptyString(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $result = $statement->bindValue(1, '', ParameterType::STRING);

        $this->assertTrue($result);
    }

    #[Test]
    public function bindValueWithZero(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $result = $statement->bindValue(1, 0, ParameterType::INTEGER);

        $this->assertTrue($result);
    }

    #[Test]
    public function bindValueWithNegativeNumber(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $result = $statement->bindValue(1, -42, ParameterType::INTEGER);

        $this->assertTrue($result);
    }

    #[Test]
    public function bindValueWithFloatValue(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $result = $statement->bindValue(1, 3.14159, ParameterType::STRING);

        $this->assertTrue($result);
    }

    #[Test]
    public function bindValueWithLargeString(): void
    {
        $statement = $this->createStatementWithParameterMap([1 => '?']);

        $largeString = str_repeat('x', 100000);
        $result = $statement->bindValue(1, $largeString, ParameterType::STRING);

        $this->assertTrue($result);
    }

    // ====================================
    // Helper Methods
    // ====================================

    /**
     * Create a Statement instance with a specific parameter map.
     *
     * @param array<int|string, string> $parameterMap
     */
    private function createStatementWithParameterMap(array $parameterMap): Statement
    {
        $resource = fopen('php://memory', 'r+');
        
        return new Statement(
            $this->connection,
            $resource,
            $parameterMap,
            'SELECT 1'
        );
    }

    /**
     * Create a Statement instance without calling constructor (for testing private methods).
     */
    private function createStatementWithoutConstructor(): Statement
    {
        $reflection = new ReflectionClass(Statement::class);
        /** @var Statement $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        
        return $instance;
    }

    /**
     * Get a private method for testing via reflection.
     */
    private function getPrivateMethod(string $methodName): ReflectionMethod
    {
        $method = new ReflectionMethod(Statement::class, $methodName);

        return $method;
    }
}
