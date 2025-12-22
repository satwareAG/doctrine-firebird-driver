<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

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

use const FBIRD_FETCH_BLOBS;
use Override;

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
                return $result;
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
        $type = get_resource_type($this->firebirdResultResource);
        $validResultTypes = ['interbase result', 'Firebird/InterBase result', 'firebird result'];
        if (! in_array($type, $validResultTypes, true)) {
            // echo "Debug: Skipping fbird_free_result for resource type: $type\n";
            $this->firebirdResultResource = null;

            return;
        }

        fbird_free_result($this->firebirdResultResource);
        $this->firebirdResultResource = null;
    }
}
