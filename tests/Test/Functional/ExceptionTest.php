<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Satag\DoctrineFirebirdDriver\Test\TestUtil;
use Throwable;

use function array_merge;
use function chmod;
use function error_reporting;
use function exec;
use function file_exists;
use function posix_geteuid;
use function posix_getpwuid;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

use const E_ALL;
use const E_WARNING;
use const PHP_OS_FAMILY;

/** @psalm-import-type Params from DriverManager */
class ExceptionTest extends FunctionalTestCase
{
    private string $tableConstraint = 'con_err_tbl';
    private string $tableOwning     = 'own_tbl';

    public function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testPrimaryConstraintViolationException(): void
    {
        $table = new Table('duplicatekey_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->insert('duplicatekey_table', ['id' => 1]);

        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->connection->insert('duplicatekey_table', ['id' => 1]);
    }

    public function testTableNotFoundException(): void
    {
        $sql = 'SELECT * FROM unknown_table';

        $this->expectException(Exception\TableNotFoundException::class);
        $this->connection->executeQuery($sql);
    }

    public function testTableExistsException(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table         = new Table('alreadyexist_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);

        $this->expectException(Exception\TableExistsException::class);
        $schemaManager->createTable($table);
        $schemaManager->createTable($table);
    }

    public function testForeignKeyConstraintViolationExceptionOnInsert(): void
    {
        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->connection->insert($this->tableConstraint, ['id' => 1]);
            $this->connection->insert($this->tableOwning, ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->insert($this->tableOwning, ['id' => 2, 'constraint_id' => 2]);
        } catch (Exception\ForeignKeyConstraintViolationException | Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnUpdate(): void
    {
        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->connection->insert($this->tableConstraint, ['id' => 1]);
            $this->connection->insert($this->tableOwning, ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->update($this->tableConstraint, ['id' => 2], ['id' => 1]);
        } catch (Exception\ForeignKeyConstraintViolationException | Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnDelete(): void
    {
        $this->setUpForeignKeyConstraintViolationExceptionTest();

        try {
            $this->connection->insert($this->tableConstraint, ['id' => 1]);
            $this->connection->insert($this->tableOwning, ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->delete($this->tableConstraint, ['id' => 1]);
        } catch (Exception\ForeignKeyConstraintViolationException | Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    public function testForeignKeyConstraintViolationExceptionOnTruncate(): void
    {
        $this->setUpForeignKeyConstraintViolationExceptionTest();

        // Force fresh connection for this test
        $this->connection = TestUtil::getConnection();
        $platform         = $this->connection->getDatabasePlatform();

        try {
            $this->connection->insert($this->tableConstraint, ['id' => 1]);
            $this->connection->insert($this->tableOwning, ['id' => 1, 'constraint_id' => 1]);
        } catch (Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->expectException(Exception\ForeignKeyConstraintViolationException::class);

        try {
            $this->connection->executeStatement($platform->getTruncateTableSQL($this->tableConstraint));
        } catch (Exception\ForeignKeyConstraintViolationException | Throwable $exception) {
            $this->tearDownForeignKeyConstraintViolationExceptionTest();

            throw $exception;
        }

        $this->tearDownForeignKeyConstraintViolationExceptionTest();
    }

    /**
     * Tests that NOT NULL constraint violations are properly detected.
     *
     * IMPORTANT: This test uses explicit NULL in SQL string instead of parameter binding
     * because the php-firebird extension has a limitation where fbird_execute() with
     * bound NULL parameters bypasses Firebird's NOT NULL constraint validation.
     *
     * KNOWN LIMITATION:
     * - Parameter binding with NULL: Inserts garbage values (e.g., "-1073741823")
     * - Explicit NULL in SQL: Correctly throws NotNullConstraintViolationException
     *
     * This limitation affects:
     * - ALL Firebird versions (2.5, 3.0, 4.0, 5.0)
     * - ALL php-firebird extension versions (v3.0.1 through v6.1.1-RC.1)
     *
     * Root Cause:
     * The php-firebird extension's fbird_execute() function does not properly handle
     * bound NULL parameters. When NULL is passed as a bound parameter, Firebird's
     * NOT NULL constraint validation is bypassed, and uninitialized memory or default
     * values are inserted instead.
     *
     * Workaround:
     * Use executeStatement() with explicit NULL in the SQL string instead of
     * parameter binding for NULL values on NOT NULL columns.
     *
     * For comprehensive research findings and technical details, see:
     * docs/null-parameter-binding-limitation.md
     *
     * @see https://github.com/FirebirdSQL/php-firebird (php-firebird extension)
     */
    public function testNotNullConstraintViolationException(): void
    {
        $table = new Table('notnull_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->addColumn('val', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->expectException(Exception\NotNullConstraintViolationException::class);

        // WORKAROUND: Use explicit NULL in SQL string instead of parameter binding
        // Original code that DOESN'T WORK: $this->connection->insert('notnull_table', ['id' => 1, 'val' => null]);
        // Correctly triggers NOT NULL constraint violation:
        $this->connection->executeStatement(
            'INSERT INTO notnull_table (id, val) VALUES (1, NULL)',
        );
    }

    public function testInvalidFieldNameException(): void
    {
        $table = new Table('bad_columnname_table');
        $table->addColumn('id', Types::INTEGER, []);
        $this->dropAndCreateTable($table);

        // prevent the PHPUnit error handler from handling the warning that may be triggered
        $oldLevel = error_reporting(E_ALL & ~E_WARNING);

        try {
            $this->expectException(Exception\InvalidFieldNameException::class);
            $this->connection->insert('bad_columnname_table', ['name' => 5]);
        } finally {
            error_reporting($oldLevel);
        }
    }

    public function testNonUniqueFieldNameException(): void
    {
        $table1 = new Table('ambiguous_list_table_1');
        $table1->addColumn('id', Types::INTEGER);
        $this->dropAndCreateTable($table1);

        $table2 = new Table('ambiguous_list_table_2');
        $table2->addColumn('id', Types::INTEGER);
        $this->dropAndCreateTable($table2);

        $sql = 'SELECT id FROM ambiguous_list_table_1, ambiguous_list_table_2';
        $this->expectException(Exception\NonUniqueFieldNameException::class);
        $this->connection->executeQuery($sql);
    }

    public function testUniqueConstraintViolationException(): void
    {
        $table = new Table('unique_column_table');
        $table->addColumn('id', Types::INTEGER);
        $table->addUniqueIndex(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('unique_column_table', ['id' => 5]);
        $this->expectException(Exception\UniqueConstraintViolationException::class);
        $this->connection->insert('unique_column_table', ['id' => 5]);
    }

    public function testSyntaxErrorException(): void
    {
        $table = new Table('syntax_error_table');
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $sql = 'SELECT id FRO syntax_error_table';
        $this->expectException(Exception\SyntaxErrorException::class);
        $this->connection->executeQuery($sql);
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('getConnectionParams')]
    public function testConnectionException(array $params): void
    {
        $params = array_merge(TestUtil::getConnectionParams(), $params);
        $conn   = DriverManager::getConnection($params);

        $this->expectException(Exception\ConnectionException::class);
        $conn->connect();
    }

    /** @return array<int, array<int, mixed>> */
    public static function getConnectionParams(): Iterator
    {
        yield [['user' => 'not_existing']];
        yield [['password' => 'really_not']];
        yield [['host' => 'localnope']];
    }

    private function setUpForeignKeyConstraintViolationExceptionTest(): void
    {
        $this->tableConstraint = 'ce_' . uniqid();
        $this->tableOwning     = 'ot_' . uniqid();

        // Use a separate connection for setup to avoid lock contamination
        $setupConnection = TestUtil::getConnection();
        $schemaManager   = $setupConnection->createSchemaManager();

        // ... definition ...
        $table = new Table($this->tableConstraint);
        $table->addColumn('id', Types::INTEGER, []);
        $table->setPrimaryKey(['id']);

        $owningTable = new Table($this->tableOwning);
        $owningTable->addColumn('id', Types::INTEGER, []);
        $owningTable->addColumn('constraint_id', Types::INTEGER, []);
        $owningTable->setPrimaryKey(['id']);
        $owningTable->addForeignKeyConstraint($table, ['constraint_id'], ['id']);

        $schemaManager->createTable($table);
        $schemaManager->createTable($owningTable);

        // Let GC handle close to avoid accidental sharing issues
    }

    private function tearDownForeignKeyConstraintViolationExceptionTest(): void
    {
        // CRITICAL: First rollback the main test connection to release locks on the FK tables.
        // The main connection still holds locks from the FK violation exception, which would
        // cause "table is in use" warnings when the teardown connection tries to drop tables.
        $fbirdConnection = $this->getFirebirdConnection();
        if ($fbirdConnection !== null) {
            try {
                $fbirdConnection->rollBack();
            } catch (Throwable) {
                // Ignore rollback errors - may already be rolled back
            }
        }

        $teardownConnection = TestUtil::getConnection();
        $schemaManager      = $teardownConnection->createSchemaManager();

        try {
            @$schemaManager->dropTable($this->tableOwning);
        } catch (Throwable) {
        }

        try {
            @$schemaManager->dropTable($this->tableConstraint);
        } catch (Throwable) {
        }

        // Let GC handle close
    }

    private function isLinuxRoot(): bool
    {
        return PHP_OS_FAMILY !== 'Windows' && posix_getpwuid(posix_geteuid())['name'] === 'root';
    }

    private function cleanupReadOnlyFile(string $filename): void
    {
        if ($this->isLinuxRoot()) {
            exec(sprintf('chattr -i %s', $filename));
        }

        chmod($filename, 0200); // make the file writable again, so it can be removed on Windows
        unlink($filename);
    }
}
