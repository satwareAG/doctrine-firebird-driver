<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

use function fbird_fetch_assoc;
use function fbird_fetch_row;
use function fbird_free_result;
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
        private Connection $connection
    ) {
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

    public function rowCount(): int
    {
        if (is_numeric($this->fbirdResultRc)) {
            return (int)$this->fbirdResultRc;
        } elseif (is_resource($this->fbirdResultRc)) {
            $rowCount = @fbird_affected_rows($this->connection->getActiveTransaction());
            if ($rowCount === 1 && $this->connection->getConnectionInsertColumn() ) {
                $this->connection->setLastInsertId($this->fetchOne());
            }
            return $rowCount;
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

    public function free(): void
    {
        while(is_resource($this->fbirdResultRc) && get_resource_type($this->fbirdResultRc) !== 'Unknown' ) {
            if(!@fbird_free_result($this->fbirdResultRc)) {
                $this->connection->checkLastApiCall();
            }
            if (!@fbird_close($this->fbirdResultRc)) {
                $this->connection->checkLastApiCall();
            }
        }
        $this->fbirdResultRc = null;
    }

    public function __destruct()
    {
        $this->free();
    }
}
