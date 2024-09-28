<?php
namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;


/* @covers \Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter */
class ExceptionConverterTest extends FunctionalTestCase
{
    use VerifyDeprecations;
    private ExceptionConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ExceptionConverter();
    }

    public function testConvertSyntaxError()
    {
        $this->expectException(SyntaxErrorException::class);
        $query = $this->connection->executeQuery('INVALID SQL'); // Assumed method to create a query
    }


    public function testConvertTableNotFound()
    {
        $this->expectException(TableNotFoundException::class);
        $query = $this->connection->executeQuery(
            'SELECT * FROM non_existent_table'
        ); // Assumed method to create a query

    }

    public function testConvertInvalidFieldName()
    {
        $this->expectException(InvalidFieldNameException::class);
        $query = $this->connection->executeQuery('SELECT unknown_column FROM RDB$DATABASE');

    }

    public function testConvertForeignKeyConstraintViolation()
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->connection->executeQuery('CREATE TABLE parent_table (id INT PRIMARY KEY)');
        $this->connection->executeQuery(
            'CREATE TABLE child_table (id INT, parent_id INT, FOREIGN KEY (parent_id) REFERENCES parent_table (id))'
        );
        $query = $this->connection->executeQuery(
            'INSERT INTO child_table (parent_id) VALUES (999)'
        ); // Assuming 999 does not exist in parent_table
    }

    public function testConvertTableExistsException()
    {
        $this->expectException(TableExistsException::class);
        $this->connection->executeQuery('CREATE TABLE existing_table (id INT)');
        $query = $this->connection->executeQuery(
            'CREATE TABLE existing_table (id INT)'
        ); // Attempt to create the same table again
    }

    public function testConvertUniqueConstraintViolationException()
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->executeQuery('CREATE TABLE unique_table (id INT, unique_field INT UNIQUE)');
        $this->connection->executeQuery('INSERT INTO unique_table (unique_field) VALUES (1)');
        $query = $this->connection->executeQuery(
            'INSERT INTO unique_table (unique_field) VALUES (1)'
        ); // Attempt to insert duplicate value
    }

    public function testConvertNotNullConstraintViolationException()
    {
        // Ensure any existing instance of the table is dropped
        $this->dropTableIfExists('notnull_table');

        $this->expectException(NotNullConstraintViolationException::class);

        // Create the table
        $this->connection->executeQuery('CREATE TABLE notnull_table (id INT, notnull_field INT NOT NULL)');

        // Attempt to insert NULL into NOT NULL field

            $this->connection->exec(
                'INSERT INTO notnull_table (notnull_field) VALUES (NULL)'
            );

        // Optionally clean up after the test
        $this->dropTableIfExists('notnull_table');
    }

    public function testConvertDeadlockException()
    {
        $anotherConnection = TestUtil::getConnection(); // Added method to get another connection
        $this->dropTableIfExists('deadlock_table');
        $this->connection->executeQuery('CREATE TABLE deadlock_table (id INT, test INT NOT NULL)');

        $this->expectException(DeadlockException::class);
        $this->connection->insert('deadlock_table', ['id' => 1, 'test' => 1], [Types::INTEGER, Types::INTEGER]);
        $this->connection->insert('deadlock_table', ['id' => 2, 'test' => 2], [Types::INTEGER, Types::INTEGER]);

        $this->connection->beginTransaction();

        $anotherConnection->beginTransaction();
        $anotherConnection->executeQuery('UPDATE deadlock_table SET test = 2 where id = 1'); // This should cause a deadlock
        try {
            $this->connection->executeQuery('UPDATE deadlock_table SET test = 1 where id = 1'); // This should cause a deadlock
        } catch (\Exception $exception) {

            $anotherConnection =  null;
            throw $exception;
        }
    }
}
