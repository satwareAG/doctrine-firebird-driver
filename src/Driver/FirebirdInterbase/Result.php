<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;

class Result implements ResultInterface
{
    /**
     * @var resource
     */
    private $statement;

    /**
     * @internal The result can only be instantiated by its driver connection or statement.
     * @param resource $statement
     */
    public function __construct($statement)
    {
        $this->statement = $statement;
    }

    /**
     * @inheritDoc
     */
    public function fetchNumeric()
    {
        return @ibase_fetch_row($this->statement, IBASE_TEXT);
    }

    /**
     * @inheritDoc
     */
    public function fetchAssociative()
    {
        return @ibase_fetch_assoc($this->statement, IBASE_TEXT);
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
        // TODO: Implement columnCount() method.
    }

    /**
     * @inheritDoc
     */
    public function free(): void
    {
        // TODO: Implement free() method.
    }
}
