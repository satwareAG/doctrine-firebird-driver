<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\Exception;

use RuntimeException;

use function sprintf;

/**
 * Thrown when mb_convert_encoding fails to transcode a value between the PHP
 * application encoding and the Firebird wire encoding.
 *
 * Conversion failure means the source byte sequence is invalid for the
 * configured source encoding - typically a sign of a misconfigured encoding
 * pair or a source string produced by reading non-UTF-8 input without
 * explicit transcoding. Surfaced as an exception rather than silently
 * passing false to the driver.
 *
 * phpcs:disable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
 */
final class CharsetConversionException extends RuntimeException
{
    public static function conversionFailure(
        string $valueKind,
        string $fromEncoding,
        string $toEncoding,
    ): self {
        return new self(sprintf(
            'Failed to re-encode %s from %s to %s (invalid source byte sequence).',
            $valueKind,
            $fromEncoding,
            $toEncoding,
        ));
    }
}
