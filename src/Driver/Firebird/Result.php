<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

use function fbird_affected_rows;
use function fbird_close;
use function fbird_fetch_assoc;
use function fbird_fetch_row;
use function fbird_free_result;
use function fbird_num_fields;
use function get_resource_type;
use function is_numeric;
use function is_resource;

use const IBASE_TEXT;

final class Result implements ResultInterface
{
    /**
     * @internal The result can only be instantiated by its driver connection or statement.
     *
     * @param resource|bool|int $fbirdResultRc;
     */
    public function __construct(
        private $fbirdResultRc,
        private readonly Connection $connection,
    ) {
        if (! $this->connection->getConnectionInsertColumn()) {
            return;
        }

        $this->connection->setConnectionInsertTableColumn(null, null);
        $this->connection->setLastInsertId($this->fetchOne());
    }

    /** @throws Exception */
    public function __destruct()
    {
        $this->free();
    }

    /** @inheritDoc */
    public function fetchNumeric()
    {
        if (is_resource($this->fbirdResultRc)) {
            return @fbird_fetch_row($this->fbirdResultRc, IBASE_TEXT);
        }

        return false;
    }

    /** @inheritDoc */
    public function fetchAssociative()
    {
        if (is_resource($this->fbirdResultRc)) {
            return @fbird_fetch_assoc($this->fbirdResultRc, IBASE_TEXT);
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

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
        if (is_numeric($this->fbirdResultRc)) {
            return (int) $this->fbirdResultRc;
        }

        if (is_resource($this->fbirdResultRc)) {
            return @fbird_affected_rows($this->connection->getActiveTransaction());
        }

        return 0;
    }

    public function columnCount(): int
    {
        if (is_resource($this->fbirdResultRc)) {
            return @fbird_num_fields($this->fbirdResultRc);
        }

        return 0;
    }

    /** @throws Exception */
    public function free(): void
    {
        while (is_resource($this->fbirdResultRc) && get_resource_type($this->fbirdResultRc) !== 'Unknown') {
            if (! @fbird_free_result($this->fbirdResultRc)) {
                $this->connection->checkLastApiCall();
            }

            if (@fbird_close($this->fbirdResultRc)) {
                continue;
            }

            $this->connection->checkLastApiCall();
        }

        $this->fbirdResultRc = null;
    }
}
