<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Override;

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
    #[Override]
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
     * {@inheritDoc}
     */
    #[Override]
    public function quote(string $value): string
    {
        $value = mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);

        return parent::quote($value);
    }
}
