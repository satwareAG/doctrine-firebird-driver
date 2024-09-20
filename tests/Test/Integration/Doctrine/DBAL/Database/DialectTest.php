<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Satag\DoctrineFirebirdDriver\Test\Functional\TestUtil;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

/**
 * Tests based on table from:
 * @link https://www.firebirdsql.org/pdfmanual/html/isql-dialects.html
 */
class DialectTest extends AbstractIntegrationTestCase
{
    /**
     * @override
     */
    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function testDialect0And3()
    {
        foreach ([0,3] as $dialect) {

            $connection = $this->reConnect([
                'dialect' => $dialect
            ]);

            $stmt = $connection->prepare("SELECT CAST(CAST('2018-01-01' AS DATE) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $result = $stmt->executeQuery()->fetchAssociative();

            $this->assertSame(100, strlen((string) $result['TXT']));
            $this->assertStringStartsWith("2018-01-01", $result['TXT']);

            $stmt = $connection->prepare("SELECT CAST(CAST('2018-01-01 00:00:00' AS TIMESTAMP) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $result = $stmt->executeQuery()->fetchAssociative();
            $this->assertSame(100, strlen((string) $result['TXT']));
            $this->assertSame("2018-01-01 00:00:00.0000", rtrim((string) $result['TXT']));

            $stmt = $connection->prepare("SELECT CAST(CAST('00:00:00' AS TIME) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $result = $stmt->executeQuery()->fetchAssociative();
            $this->assertSame(100, strlen((string) $result['TXT']));
            $this->assertSame("00:00:00.0000", rtrim((string) $result['TXT']));

            $stmt = $connection->prepare("SELECT a.\"ID\" FROM Album AS a");
            $result = $stmt->executeQuery()->fetchAssociative();
            $this->assertIsArray($result);
            $this->assertArrayHasKey("ID", $result);
            $this->assertSame(1, $result["ID"]);

            $stmt = $connection->prepare("SELECT 1/3 AS NUMBER FROM RDB\$DATABASE");
            $result = $stmt->executeQuery()->fetchAssociative();
            $this->assertIsArray($result);
            $this->assertArrayHasKey("NUMBER", $result);
            $this->assertIsInt($result["NUMBER"]);
            $this->assertSame(0, $result["NUMBER"]);
            $connection->close();
        }
    }

    public function testDialect1()
    {
        if($this->_platform !== 'Firebird') {
            $this->markTestSkipped(sprintf('Platform %s (for DB %s) is not supported yet', $this->_platform->getName(), $this->connection->getDatabase()));
        }
        $connection = $this->reConnect([
            'dialect' => 1
        ]);
        $stmt = $connection->prepare("SELECT CAST(CAST('2018-01-01' AS DATE) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        $result = $stmt->executeQuery()->fetchAssociative();
        $this->assertSame(100, strlen((string) $result['TXT']));
        $this->assertStringStartsWith("1-JAN-2018", $result['TXT']);

        $stmt = $connection->prepare("SELECT CAST(CAST('2018-01-01 00:00:00' AS TIMESTAMP) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        $result = $stmt->executeQuery()->fetchAssociative();
        $this->assertSame(100, strlen((string) $result['TXT']));
        $this->assertSame("1-JAN-2018", rtrim((string) $result['TXT']));

        try {
            $stmt = $connection->prepare("SELECT CAST(CAST('00:00:00' AS TIME) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $stmt->executeQuery();
        } catch (\Throwable $t) {
            $this->assertSame(SyntaxErrorException::class, $t::class);
            $this->assertStringStartsWith('An exception occurred while executing a query: ', $t->getMessage());
            $this->assertIsObject($t->getPrevious());
            $this->assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            $this->assertStringStartsWith('Dynamic SQL Error SQL error code = -104 Client SQL dialect 1 does not support reference to TIME datatype ', $t->getPrevious()->getMessage());
        }


        try {
            $stmt = $connection->prepare("SELECT a.\"ID\" FROM Album AS a");
            $stmt->executeQuery();
        } catch (\Throwable $t) {
            $this->assertSame(SyntaxErrorException::class, $t::class);
            $this->assertStringStartsWith('An exception occurred while executing', $t->getMessage());
            $this->assertIsObject($t->getPrevious());
            $this->assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            $this->assertStringStartsWith('Dynamic SQL Error SQL error code = -104 Token unknown - line 1, column 10 "ID"', $t->getPrevious()->getMessage());
        }

        $stmt = $connection->prepare("SELECT 1/3 AS NUMBER FROM RDB\$DATABASE");
        $result = $stmt->executeQuery()->fetchAssociative();
        $this->assertIsArray($result);
        $this->assertArrayHasKey("NUMBER", $result);
        $this->assertIsFloat($result["NUMBER"]);
        $this->assertSame("0.33333333", number_format($result["NUMBER"], 8));
        $connection->close();
    }

    public function testDialect2()
    {
        $connection = $this->reConnect(['dialect' => 2]);

        try {
            $stmt = $connection->prepare("SELECT CAST(CAST('2018-01-01' AS DATE) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
            $stmt->executeQuery();
        } catch (\Throwable $t) {
            $this->assertSame(SyntaxErrorException::class, $t::class);
            $this->assertStringStartsWith('An exception occurred while executing a query:', $t->getMessage());
            $this->assertIsObject($t->getPrevious());
            $this->assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            $this->assertStringStartsWith('Dynamic SQL Error SQL error code = -104 DATE must be changed to TIMESTAMP ', $t->getPrevious()->getMessage());
        }

        $stmt = $connection->prepare("SELECT CAST(CAST('2018-01-01 00:00:00' AS TIMESTAMP) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        $result = $stmt->executeQuery()->fetchAssociative();
        $this->assertSame(100, strlen((string) $result['TXT']));
        $this->assertSame("2018-01-01 00:00:00.0000", rtrim((string) $result['TXT']));

        $stmt = $connection->prepare("SELECT CAST(CAST('00:00:00' AS TIME) AS CHAR(25)) AS TXT FROM RDB\$DATABASE");
        try {
            $stmt->executeQuery();
        } catch (\Throwable $t) {
            $this->assertSame(SyntaxErrorException::class, $t::class);
            $this->assertStringStartsWith('Error -104: An exception occurred while executing ', $t->getMessage());
            $this->assertIsObject($t->getPrevious());
            $this->assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            $this->assertStringStartsWith('Dynamic SQL Error SQL error code = -104 Client SQL dialect 1 does not support reference to TIME datatype', $t->getPrevious()->getMessage());
        }


        try {
            $stmt = $connection->prepare("SELECT a.\"ID\" FROM Album AS a");
            $stmt->executeQuery();
        } catch (\Throwable $t) {
            $this->assertSame(SyntaxErrorException::class, $t::class);
            $this->assertStringStartsWith('An exception occurred while executing a query: Dynamic SQL Error SQL error code = -104 a string constant is delimited by double quotes ', $t->getMessage());
            $this->assertIsObject($t->getPrevious());
            $this->assertSame(\Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::class, $t->getPrevious()::class);
            $this->assertStringStartsWith('Dynamic SQL Error SQL error code = -104 a string constant is delimited by double quotes', $t->getPrevious()->getMessage());
        }

        $stmt = $connection->prepare("SELECT 1/3 AS NUMBER FROM RDB\$DATABASE");
        $result = $stmt->executeQuery()->fetchAssociative();
        $this->assertIsArray($result);
        $this->assertArrayHasKey("NUMBER", $result);
        $this->assertIsInt($result["NUMBER"]);
        $this->assertSame(0, $result["NUMBER"]);

        $connection->close();
    }



}
