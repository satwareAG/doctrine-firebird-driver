<?php
namespace Satag\DoctrineFirebirdDriver\Test\Functional\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use RectorPrefix202409\SebastianBergmann\Diff\Exception;
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
        $this->expectException(DeadlockException::class);
        $queries = [
            'SET TRANSACTION WAIT',
            'SET TRANSACTION RESERVING RDB$DATABASE FOR PROTECTED WRITE;'
        ];
        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }
        $anotherConnection = TestUtil::getConnection(); // Added method to get another connection
        try {
            $anotherConnection->executeQuery(
                'LOCK TABLES RDB$DATABASE IN EXCLUSIVE MODE'
            ); // This should cause a deadlock
        } catch (\Exception $exception) {
            $anotherConnection->close();
            $this->connection->rollBack();
            throw $exception;
        }


        $anotherConnection = null;
    }

    public function testConvertLockWaitTimeoutException()
    {
        $this->expectException(LockWaitTimeoutException::class);
        $this->connection->beginTransaction();
        $this->connection->executeQuery('LOCK TABLES RDB$DATABASE IN EXCLUSIVE MODE'); // Lock table to cause a timeout
        // Setting a low timeout threshold for the test case
        $this->connection->executeQuery('SET TRANSACTION WAIT NO');
        $query = $this->connection->executeQuery(
            'LOCK TABLES RDB$DATABASE IN EXCLUSIVE MODE'
        ); // This should cause a lock wait timeout
    }
}
