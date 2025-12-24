<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Override;

use function array_values;
use function defined;
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
    /**
     * @internal The result can only be instantiated by its driver connection or statement.
     *
     * The $statement parameter is intentionally held but never read directly.
     * It prevents premature garbage collection of the Statement object while
     * the Result is being iterated. Without this reference, PHP may GC the
     * Statement, invalidating the underlying Firebird result resource.
     *
     * @throws Exception
     *
     * @phpstan-ignore property.onlyWritten (Required for GC: keeps Statement alive during Result iteration)
     */
    public function __construct(
        private mixed $firebirdResultResource,
        private readonly Connection $connection,
        private readonly Statement|null $statement = null,
    ) {
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
        if (is_resource($this->firebirdResultResource)) {
            // @todo remove @ when fbird_fetch_row() doesn't warn on normal end of fetch or closed cursor
            // Warning "Invalid cursor" is emitted when fetching from a closed/reused statement's result in some cases
            $result = fbird_fetch_row($this->firebirdResultResource, FBIRD_FETCH_BLOBS);
            if (is_array($result)) {
                return array_values($result);
            }

            // Free result resource implicitly to allow Statement to be freed later
            // Also commit transaction if autocommit is enabled to keep transaction log clean
            $this->free();
            $this->connection->autoCommit();
        }

        return false;
    }

    #[Override]
    public function fetchAssociative(): array|false
    {
        if (is_resource($this->firebirdResultResource)) {
            // @todo remove @ when fbird_fetch_assoc() doesn't warn
            $result = fbird_fetch_assoc($this->firebirdResultResource, FBIRD_FETCH_BLOBS);
            if (is_array($result)) {
                // Firebird 3.0+ may return padded aliases (e.g. "COLUMN   "), causing issues
                // with Doctrine's column mapping. We need to normalize keys to ensure consistency.
                // The php-firebird extension appends unique suffixes (e.g. _01) AFTER padding.
                // Examples: "COLUMN   ", "COLUMN   _01"
                // We need to convert them to: "COLUMN", "COLUMN_01"
                // See https://github.com/satwareAG/php-firebird/issues/23
                $normalized = [];
                foreach ($result as $key => $value) {
                    // 1. Remove spaces before suffix (e.g. "   _01" -> "_01")
                    $key = preg_replace('/\s+(?=_\d+$)/', '', (string) $key);
                    // 2. Trim surrounding spaces
                    $normalized[trim((string) $key)] = $value;
                }

                return $normalized;
            }

            $this->free();
            $this->connection->autoCommit();
        }

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

        if (is_resource($this->firebirdResultResource)) {
            return fbird_affected_rows($this->connection->getNativeConnection());
        }

        return 0;
    }

    #[Override]
    public function columnCount(): int
    {
        if (is_resource($this->firebirdResultResource)) {
            return fbird_num_fields($this->firebirdResultResource);
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
     * Benefits:
     * - Type-safe date handling
     * - Timezone-aware operations
     * - No manual string parsing required
     * - Compatible with Doctrine's DateTimeImmutable type mappings
     *
     * @return false|list<mixed> Numeric array with DateTimeImmutable for date columns, or false
     */
    public function fetchNumericWithDateObjects(): array|false
    {
        if (! is_resource($this->firebirdResultResource)) {
            return false;
        }

        // FBIRD_FETCH_DATE_OBJ is available in php-firebird v7.0.0+
        if (! defined('FBIRD_FETCH_DATE_OBJ')) {
            // Fall back to standard fetch if constant not available
            return $this->fetchNumeric();
        }

        $fetchFlags = FBIRD_FETCH_BLOBS | FBIRD_FETCH_DATE_OBJ;
        $result     = fbird_fetch_row($this->firebirdResultResource, $fetchFlags);

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
     * Example:
     *   $row = $result->fetchAssociativeWithDateObjects();
     *   // $row['CREATED_AT'] is DateTimeImmutable, not string "2025-12-22 09:00:00"
     *   echo $row['CREATED_AT']->format('Y-m-d'); // "2025-12-22"
     *
     * @return array<string, mixed>|false Associative array with DateTimeImmutable for date columns, or false
     */
    public function fetchAssociativeWithDateObjects(): array|false
    {
        if (! is_resource($this->firebirdResultResource)) {
            return false;
        }

        // FBIRD_FETCH_DATE_OBJ is available in php-firebird v7.0.0+
        if (! defined('FBIRD_FETCH_DATE_OBJ')) {
            // Fall back to standard fetch if constant not available
            return $this->fetchAssociative();
        }

        $fetchFlags = FBIRD_FETCH_BLOBS | FBIRD_FETCH_DATE_OBJ;
        $result     = fbird_fetch_assoc($this->firebirdResultResource, $fetchFlags);

        if (is_array($result)) {
            // Normalize keys to handle Firebird 3.0+ padded aliases and suffixes
            $normalized = [];
            foreach ($result as $key => $value) {
                // 1. Remove spaces before suffix (e.g. "   _01" -> "_01")
                $key = preg_replace('/\s+(?=_\d+$)/', '', (string) $key);
                // 2. Trim surrounding spaces
                $normalized[trim((string) $key)] = $value;
            }

            return $normalized;
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
        if (! is_resource($this->firebirdResultResource)) {
            $this->firebirdResultResource = null;

            return;
        }

        // Check if resource is valid for fbird_free_result
        // Valid types depend on php-firebird version:
        // - v6.x: 'interbase result' or 'Firebird/InterBase result'
        // - v7.x: 'firebird result' (removed legacy interbase naming)
        // Other types like 'Firebird/InterBase transaction', 'firebird transaction' or 'Unknown' should not be passed
        $type             = get_resource_type($this->firebirdResultResource);
        $validResultTypes = ['interbase result', 'Firebird/InterBase result', 'firebird result'];
        if (! in_array($type, $validResultTypes, true)) {
            // echo "Debug: Skipping fbird_free_result for resource type: $type\n";
            $this->firebirdResultResource = null;

            return;
        }

        fbird_free_result($this->firebirdResultResource);
        $this->firebirdResultResource = null;
    }

    /**
     * Check if DateTimeImmutable fetch is available.
     *
     * Returns true if php-firebird v7.0.0+ with FBIRD_FETCH_DATE_OBJ support
     * is available, false otherwise.
     */
    public static function isDateObjectFetchAvailable(): bool
    {
        return defined('FBIRD_FETCH_DATE_OBJ');
    }
}
