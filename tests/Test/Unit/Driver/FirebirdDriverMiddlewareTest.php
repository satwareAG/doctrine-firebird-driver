<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\FirebirdDriverMiddleware;

/**
 * Unit tests for FirebirdDriverMiddleware.
 *
 * Verifies that the middleware correctly wraps a Driver and delegates
 * all standard Driver interface calls to the wrapped instance.
 */
#[CoversClass(FirebirdDriverMiddleware::class)]
class FirebirdDriverMiddlewareTest extends TestCase
{
    public function testWrapReturnsDriverInstance(): void
    {
        $innerDriver = $this->createMock(Driver::class);
        $middleware  = new FirebirdDriverMiddleware();

        $wrapped = $middleware->wrap($innerDriver);

        self::assertInstanceOf(Driver::class, $wrapped);
    }

    public function testWrapReturnsDifferentInstanceFromInner(): void
    {
        $innerDriver = $this->createMock(Driver::class);
        $middleware  = new FirebirdDriverMiddleware();

        $wrapped = $middleware->wrap($innerDriver);

        self::assertNotSame($innerDriver, $wrapped);
    }

    public function testWrappedDriverDelegatesGetExceptionConverter(): void
    {
        $exceptionConverter = $this->createMock(ExceptionConverter::class);

        $innerDriver = $this->createMock(Driver::class);
        $innerDriver->expects(self::once())
            ->method('getExceptionConverter')
            ->willReturn($exceptionConverter);

        $middleware = new FirebirdDriverMiddleware();
        $wrapped    = $middleware->wrap($innerDriver);

        $result = $wrapped->getExceptionConverter();

        self::assertSame($exceptionConverter, $result);
    }

    public function testMultipleWrapsAreIndependent(): void
    {
        $innerDriver1 = $this->createMock(Driver::class);
        $innerDriver2 = $this->createMock(Driver::class);
        $middleware   = new FirebirdDriverMiddleware();

        $wrapped1 = $middleware->wrap($innerDriver1);
        $wrapped2 = $middleware->wrap($innerDriver2);

        self::assertNotSame($wrapped1, $wrapped2);
    }
}
