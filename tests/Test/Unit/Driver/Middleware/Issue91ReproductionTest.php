<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Driver\Middleware;

use Doctrine\DBAL\Driver\Result as DriverResult;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetResultMiddleware;

use function fopen;
use function fwrite;
use function mb_convert_encoding;
use function rewind;
use function stream_get_contents;

/**
 * Tests for issue #91: binary BLOB data integrity through CharsetResultMiddleware.
 *
 * After #127/#129, the columnTypes parameter was removed. Binary detection
 * now uses fbird_field_info() sub_type lookup when a native Firebird Result
 * is available (R1 optimization). These tests use a mock Result, so they
 * exercise the NULL-byte heuristic fallback path only. The definitive
 * sub_type-based detection is tested in BlobBinaryCharsetTest.
 */
class Issue91ReproductionTest extends TestCase
{
    /**
     * Binary BLOB with NULL bytes is correctly detected and preserved as resource.
     */
    public function testBinaryBlobWithNullBytesIsDetectedViaPeekingFallback(): void
    {
        $binaryData = "\x00" . str_repeat('X', 1023);

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $binaryData);
        rewind($stream);

        $result = $this->createMock(DriverResult::class);
        $result->method('fetchOne')->willReturn($stream);

        $mw = new CharsetResultMiddleware($result, 'ISO-8859-1', 'UTF-8');

        $value = $mw->fetchOne();

        self::assertIsResource($value, 'Binary BLOB with null bytes should remain a resource');
        self::assertSame($binaryData, stream_get_contents($value));
    }

    /**
     * Binary BLOB data without NULL bytes is transcoded (fallback path only).
     *
     * When the inner Result is not a native Firebird Result (e.g., a mock),
     * the NULL-byte heuristic is used. This test documents the fallback behavior.
     * The definitive sub_type-based detection is tested in BlobBinaryCharsetTest.
     *
     * This is a known limitation of the fallback heuristic (see #130), fixed
     * only on the native Result path via fbird_field_info() sub_type lookup.
     */
    public function testBinaryBlobWithoutNullBytesIsTranscodedButUnchanged(): void
    {
        // Pure ASCII data — survives transcoding because ASCII ⊂ ISO-8859-1 ⊂ UTF-8
        $binaryData = str_repeat('X', 1024);

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $binaryData);
        rewind($stream);

        $result = $this->createMock(DriverResult::class);
        $result->method('fetchOne')->willReturn($stream);

        $mw = new CharsetResultMiddleware($result, 'ISO-8859-1', 'UTF-8');

        $value = $mw->fetchOne();

        // Without NULL bytes, the resource is consumed and transcoded to a string
        self::assertIsString($value, 'Binary data without NULL bytes is transcoded (known limitation, see #130)');
        self::assertSame(
            mb_convert_encoding($binaryData, 'UTF-8', 'ISO-8859-1'),
            $value,
            'Pure ASCII data survives transcoding unchanged',
        );
    }
}
