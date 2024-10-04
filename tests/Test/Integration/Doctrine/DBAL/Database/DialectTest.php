<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Throwable;

use function number_format;
use function rtrim;
use function sprintf;
use function strlen;

/**
 * Tests based on table from:
 *
 * @link https://www.firebirdsql.org/pdfmanual/html/isql-dialects.html
 */
class DialectTest extends AbstractIntegrationTestCase
{
    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function testDialect0And3(): void
    {
        foreach ([0, 3] as $dialect) {
            $connection = $this->reConnect(['dialect' => $dialect]);

            $stmt   = $connection->prepare("SELECT CAST(CAST('2018-01-01' AS DATE) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $result = $stmt->executeQuery()->fetchAssociative();

            self::assertSame(100, strlen((string) $result['TXT']));
            self::assertStringStartsWith('2018-01-01', $result['TXT']);

            $stmt   = $connection->prepare("SELECT CAST(CAST('2018-01-01 00:00:00' AS TIMESTAMP) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $result = $stmt->executeQuery()->fetchAssociative();
            self::assertSame(100, strlen((string) $result['TXT']));
            self::assertSame('2018-01-01 00:00:00.0000', rtrim((string) $result['TXT']));

            $stmt   = $connection->prepare("SELECT CAST(CAST('00:00:00' AS TIME) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $result = $stmt->executeQuery()->fetchAssociative();
            self::assertSame(100, strlen((string) $result['TXT']));
            self::assertSame('00:00:00.0000', rtrim((string) $result['TXT']));

            $stmt   = $connection->prepare('SELECT a."ID" FROM Album AS a');
            $result = $stmt->executeQuery()->fetchAssociative();
            self::assertIsArray($result);
            self::assertArrayHasKey('ID', $result);
            self::assertSame(1, $result['ID']);

            $stmt   = $connection->prepare('SELECT 1/3 AS NUMBER FROM RDB$DATABASE');
            $result = $stmt->executeQuery()->fetchAssociative();
            self::assertIsArray($result);
            self::assertArrayHasKey('NUMBER', $result);
            self::assertIsInt($result['NUMBER']);
            self::assertSame(0, $result['NUMBER']);
            $connection->close();
        }
    }

    public function testDialect1(): void
    {
        if ($this->_platform !== 'Firebird') {
            $this->markTestSkipped(sprintf('Platform %s (for DB %s) is not supported yet', $this->_platform->getName(), $this->connection->getDatabase()));
        }

        $connection = $this->reConnect(['dialect' => 1]);
        $stmt       = $connection->prepare("SELECT CAST(CAST('2018-01-01' AS DATE) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        $result     = $stmt->executeQuery()->fetchAssociative();
        self::assertSame(100, strlen((string) $result['TXT']));
        self::assertStringStartsWith('1-JAN-2018', $result['TXT']);

        $stmt   = $connection->prepare("SELECT CAST(CAST('2018-01-01 00:00:00' AS TIMESTAMP) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        $result = $stmt->executeQuery()->fetchAssociative();
        self::assertSame(100, strlen((string) $result['TXT']));
        self::assertSame('1-JAN-2018', rtrim((string) $result['TXT']));

        try {
            $stmt = $connection->prepare("SELECT CAST(CAST('00:00:00' AS TIME) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $stmt->executeQuery();
        } catch (Throwable $t) {
            self::assertSame(SyntaxErrorException::class, $t::class);
            self::assertStringStartsWith('An exception occurred while executing a query: ', $t->getMessage());
            self::assertIsObject($t->getPrevious());
            self::assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            self::assertStringStartsWith('Dynamic SQL Error SQL error code = -104 Client SQL dialect 1 does not support reference to TIME datatype ', $t->getPrevious()->getMessage());
        }

        try {
            $stmt = $connection->prepare('SELECT a."ID" FROM Album AS a');
            $stmt->executeQuery();
        } catch (Throwable $t) {
            self::assertSame(SyntaxErrorException::class, $t::class);
            self::assertStringStartsWith('An exception occurred while executing', $t->getMessage());
            self::assertIsObject($t->getPrevious());
            self::assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            self::assertStringStartsWith('Dynamic SQL Error SQL error code = -104 Token unknown - line 1, column 10 "ID"', $t->getPrevious()->getMessage());
        }

        $stmt   = $connection->prepare('SELECT 1/3 AS NUMBER FROM RDB$DATABASE');
        $result = $stmt->executeQuery()->fetchAssociative();
        self::assertIsArray($result);
        self::assertArrayHasKey('NUMBER', $result);
        self::assertIsFloat($result['NUMBER']);
        self::assertSame('0.33333333', number_format($result['NUMBER'], 8));
        $connection->close();
    }

    public function testDialect2(): void
    {
        $connection = $this->reConnect(['dialect' => 2]);

        try {
            $stmt = $connection->prepare("SELECT CAST(CAST('2018-01-01' AS DATE) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $stmt->executeQuery();
        } catch (Throwable $t) {
            self::assertSame(SyntaxErrorException::class, $t::class);
            self::assertStringStartsWith('An exception occurred while executing a query:', $t->getMessage());
            self::assertIsObject($t->getPrevious());
            self::assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            self::assertStringStartsWith('Dynamic SQL Error SQL error code = -104 DATE must be changed to TIMESTAMP ', $t->getPrevious()->getMessage());
        }

        $stmt   = $connection->prepare("SELECT CAST(CAST('2018-01-01 00:00:00' AS TIMESTAMP) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        $result = $stmt->executeQuery()->fetchAssociative();
        self::assertSame(100, strlen((string) $result['TXT']));
        self::assertSame('2018-01-01 00:00:00.0000', rtrim((string) $result['TXT']));

        $stmt = $connection->prepare("SELECT CAST(CAST('00:00:00' AS TIME) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        try {
            $stmt->executeQuery();
        } catch (Throwable $t) {
            self::assertSame(SyntaxErrorException::class, $t::class);
            self::assertStringStartsWith('Error -104: An exception occurred while executing ', $t->getMessage());
            self::assertIsObject($t->getPrevious());
            self::assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            self::assertStringStartsWith('Dynamic SQL Error SQL error code = -104 Client SQL dialect 1 does not support reference to TIME datatype', $t->getPrevious()->getMessage());
        }

        try {
            $stmt = $connection->prepare('SELECT a."ID" FROM Album AS a');
            $stmt->executeQuery();
        } catch (Throwable $t) {
            self::assertSame(SyntaxErrorException::class, $t::class);
            self::assertStringStartsWith('An exception occurred while executing a query: Dynamic SQL Error SQL error code = -104 a string constant is delimited by double quotes ', $t->getMessage());
            self::assertIsObject($t->getPrevious());
            self::assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            self::assertStringStartsWith('Dynamic SQL Error SQL error code = -104 a string constant is delimited by double quotes', $t->getPrevious()->getMessage());
        }

        $stmt   = $connection->prepare('SELECT 1/3 AS NUMBER FROM RDB$DATABASE');
        $result = $stmt->executeQuery()->fetchAssociative();
        self::assertIsArray($result);
        self::assertArrayHasKey('NUMBER', $result);
        self::assertIsInt($result['NUMBER']);
        self::assertSame(0, $result['NUMBER']);

        $connection->close();
    }

    public function setUp(): void
    {
        parent::setUp();
    }
}
