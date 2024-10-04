<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

use function array_values;
use function fbird_affected_rows;
use function fbird_close;
use function fbird_fetch_assoc;
use function fbird_fetch_row;
use function fbird_free_result;
use function fbird_num_fields;
use function get_resource_type;
use function is_array;
use function is_numeric;
use function is_resource;

use const IBASE_TEXT;

final class Result implements ResultInterface
{
    /**
     * @internal The result can only be instantiated by its driver connection or statement.
     *
     * @param bool|int|resource|null $firebirdResultResource;
     * @psalm-param bool|int|resource|null $firebirdResultResource;
     *
     * @throws Exception
     */
    public function __construct(
        private mixed $firebirdResultResource,
        private readonly Connection $connection,
    ) {
        if ($this->connection->getConnectionInsertColumn() === null) {
            return;
        }

        $this->connection->setConnectionInsertColumn(null);
        $this->connection->setLastInsertId((int) $this->fetchOne());
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
            $result = @fbird_fetch_row($this->firebirdResultResource, IBASE_TEXT);
            if (is_array($result)) {
                return array_values($result);
            }
        }

        return false;
    }

    /** @inheritDoc */
    public function fetchAssociative()
    {
        if (is_resource($this->firebirdResultResource)) {
            return @fbird_fetch_assoc($this->firebirdResultResource, IBASE_TEXT);
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
            return (int) $this->firebirdResultResource;
        }

        if (is_resource($this->firebirdResultResource)) {
            return @fbird_affected_rows($this->connection->getActiveTransaction());
        }

        return 0;
    }

    public function columnCount(): int
    {
        if (is_resource($this->firebirdResultResource)) {
            return @fbird_num_fields($this->firebirdResultResource);
        }

        return 0;
    }

    /** @throws Exception */
    public function free(): void
    {
        if (! is_resource($this->firebirdResultResource)) {
            return;
        }

        $type = get_resource_type($this->firebirdResultResource);
        if ($type !== 'interbase result') {
            return;
        }

        @fbird_free_result($this->firebirdResultResource);
        @fbird_close($this->firebirdResultResource);
    }
}
