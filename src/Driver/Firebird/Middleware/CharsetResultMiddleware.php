<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;
use Override;

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
 */
final class CharsetResultMiddleware extends AbstractResultMiddleware
{
    /** @param array<int|string, int> $columnTypes Map of column index/name to ParameterType::* */
    public function __construct(
        Result $result,
        private readonly string $databaseEncoding,
        private readonly string $phpEncoding,
        private readonly array $columnTypes = [],
    ) {
        parent::__construct($result);
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

        foreach ($row as $key => $value) {
            $row[$key] = $this->decodeValue($value, $key);
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
            foreach ($row as $key => $value) {
                $row[$key] = $this->decodeValue($value, $key);
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
     * Decode a single value from database encoding to PHP encoding if it is a string.
     * Non-string values (int, float, null, bool, objects) pass through unchanged.
     *
     * For TEXT BLOB columns, Firebird returns stream resources. These are extracted
     * and transcoded in-place, returning the decoded string directly.
     *
     * For BINARY BLOB columns, the resource is returned as-is to the application.
     *
     * @param int|string|null $column Key/index of the column being decoded
     */
    private function decodeValue(mixed $value, int|string|null $column = null): mixed
    {
        if (is_resource($value)) {
            // If we know the column type is BINARY, return the resource as-is
            if ($column !== null && isset($this->columnTypes[$column])) {
                $columnType = $this->columnTypes[$column];
                if ($columnType === ParameterType::BINARY || $columnType === ParameterType::LARGE_OBJECT) {
                    return $value;
                }
            }

            // Fallback: peek at the first 512 bytes to detect binary data (NULL bytes)
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

        return mb_convert_encoding($value, $this->phpEncoding, $this->databaseEncoding);
    }
}
