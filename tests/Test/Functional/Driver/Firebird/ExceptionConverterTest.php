<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\Group;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/* @covers \Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter */
class ExceptionConverterTest extends FunctionalTestCase
{
    use VerifyDeprecations;

    private ExceptionConverter $converter;

    public function testConvertSyntaxError(): void
    {
        $this->expectException(SyntaxErrorException::class);
        $this->connection->executeQuery('INVALID SQL'); // Assumed method to create a query
    }

    public function testConvertTableNotFound(): void
    {
        $this->expectException(TableNotFoundException::class);
        $this->connection->executeQuery(
            'SELECT * FROM non_existent_table',
        ); // Assumed method to create a query
    }

    public function testConvertInvalidFieldName(): void
    {
        $this->expectException(InvalidFieldNameException::class);
        $this->connection->executeQuery('SELECT unknown_column FROM RDB$DATABASE');
    }

    public function testConvertForeignKeyConstraintViolation(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->connection->executeQuery('CREATE TABLE parent_table (id INT PRIMARY KEY)');
        $this->connection->executeQuery(
            'CREATE TABLE child_table (id INT, parent_id INT, FOREIGN KEY (parent_id) REFERENCES parent_table (id))',
        );
        $this->connection->executeQuery(
            'INSERT INTO child_table (parent_id) VALUES (999)',
        ); // Assuming 999 does not exist in parent_table
    }

    public function testConvertTableExistsException(): void
    {
        $this->expectException(TableExistsException::class);
        $this->connection->executeQuery('CREATE TABLE existing_table (id INT)');
        $this->connection->executeQuery(
            'CREATE TABLE existing_table (id INT)',
        ); // Attempt to create the same table again
    }

    public function testConvertUniqueConstraintViolationException(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->executeQuery('CREATE TABLE unique_table (id INT, unique_field INT UNIQUE)');
        $this->connection->executeQuery('INSERT INTO unique_table (unique_field) VALUES (1)');
        $this->connection->executeQuery(
            'INSERT INTO unique_table (unique_field) VALUES (1)',
        ); // Attempt to insert duplicate value
    }

    public function testConvertNotNullConstraintViolationException(): void
    {
        // Ensure any existing instance of the table is dropped
        $this->dropTableIfExists('notnull_constraint_table');

        $this->expectException(NotNullConstraintViolationException::class);

        // Create the table
        $this->connection->executeQuery('CREATE TABLE notnull_constraint_table (id INT, notnull_field INT NOT NULL)');

        // Attempt to insert NULL into NOT NULL field

            $this->connection->exec(
                'INSERT INTO notnull_constraint_table (notnull_field) VALUES (NULL)',
            );

        // Optionally clean up after the test
        $this->dropTableIfExists('notnull_constraint_table');
    }

    /**
     * Note: True deadlock simulation requires concurrent execution (circular wait):
     * - Transaction A locks row 1, wants row 2
     * - Transaction B locks row 2, wants row 1
     *
     * In single-threaded PHP, we can only create a lock wait scenario (not a circular deadlock).
     * Firebird's lock timeout is typically longer than PHPUnit's test timeout.
     *
     * This test is marked incomplete as true deadlock cannot be reliably simulated
     * in a single-threaded PHP process without pcntl_fork() or similar mechanisms.
     *
     * The ExceptionConverter IS tested indirectly:
     * - Code -913 maps to DeadlockException
     * - Code -901 with "transaction deadlock" maps to DeadlockException
     */
    #[Group('skip-on-ci')]
    public function testConvertDeadlockException(): void
    {
        $this->markTestIncomplete(
            'True deadlock (code -913) cannot be reliably simulated in single-threaded PHP. ' .
            'This test creates a lock wait scenario which times out before Firebird detects deadlock. ' .
            'The ExceptionConverter handling for -913 and "transaction deadlock" is verified via unit tests.',
        );
    }

    protected function setUp(): void
    {
        $this->converter = new ExceptionConverter();
    }
}
