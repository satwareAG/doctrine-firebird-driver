<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Result;
use Override;

use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function mb_convert_encoding;

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
    public function __construct(
        Result $result,
        private readonly string $databaseEncoding,
        private readonly string $phpEncoding,
    ) {
        parent::__construct($result);
    }

    /** @return list<mixed>|false */
    #[Override]
    public function fetchNumeric(): array|false
    {
        $row = parent::fetchNumeric();

        return is_array($row) ? array_values($this->decodeRow($row)) : $row;
    }

    /** @return array<string, mixed>|false */
    #[Override]
    public function fetchAssociative(): array|false
    {
        $row = parent::fetchAssociative();

        if (! is_array($row)) {
            return $row;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $this->decodeRow($row);

        return $decoded;
    }

    #[Override]
    public function fetchOne(): mixed
    {
        $value = parent::fetchOne();

        return $this->decodeValue($value);
    }

    /** @return list<list<mixed>> */
    #[Override]
    public function fetchAllNumeric(): array
    {
        return array_map(
            fn (array $row): array => array_values($this->decodeRow($row)),
            parent::fetchAllNumeric(),
        );
    }

    /** @return list<array<string, mixed>> */
    #[Override]
    public function fetchAllAssociative(): array
    {
        return array_map(
            fn (array $row): array => $this->decodeRow($row),
            parent::fetchAllAssociative(),
        );
    }

    /** @return list<mixed> */
    #[Override]
    public function fetchFirstColumn(): array
    {
        return array_map(
            fn (mixed $value): mixed => $this->decodeValue($value),
            parent::fetchFirstColumn(),
        );
    }

    /**
     * Decode all string values in a row from database encoding to PHP encoding.
     *
     * @param array<int|string, mixed> $row
     *
     * @return array<int|string, mixed>
     */
    private function decodeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $this->decodeValue($value);
        }

        return $row;
    }

    /**
     * Decode a single value from database encoding to PHP encoding if it is a string.
     * Non-string values (int, float, null, bool, objects) pass through unchanged.
     */
    private function decodeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return mb_convert_encoding($value, $this->phpEncoding, $this->databaseEncoding);
    }
}
