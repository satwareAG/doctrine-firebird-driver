<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

use function ibase_fetch_assoc;
use function ibase_fetch_row;
use function ibase_free_result;
use function is_resource;

use const IBASE_TEXT;

final class Result implements ResultInterface
{
    /**
     * @internal The result can only be instantiated by its driver connection or statement.
     *
     * @param resource|null $ibaseResultResource;
     */
    public function __construct(
        private $ibaseResultResource,
        private Connection $connection,
        private int $affectedRows = 0,
        private int $columnCount = 0,
    ) {
    }

    /** @inheritDoc */
    public function fetchNumeric()
    {
        if (is_resource($this->ibaseResultResource)) {
            return @ibase_fetch_row($this->ibaseResultResource, IBASE_TEXT);
        }

        return false;
    }

    /** @inheritDoc */
    public function fetchAssociative()
    {
        if (is_resource($this->ibaseResultResource)) {
            return @ibase_fetch_assoc($this->ibaseResultResource, IBASE_TEXT);
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
        return $this->affectedRows;
    }

    public function columnCount(): int
    {
        return $this->columnCount;
    }

    public function free(): void
    {
        if ($this->ibaseResultResource && is_resource($this->ibaseResultResource)) {
            $success = @ibase_free_result($this->ibaseResultResource);
            if (! $success) {
                /**
                 * Essentially untestable because Firebird has a tendency to fail hard with
                 * "Segmentation fault (core dumped)."
                 */
                $this->connection->checkLastApiCall();
            }
        }

        $this->ibaseResultResource = null;
    }

    public function __destruct()
    {
        $this->free();
    }
}
