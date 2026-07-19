<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetConnectionMiddleware;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetResultMiddleware;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetStatementMiddleware;

use function fopen;
use function fwrite;
use function mb_convert_encoding;
use function rewind;
use function fclose;

/**
 * Unit tests for CharsetMiddleware, CharsetConnectionMiddleware, and CharsetStatementMiddleware.
 */
class CharsetMiddlewareTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // CharsetMiddleware
    // ---------------------------------------------------------------------------

    public function testCharsetMiddlewareDefaultEncodings(): void
    {
        $middleware = new CharsetMiddleware();
        // Just assert it can be constructed; encoding values verified via wrap()
        self::assertInstanceOf(CharsetMiddleware::class, $middleware);
    }

    public function testCharsetMiddlewareWrapReturnsDriver(): void
    {
        $inner      = $this->createMock(Driver::class);
        $middleware  = new CharsetMiddleware();
        $wrapped    = $middleware->wrap($inner);
        self::assertInstanceOf(Driver::class, $wrapped);
    }

    public function testCharsetMiddlewareWrapWithCustomEncodings(): void
    {
        $inner      = $this->createMock(Driver::class);
        $middleware  = new CharsetMiddleware('ISO-8859-1', 'UTF-8');
        $wrapped    = $middleware->wrap($inner);
        self::assertInstanceOf(Driver::class, $wrapped);
    }

    public function testCharsetMiddlewareConnectReturnsCharsetConnection(): void
    {
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerDriver     = $this->createMock(Driver::class);
        $innerDriver->expects(self::once())
            ->method('connect')
            ->willReturn($innerConnection);

        $middleware = new CharsetMiddleware('Windows-1252', 'UTF-8');
        $wrapped    = $middleware->wrap($innerDriver);
        $conn       = $wrapped->connect([]);

        self::assertInstanceOf(CharsetConnectionMiddleware::class, $conn);
    }

    // ---------------------------------------------------------------------------
    // CharsetConnectionMiddleware
    // ---------------------------------------------------------------------------

    public function testCharsetConnectionMiddlewarePrepareReturnsCharsetStatement(): void
    {
        $innerStatement  = $this->createMock(DriverStatement::class);
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('prepare')
            ->willReturn($innerStatement);

        $conn  = new CharsetConnectionMiddleware($innerConnection, 'Windows-1252', 'UTF-8');
        $stmt  = $conn->prepare('SELECT 1');

        self::assertInstanceOf(CharsetStatementMiddleware::class, $stmt);
    }

    /**
     * FR-010: prepare() MUST re-encode the SQL body from PHP encoding to database
     * encoding so that QueryBuilder literal fragments (and other inline string
     * literals) are transcoded before reaching Firebird.
     */
    public function testCharsetConnectionMiddlewarePrepareEncodesSqlBody(): void
    {
        $utf8Sql        = "SELECT 'Müller' AS name WHERE ort LIKE '%Faßbrause%'";
        $win1252Sql     = mb_convert_encoding($utf8Sql, 'Windows-1252', 'UTF-8');

        $innerStatement  = $this->createMock(DriverStatement::class);
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('prepare')
            ->with($win1252Sql)
            ->willReturn($innerStatement);

        $conn = new CharsetConnectionMiddleware($innerConnection, 'Windows-1252', 'UTF-8');
        $conn->prepare($utf8Sql);
    }

    /**
     * FR-008: query() MUST re-encode the SQL body from PHP encoding to database
     * encoding AND wrap the returned Result in CharsetResultMiddleware so fetched
     * rows are decoded back to the PHP encoding.
     */
    public function testCharsetConnectionMiddlewareQueryEncodesSqlBodyAndWrapsResult(): void
    {
        $utf8Sql    = "SELECT 'Müller' AS name WHERE ort LIKE '%Faßbrause%'";
        $win1252Sql = mb_convert_encoding($utf8Sql, 'Windows-1252', 'UTF-8');

        $innerResult = $this->createMock(DriverResult::class);
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('query')
            ->with($win1252Sql)
            ->willReturn($innerResult);

        $conn   = new CharsetConnectionMiddleware($innerConnection, 'Windows-1252', 'UTF-8');
        $result = $conn->query($utf8Sql);

        self::assertInstanceOf(CharsetResultMiddleware::class, $result);
    }

    /**
     * FR-008: query() MUST wrap the returned Result even when SQL is pure ASCII,
     * so that result rows are decoded back to the PHP encoding regardless of the
     * SQL body content (this is the core of issue GH-116).
     */
    public function testCharsetConnectionMiddlewareQueryWrapsResultForAsciiSql(): void
    {
        $asciiSql = 'SELECT name FROM adressen';

        $innerResult = $this->createMock(DriverResult::class);
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('query')
            ->with($asciiSql)
            ->willReturn($innerResult);

        $conn   = new CharsetConnectionMiddleware($innerConnection, 'Windows-1252', 'UTF-8');
        $result = $conn->query($asciiSql);

        self::assertInstanceOf(CharsetResultMiddleware::class, $result);
    }

    /**
     * FR-009: exec() MUST re-encode the SQL body from PHP encoding to database
     * encoding so that parameterless DML statements with UTF-8 literals are
     * transcoded before reaching Firebird.
     */
    public function testCharsetConnectionMiddlewareExecEncodesSqlBody(): void
    {
        $utf8Sql    = "UPDATE adressen SET ort = 'Müller & Söhne' WHERE name LIKE '%Faß%'";
        $win1252Sql = mb_convert_encoding($utf8Sql, 'Windows-1252', 'UTF-8');

        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('exec')
            ->with($win1252Sql)
            ->willReturn(1);

        $conn   = new CharsetConnectionMiddleware($innerConnection, 'Windows-1252', 'UTF-8');
        $result = $conn->exec($utf8Sql);

        self::assertSame(1, $result);
    }

    /**
     * FR-009: exec() return value (affected-row count) MUST pass through unchanged.
     */
    public function testCharsetConnectionMiddlewareExecReturnsAffectedRowCount(): void
    {
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('exec')
            ->willReturn(42);

        $conn   = new CharsetConnectionMiddleware($innerConnection, 'Windows-1252', 'UTF-8');
        $result = $conn->exec('UPDATE t SET x = 1');

        self::assertSame(42, $result);
    }

    /**
     * FR-008/009/010: when phpEncoding === databaseEncoding, SQL MUST pass through
     * unchanged (no spurious re-encoding).
     */
    public function testCharsetConnectionMiddlewareSqlMethodsAreNoopWhenEncodingsMatch(): void
    {
        $sql = "SELECT 'Müller' AS name";

        $innerResult    = $this->createMock(DriverResult::class);
        $innerStatement = $this->createMock(DriverStatement::class);
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->method('query')->with($sql)->willReturn($innerResult);
        $innerConnection->method('exec')->with($sql)->willReturn(0);
        $innerConnection->method('prepare')->with($sql)->willReturn($innerStatement);

        $conn = new CharsetConnectionMiddleware($innerConnection, 'UTF-8', 'UTF-8');

        self::assertInstanceOf(CharsetResultMiddleware::class, $conn->query($sql));
        self::assertSame(0, $conn->exec($sql));
        self::assertInstanceOf(CharsetStatementMiddleware::class, $conn->prepare($sql));
    }

    public function testCharsetConnectionMiddlewareQuoteConvertsStringEncoding(): void
    {
        $innerConnection = $this->createMock(DriverConnection::class);
        $innerConnection->expects(self::once())
            ->method('quote')
            ->willReturnCallback(static fn ($value) => "'" . $value . "'");

        $conn   = new CharsetConnectionMiddleware($innerConnection, 'UTF-8', 'UTF-8');
        $result = $conn->quote('hello');
        self::assertStringContainsString('hello', (string) $result);
    }

    // ---------------------------------------------------------------------------
    // CharsetStatementMiddleware
    // ---------------------------------------------------------------------------

    public function testCharsetStatementBindValueEncodesString(): void
    {
        $inner = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('bindValue');

        $stmt = new CharsetStatementMiddleware($inner, 'UTF-8', 'UTF-8');
        $stmt->bindValue(1, 'hello', ParameterType::STRING);
    }

    public function testCharsetStatementBindValuePassesThroughNonStringValue(): void
    {
        $inner = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('bindValue')
            ->with(1, 42, ParameterType::INTEGER);

        $stmt = new CharsetStatementMiddleware($inner, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, 42, ParameterType::INTEGER);
    }

    public function testCharsetStatementBindValuePassesThroughBinaryType(): void
    {
        $inner = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('bindValue')
            ->with(1, 'raw', ParameterType::BINARY);

        $stmt = new CharsetStatementMiddleware($inner, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, 'raw', ParameterType::BINARY);
    }

    public function testCharsetStatementBindValuePassesThroughASCII(): void
    {
        $utf8String   = 'Hällo';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $inner = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('bindValue')
            ->with(1, $win1252Bytes, ParameterType::ASCII);

        $stmt = new CharsetStatementMiddleware($inner, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, $utf8String, ParameterType::ASCII);
    }

    public function testCharsetStatementExecuteReturnsWrappedResult(): void
    {
        $innerResult = $this->createMock(DriverResult::class);
        $inner       = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('execute')
            ->willReturn($innerResult);

        $stmt   = new CharsetStatementMiddleware($inner, 'UTF-8', 'UTF-8');
        $result = $stmt->execute();
        // Returns a CharsetResultMiddleware wrapping the inner result
        self::assertInstanceOf(DriverResult::class, $result);
    }

    public function testCharsetStatementBindValueEncodesStreamAsLargeObject(): void
    {
        $utf8String   = 'Hällo World';
        $win1252Bytes = mb_convert_encoding($utf8String, 'Windows-1252', 'UTF-8');

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $utf8String);
        rewind($stream);

        $inner = $this->createMock(DriverStatement::class);
        $inner->expects(self::once())
            ->method('bindValue')
            ->with(1, $win1252Bytes, ParameterType::LARGE_OBJECT);

        $stmt = new CharsetStatementMiddleware($inner, 'Windows-1252', 'UTF-8');
        $stmt->bindValue(1, $stream, ParameterType::LARGE_OBJECT);

        fclose($stream);
    }
}
