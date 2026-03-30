<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Throwable;

use function array_values;
use function fbird_affected_rows;
use function fbird_fetch_assoc;
use function fbird_fetch_row;
use function fbird_free_result;
use function fbird_num_fields;
use function get_resource_type;
use function in_array;
use function is_array;
use function is_numeric;
use function is_resource;
use function preg_replace;
use function trim;

use const FBIRD_FETCH_BLOBS;
use const FBIRD_FETCH_DATE_OBJ;

final class Result implements ResultInterface
{
    /** @var resource|int|null */
    private mixed $firebirdResultResource = null;

    /**
     * Valid resource types for Firebird result sets.
     * php-firebird v6.x uses legacy names, v7.x+ uses 'firebird result'.
     * v9.x+ uses 'Firebird query' for both statements and results.
     */
    private const VALID_RESULT_TYPES = [
        'interbase result',
        'Firebird/InterBase result',
        'firebird result',
        'Firebird query',
    ];

    /**
     * @internal The result can only be instantiated by its driver connection or statement.
     *
     * The $statement parameter is intentionally held but never read directly.
     * It prevents premature garbage collection of the Statement object while
     * the Result is being iterated. Without this reference, PHP may GC the
     * Statement, invalidating the underlying Firebird result resource.
     *
     * @throws Exception
     */

    /** @param resource|int|null $firebirdResultResource */
    public function __construct(
        $firebirdResultResource,
        private readonly Connection $connection,
        /** @phpstan-ignore-next-line property.onlyWritten */
        private readonly Statement|null $statement = null,
    ) {
        $this->firebirdResultResource = $firebirdResultResource;

        // If no insert column is expected (normal query), return early to allow user to fetch results.
        if ($this->connection->getConnectionInsertColumn() === null) {
            return;
        }

        // If insert column is expected (INSERT ... RETURNING ...), fetch it immediately for lastInsertId.
        $this->connection->setConnectionInsertColumn(null);
        $lastInsertId = $this->fetchOne();
        if ($lastInsertId === false) {
            return;
        }

        $this->connection->setLastInsertId((int) $lastInsertId);
    }

    /** @throws Exception */
    public function __destruct()
    {
        $this->free();
    }

    /**
     * {@inheritDoc}
     *
     * @return false|list<mixed>
     */
    #[Override]
    public function fetchNumeric()
    {
        if (! $this->isResultValid()) {
            return false;
        }

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        // (php-firebird v7.0.0-rc.6+). The @ operator only suppresses warnings, not exceptions.
        try {
            // Warning "Invalid cursor" may be emitted when fetching from a closed/reused statement's result
            /** @phpstan-ignore argument.type */
            $result = fbird_fetch_row($this->firebirdResultResource, FBIRD_FETCH_BLOBS);
            if (is_array($result)) {
                return array_values($result);
            }

            // Free result resource implicitly to allow Statement to be freed later
            // Also commit transaction if autocommit is enabled to keep transaction log clean
            $this->free();
            $this->connection->autoCommit();
        } catch (Throwable $e) {
            throw \Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::fromThrowable($e);
        }

        return false;
    }

    #[Override]
    public function fetchAssociative(): array|false
    {
        if (! $this->isResultValid()) {
            return false;
        }

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        // (php-firebird v7.0.0-rc.6+). The @ operator only suppresses warnings, not exceptions.
        try {
            /** @phpstan-ignore argument.type */
            $result = fbird_fetch_assoc($this->firebirdResultResource, FBIRD_FETCH_BLOBS);
        } catch (Throwable $e) {
            throw \Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::fromThrowable($e);
        }

        if (is_array($result)) {
            return $this->normalizeRowKeys($result);
        }

        $this->free();
        $this->connection->autoCommit();

        return false;
    }

    #[Override]
    public function fetchOne(): mixed
    {
        return FetchUtils::fetchOne($this);
    }

    /** @inheritDoc */
    #[Override]
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /** @inheritDoc */
    #[Override]
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /** @inheritDoc */
    #[Override]
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    #[Override]
    public function rowCount(): int
    {
        if (is_numeric($this->firebirdResultResource)) {
            /** @psalm-suppress RedundantCast */
            return (int) $this->firebirdResultResource;
        }

        if ($this->isResultValid()) {
            return fbird_affected_rows($this->connection->getNativeConnection());
        }

        return 0;
    }

    #[Override]
    public function columnCount(): int
    {
        if ($this->isResultValid()) {
            /** @phpstan-ignore argument.type */
            return (int) fbird_num_fields($this->firebirdResultResource);
        }

        return 0;
    }

    // =========================================================================
    // php-firebird v7.0.0+ DateTimeImmutable fetch support
    // Uses FBIRD_FETCH_DATE_OBJ constant for native DateTimeImmutable returns
    // =========================================================================

    /**
     * Fetch a row as a numeric array with DATE/TIME/TIMESTAMP as DateTimeImmutable.
     *
     * php-firebird v7.0.0+ feature: When FBIRD_FETCH_DATE_OBJ is used,
     * DATE, TIME, and TIMESTAMP columns are returned as DateTimeImmutable
     * objects instead of string representations.
     *
     * @return false|list<mixed> Numeric array with DateTimeImmutable for date columns, or false
     */
    public function fetchNumericWithDateObjects(): array|false
    {
        if (! $this->isResultValid()) {
            return false;
        }

        $fetchFlags = FBIRD_FETCH_BLOBS | FBIRD_FETCH_DATE_OBJ;

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        try {
            /** @phpstan-ignore argument.type */
            $result = fbird_fetch_row($this->firebirdResultResource, $fetchFlags);
        } catch (Throwable $e) {
            throw \Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::fromThrowable($e);
        }

        if (is_array($result)) {
            return array_values($result);
        }

        $this->free();
        $this->connection->autoCommit();

        return false;
    }

    /**
     * Fetch a row as an associative array with DATE/TIME/TIMESTAMP as DateTimeImmutable.
     *
     * php-firebird v7.0.0+ feature: When FBIRD_FETCH_DATE_OBJ is used,
     * DATE, TIME, and TIMESTAMP columns are returned as DateTimeImmutable
     * objects instead of string representations.
     *
     * @return array<string, mixed>|false Associative array with DateTimeImmutable for date columns, or false
     */
    public function fetchAssociativeWithDateObjects(): array|false
    {
        if (! $this->isResultValid()) {
            return false;
        }

        $fetchFlags = FBIRD_FETCH_BLOBS | FBIRD_FETCH_DATE_OBJ;

        // Wrap in try-catch to handle Firebird\Exception when Exception Mode is enabled
        try {
            /** @phpstan-ignore argument.type */
            $result = fbird_fetch_assoc($this->firebirdResultResource, $fetchFlags);
        } catch (Throwable $e) {
            throw \Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception::fromThrowable($e);
        }

        if (is_array($result)) {
            return $this->normalizeRowKeys($result);
        }

        $this->free();
        $this->connection->autoCommit();

        return false;
    }

    /**
     * Fetch all rows with DATE/TIME/TIMESTAMP as DateTimeImmutable objects.
     *
     * @return array<int, array<string, mixed>> All rows with DateTimeImmutable for date columns
     */
    public function fetchAllAssociativeWithDateObjects(): array
    {
        $rows = [];
        while (($row = $this->fetchAssociativeWithDateObjects()) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @throws Exception */
    #[Override]
    public function free(): void
    {
        if (! $this->isResultValid()) {
            $this->firebirdResultResource = null;

            return;
        }

        /** @phpstan-ignore argument.type */
        fbird_free_result($this->firebirdResultResource);
        $this->firebirdResultResource = null;
    }

    /**
     * Check if DateTimeImmutable fetch is available.
     *
     * Always returns true since php-firebird v7.0.0+ (minimum supported version)
     * guarantees FBIRD_FETCH_DATE_OBJ availability.
     */
    public static function isDateObjectFetchAvailable(): bool
    {
        return true;
    }

    /**
     * Check if the result resource is a valid Firebird result resource.
     *
     * Validates both that the value is a resource AND that it has a valid
     * Firebird result type string. This prevents passing transaction resources
     * or invalidated ("Unknown") resources to fbird_* functions.
     *
     * @psalm-assert-if-true resource $this->firebirdResultResource
     * @phpstan-assert-if-true resource $this->firebirdResultResource
     */
    private function isResultValid(): bool
    {
        if (! is_resource($this->firebirdResultResource)) {
            return false;
        }

        return in_array(get_resource_type($this->firebirdResultResource), self::VALID_RESULT_TYPES, true);
    }

    /**
     * Normalize Firebird 3.0+ column names in an associative row.
     *
     * Firebird 3.0+ may return padded aliases (e.g. "COLUMN   "), and php-firebird
     * appends unique suffixes AFTER padding (e.g. "COLUMN   _01"). This method
     * converts them to clean names: "COLUMN", "COLUMN_01".
     *
     * @see https://github.com/satwareAG/php-firebird/issues/23
     *
     * @param array<string, mixed> $row Raw associative row from fbird_fetch_assoc()
     *
     * @return array<string, mixed> Row with normalized column keys
     */
    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $finalKey = self::normalizeKey($key);
            if ($finalKey === '') {
                continue;
            }

            $normalized[$finalKey] = $value;
        }

        return $normalized;
    }

    /**
     * Normalize a single Firebird column name.
     *
     * 1. Remove spaces before suffix (e.g. "   _01" -> "_01")
     * 2. Trim surrounding spaces
     *
     * @param string $key Raw column name from Firebird
     *
     * @return string Normalized column name, or empty string if key was empty
     */
    private static function normalizeKey(string $key): string
    {
        // Handle preg_replace returning null on error by using null coalescing
        $keyString = preg_replace('/\s+(?=_\d+$)/', '', $key) ?? $key;

        return trim($keyString);
    }
}
