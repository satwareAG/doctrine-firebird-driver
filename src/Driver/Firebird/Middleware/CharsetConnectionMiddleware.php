<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\Exception\CharsetConversionException;

use function mb_convert_encoding;

/** @psalm-suppress DeprecatedInterface */
final class CharsetConnectionMiddleware extends AbstractConnectionMiddleware
{
    /** The wrapped driver connection (stored for middleware chain traversal). */
    private readonly Connection $driverConnection;

    public function __construct(
        Connection $connection,
        private readonly string $databaseEncoding,
        private readonly string $phpEncoding,
    ) {
        parent::__construct($connection);
        $this->driverConnection = $connection;
    }

    /**
     * Returns the wrapped driver connection.
     *
     * DBAL4 removes getWrappedConnection(); this method allows
     * ConnectionWrapper to traverse the middleware chain and find
     * the underlying Firebird driver connection.
     */
    public function getWrappedDriverConnection(): Connection
    {
        return $this->driverConnection;
    }

    /**
     * Re-encode the SQL body from PHP encoding to database encoding, then prepare
     * and wrap the resulting Statement so bound parameters are encoded too.
     *
     * Re-encoding the SQL body is required because QueryBuilder literal fragments
     * (raw strings embedded in WHERE clauses via andWhere()/orWhere()) and other
     * inline string literals are concatenated verbatim into the final SQL by
     * DBAL and never flow through bindValue(). ASCII bytes (0x00-0x7F) pass
     * through mb_convert_encoding unchanged, so SQL syntax, identifiers and
     * placeholders (?, :name) are preserved.
     */
    #[Override]
    public function prepare(string $sql): Statement
    {
        return new CharsetStatementMiddleware(
            parent::prepare($this->encodeSql($sql)),
            $this->databaseEncoding,
            $this->phpEncoding,
        );
    }

    /**
     * Re-encode the SQL body and wrap the Result so fetched rows decode back to
     * the PHP encoding.
     *
     * DBAL Connection::executeQuery() takes a parameterless shortcut through
     * Driver\Connection::query() (see vendor/doctrine/dbal/src/Connection.php
     * around line 1106). Without this override the entire charset middleware
     * chain is bypassed for parameterless SELECTs (GH-116).
     *
     * Binary BLOB detection uses a NULL-byte heuristic in CharsetResultMiddleware.
     * jane: replace with fbird_field_info()['sub_type'] when php-firebird exposes it.
     */
    #[Override]
    public function query(string $sql): Result
    {
        return new CharsetResultMiddleware(
            parent::query($this->encodeSql($sql)),
            $this->databaseEncoding,
            $this->phpEncoding,
        );
    }

    /**
     * Re-encode the SQL body for parameterless DML.
     *
     * DBAL Connection::executeStatement() takes a parameterless shortcut through
     * Driver\Connection::exec() (see vendor/doctrine/dbal/src/Connection.php
     * around line 1216). Without this override UTF-8 literals in parameterless
     * INSERT/UPDATE/DELETE statements would reach Firebird un-transcoded.
     *
     * Returns the affected-row count from the inner driver unchanged.
     */
    #[Override]
    public function exec(string $sql): int|string
    {
        return parent::exec($this->encodeSql($sql));
    }

    /**
     * Encode string values from PHP encoding to database encoding before quoting.
     *
     * Lenient on invalid bytes: relies on PHP's default mb_substitute_character
     * substitution rather than throwing. Bound values are data, not syntax -
     * crashing on bad data is worse than mojibake. See encodeSql() for the
     * stricter treatment applied to SQL body literals.
     *
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/117
     *
     * Note: signature uses untyped $value/$type and no return type hint to
     * match the parent AbstractConnectionMiddleware which was written before
     * PHP 8 union types. DBAL 4.x tightens this signature; reconciliation is
     * tracked separately from GH-116.
     *
     * {@inheritDoc}
     */
    #[Override]
    public function quote(string $value): string
    {
        $encoded = mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);

        return parent::quote($encoded === false ? $value : $encoded);
    }

    /**
     * Re-encode the SQL body from PHP encoding to database encoding.
     *
     * The `false` return guard is defense-in-depth: under PHP 8.x,
     * mb_convert_encoding substitutes invalid source bytes (or throws
     * ValueError for unknown encoding names) rather than returning false.
     * The guard exists to satisfy PHPStan's `string|false` return type and
     * to fail loudly should the runtime ever return false (e.g. via a custom
     * mb_substitute_character configuration).
     */
    private function encodeSql(string $sql): string
    {
        $encoded = @mb_convert_encoding($sql, $this->databaseEncoding, $this->phpEncoding);

        if ($encoded === false) {
            throw CharsetConversionException::conversionFailure(
                'SQL body',
                $this->phpEncoding,
                $this->databaseEncoding,
            );
        }

        return $encoded;
    }
}
