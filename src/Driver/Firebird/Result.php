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
use function is_array;
use function is_numeric;
use function is_resource;

use const IBASE_FETCH_BLOBS;

final class Result implements ResultInterface
{
    /**
     * @internal The result can only be instantiated by its driver connection or statement.
     *
     * @throws Exception
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
    public function fetchNumeric()
    {
        if (is_resource($this->firebirdResultResource)) {
            // @todo remove @ when fbird_fetch_row() doesn't warn on normal end of fetch or closed cursor
            // Warning "Invalid cursor" is emitted when fetching from a closed/reused statement's result in some cases
            $result = fbird_fetch_row($this->firebirdResultResource, IBASE_FETCH_BLOBS);
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

    /** @inheritDoc */
    public function fetchAssociative()
    {
        if (is_resource($this->firebirdResultResource)) {
            // @todo remove @ when fbird_fetch_assoc() doesn't warn
            $result = fbird_fetch_assoc($this->firebirdResultResource, IBASE_FETCH_BLOBS);
            if (is_array($result)) {
                return $result;
            }

            $this->free();
            $this->connection->autoCommit();
        }

        return false;
    }

    /** @inheritDoc */
    public function fetchOne()
    {
        return FetchUtils::fetchOne($this);
    }

    /** @inheritDoc */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /** @inheritDoc */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /** @inheritDoc */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function rowCount(): int
    {
        if (is_numeric($this->firebirdResultResource)) {
            /** @psalm-suppress RedundantCast */
            $result = (int) $this->firebirdResultResource;
            return $result;
        }

        if (is_resource($this->firebirdResultResource)) {
            $result = fbird_affected_rows($this->connection->getNativeConnection());
            return $result;
        }
        return 0;
    }

    public function columnCount(): int
    {
        if (is_resource($this->firebirdResultResource)) {
            return fbird_num_fields($this->firebirdResultResource);
        }

        return 0;
    }

    /** @throws Exception */
    public function free(): void
    {
        if (! is_resource($this->firebirdResultResource)) {
            $this->firebirdResultResource = null;

            return;
        }

        // Check if resource is valid for fbird_free_result
        // Valid types are typically 'interbase result' or 'Firebird/InterBase result'
        // Other types like 'Firebird/InterBase transaction' or 'Unknown' should not be passed
        $type = get_resource_type($this->firebirdResultResource);
        if ($type !== 'interbase result' && $type !== 'Firebird/InterBase result') {
            // echo "Debug: Skipping fbird_free_result for resource type: $type\n";
            $this->firebirdResultResource = null;

            return;
        }

        fbird_free_result($this->firebirdResultResource);
        $this->firebirdResultResource = null;
    }
}
