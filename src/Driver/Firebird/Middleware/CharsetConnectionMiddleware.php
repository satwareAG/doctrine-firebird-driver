<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

use function is_string;
use function mb_convert_encoding;

/**
 * Wraps every prepared Statement in CharsetStatementMiddleware and encodes
 * values passed to quote() from the PHP encoding to the database encoding.
 */
final class CharsetConnectionMiddleware extends AbstractConnectionMiddleware
{
    public function __construct(
        Connection $connection,
        private readonly string $databaseEncoding,
        private readonly string $phpEncoding,
    ) {
        parent::__construct($connection);
    }

    /**
     * Prepare a statement and wrap it so all bound parameters are encoded.
     */
    #[\Override]
    public function prepare(string $sql): Statement
    {
        return new CharsetStatementMiddleware(
            parent::prepare($sql),
            $this->databaseEncoding,
            $this->phpEncoding,
        );
    }

    /**
     * Encode string values from PHP encoding to database encoding before quoting.
     *
     * Note: signature uses untyped $value/$type and no return type hint to
     * match the parent AbstractConnectionMiddleware which was written before
     * PHP 8 union types.
     *
     * {@inheritDoc}
     *
     * @param mixed $value
     * @param mixed $type
     *
     * @return mixed
     */
    #[\Override]
    public function quote($value, $type = ParameterType::STRING)
    {
        if (is_string($value)) {
            $value = mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);
        }

        return parent::quote($value, $type);
    }
}
