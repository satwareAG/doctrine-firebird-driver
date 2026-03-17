<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver\Result as DriverResult;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetResultMiddleware;
use Doctrine\DBAL\ParameterType;

use function fopen;
use function fwrite;
use function rewind;
use function stream_get_contents;

class Issue91ReproductionTest extends TestCase
{
    /**
     * This test demonstrates that binary BLOBs without null bytes in the first 512 bytes
     * are incorrectly transcoded by the current CharsetResultMiddleware implementation.
     */
    public function testBinaryBlobWithoutNullBytesIsNotCorrupted(): void
    {
        // Binary data without null bytes (e.g., a bunch of 'X's)
        $binaryData = str_repeat('X', 1024);
        
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $binaryData);
        rewind($stream);

        // We create a mock that implements DriverResult and also has getColumnTypes method
        $result = $this->createMock(ResultWithMetadata::class);
        $result->method('fetchOne')->willReturn($stream);
        $result->method('getColumnTypes')->willReturn([0 => ParameterType::BINARY]);

        // Use different encodings
        $mw = new CharsetResultMiddleware($result, 'ISO-8859-1', 'UTF-8', [0 => ParameterType::BINARY]);
        
        $value = $mw->fetchOne();

        self::assertIsResource($value, 'Binary BLOB should remain a resource when type is ParameterType::BINARY');
        self::assertSame($binaryData, stream_get_contents($value));
    }

    /**
     * This test demonstrates that binary BLOBs with null bytes ARE correctly detected
     * via the peeking fallback when metadata is NOT available.
     */
    public function testBinaryBlobWithNullBytesIsDetectedViaPeekingFallback(): void
    {
        // Binary data with a null byte at the beginning
        $binaryData = "\x00" . str_repeat('X', 1023);
        
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $binaryData);
        rewind($stream);

        $result = $this->createMock(DriverResult::class);
        $result->method('fetchOne')->willReturn($stream);

        // No metadata passed, should default to ParameterType::STRING and then peek
        $mw = new CharsetResultMiddleware($result, 'ISO-8859-1', 'UTF-8');
        
        $value = $mw->fetchOne();

        self::assertIsResource($value, 'Binary BLOB with null bytes should remain a resource even without metadata');
        self::assertSame($binaryData, stream_get_contents($value));
    }
}

/**
 * Interface for mocking purposes
 */
interface ResultWithMetadata extends DriverResult {
    public function getColumnTypes(): array;
}
