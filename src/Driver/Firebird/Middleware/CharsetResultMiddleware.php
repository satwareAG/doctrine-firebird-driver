<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Result;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Result as FirebirdResult;

use function array_values;
use function fopen;
use function fwrite;
use function is_array;
use function is_resource;
use function is_string;
use function mb_convert_encoding;
use function rewind;
use function stream_get_contents;
use function strpos;

/**
 * Decodes all string values returned from Firebird (database encoding) to the
 * PHP application encoding (typically UTF-8).
 *
 * This middleware wraps every Result object so that raw DBAL fetch calls
 * (fetchAssociative, fetchNumeric, fetchOne, etc.) transparently return
 * strings in the PHP encoding regardless of the database wire encoding.
 *
 * Binary BLOB data (sub_type 0) is detected via fbird_field_info() sub_type
 * lookup when the inner Result is a native Firebird Result. If the inner
 * Result is wrapped by other middleware, falls back to a NULL-byte heuristic.
 * See R1 optimization and issue #130 for the prior heuristic-based approach.
 */
final class CharsetResultMiddleware extends AbstractResultMiddleware
{
    /**
     * Map of [column_index => sub_type] for BLOB columns, or null if the inner
     * Result is not a native Firebird Result (e.g., wrapped by other middleware).
     *
     * When non-null, decodeValue() uses this for definitive binary BLOB detection.
     * When null, falls back to the NULL-byte heuristic.
     *
     * @var array<int, int>|null
     */
    private readonly array|null $blobSubTypes;

    public function __construct(
        Result $result,
        private readonly string $databaseEncoding,
        private readonly string $phpEncoding,
    ) {
        parent::__construct($result);

        // Pre-compute BLOB sub_types if the inner Result is a native Firebird Result.
        // If it's another middleware wrapper, fall back to NULL-byte heuristic.
        $this->blobSubTypes = $result instanceof FirebirdResult
            ? $result->getBlobSubTypes()
            : null;
    }

    /** @return list<mixed>|false */
    #[Override]
    public function fetchNumeric(): array|false
    {
        $row = parent::fetchNumeric();

        if (! is_array($row)) {
            return $row;
        }

        foreach ($row as $i => $value) {
            $row[$i] = $this->decodeValue($value, $i);
        }

        return array_values($row);
    }

    /** @return array<string, mixed>|false */
    #[Override]
    public function fetchAssociative(): array|false
    {
        $row = parent::fetchAssociative();

        if (! is_array($row)) {
            return $row;
        }

        $i = 0;
        foreach ($row as $key => $value) {
            $row[$key] = $this->decodeValue($value, $i++);
        }

        return $row;
    }

    #[Override]
    public function fetchOne(): mixed
    {
        $value = parent::fetchOne();

        return $this->decodeValue($value, 0);
    }

    /** @return list<list<mixed>> */
    #[Override]
    public function fetchAllNumeric(): array
    {
        $rows = parent::fetchAllNumeric();

        foreach ($rows as $i => $row) {
            foreach ($row as $j => $value) {
                $row[$j] = $this->decodeValue($value, $j);
            }

            $rows[$i] = array_values($row);
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    #[Override]
    public function fetchAllAssociative(): array
    {
        $rows = parent::fetchAllAssociative();

        foreach ($rows as $i => $row) {
            $j = 0;
            foreach ($row as $key => $value) {
                $row[$key] = $this->decodeValue($value, $j++);
            }

            $rows[$i] = $row;
        }

        return $rows;
    }

    /** @return list<mixed> */
    #[Override]
    public function fetchFirstColumn(): array
    {
        $column = parent::fetchFirstColumn();

        foreach ($column as $i => $value) {
            $column[$i] = $this->decodeValue($value, 0);
        }

        return $column;
    }

    /**
     * Decode a single value from database encoding to PHP encoding.
     *
     * Non-string values (int, float, null, bool, objects) pass through unchanged.
     *
     * When BLOB sub_type information is available (inner Result is a native
     * Firebird Result), binary BLOBs (sub_type 0) are passed through without
     * transcoding and text BLOBs (sub_type 1) are transcoded. This is the
     * definitive detection path (R1 optimization).
     *
     * When sub_type information is not available (inner Result is wrapped by
     * other middleware), falls back to a NULL-byte heuristic: binary data
     * containing NULL bytes is passed through without transcoding to prevent
     * corruption (e.g., JPEG \xFF\xD8\xFF\xE0\x00 would be mangled to
     * \xC3\xBF\xC3\x98... by ISO8859_1->UTF-8 conversion).
     *
     * For resource values (legacy BLOB fetch without FBIRD_FETCH_BLOBS), the
     * same heuristic is applied on the fallback path: binary data is preserved
     * as a resource, text data is transcoded to a string.
     *
     * Lenient on invalid bytes: relies on PHP's default substitution rather
     * than throwing. Result data is external input - the database may contain
     * legacy/mixed-encoding data; throwing would crash every query on a
     * single bad row. See encodeSql() in CharsetConnectionMiddleware for
     * the stricter treatment applied to SQL body literals.
     *
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/117
     *
     * @param int|null $columnIndex Zero-based column index for sub_type lookup, or null if unknown.
     */
    private function decodeValue(mixed $value, int|null $columnIndex = null): mixed
    {
        // Definitive BLOB sub_type detection (R1): if we have sub_type info and
        // this column is a BLOB, use sub_type to decide transcoding.
        if ($columnIndex !== null && $this->blobSubTypes !== null && isset($this->blobSubTypes[$columnIndex])) {
            $subType = $this->blobSubTypes[$columnIndex];

            if ($subType === 0) {
                // Binary BLOB (sub_type 0) - pass through unchanged (resource or string).
                // No transcoding, no stream peeking.
                return $value;
            }

            // Text BLOB (sub_type 1) - transcode from database encoding to PHP encoding.
            if (is_resource($value)) {
                $content = stream_get_contents($value);

                return mb_convert_encoding($content, $this->phpEncoding, $this->databaseEncoding);
            }

            if (is_string($value)) {
                return mb_convert_encoding($value, $this->phpEncoding, $this->databaseEncoding);
            }

            return $value;
        }

        // --- Fallback: NULL-byte heuristic (when sub_type not available) ---

        // Note: This checks for PHP stream resources (BLOB content returned as
        // php_stream by php-firebird's PARAM_LOB handling), NOT Firebird
        // connection/transaction handles which are Firebird\* objects since v11.
        if (is_resource($value)) {
            // Peek at the first 512 bytes to detect binary data (NULL bytes)
            $buffer = stream_get_contents($value, 512);
            $rest   = stream_get_contents($value);

            // If we found a NULL byte, it's likely binary data, keep it as resource
            if (strpos($buffer, "\x00") !== false) {
                $newStream = fopen('php://memory', 'r+');
                if ($newStream !== false) {
                    fwrite($newStream, $buffer);
                    fwrite($newStream, $rest);
                    rewind($newStream);

                    return $newStream;
                }

                return $value;
            }

            // Otherwise, treat as text and transcode
            return mb_convert_encoding($buffer . $rest, $this->phpEncoding, $this->databaseEncoding);
        }

        if (! is_string($value)) {
            return $value;
        }

        // Binary BLOB strings from FBIRD_FETCH_BLOBS: skip transcoding if NULL
        // bytes are present. All common binary formats (JPEG, PNG, GIF, PDF, ZIP,
        // BMP, TIFF) contain \x00 in their first few bytes.
        if (strpos($value, "\x00") !== false) {
            return $value;
        }

        return mb_convert_encoding($value, $this->phpEncoding, $this->databaseEncoding);
    }
}
