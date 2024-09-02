<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

final class Result implements ResultInterface
{
    /**
     * Number of rows affected by last execute
     * @var integer
     */
    protected $affectedRows = 0;

    /**
     * @var int
     */
    protected $numFields = 0;
    private $columnCount = 0;
    private Connection $connection;
    /**
     * @var resource
     */
    private $ibaseResultResource;

    /**
     * @param resource|null $ibaseResultResource;
     *@internal The result can only be instantiated by its driver connection or statement.
     */
    public function __construct($ibaseResultResource, Connection $connection, $affectedRows, $columnCount)
    {
        $this->ibaseResultResource = $ibaseResultResource;
        $this->affectedRows = $affectedRows;
        $this->columnCount = $columnCount;
        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     */
    public function fetchNumeric()
    {
        if (is_resource($this->ibaseResultResource)) {
            return @ibase_fetch_row($this->ibaseResultResource, IBASE_TEXT);
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fetchAssociative()
    {
        if (is_resource($this->ibaseResultResource)) {
            return @ibase_fetch_assoc($this->ibaseResultResource, IBASE_TEXT);
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fetchOne()
    {
        return FetchUtils::fetchOne($this);
    }

    /**
     * @inheritDoc
     */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /**
     * @inheritDoc
     */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /**
     * @inheritDoc
     */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    /**
     * @inheritDoc
     */
    public function columnCount(): int
    {
        return $this->columnCount;
    }

    /**
     * @inheritDoc
     */
    public function free(): void
    {
        if ($this->ibaseResultResource && is_resource($this->ibaseResultResource)) {
            $success = @ibase_free_result($this->ibaseResultResource);
            if (!$success) {
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
