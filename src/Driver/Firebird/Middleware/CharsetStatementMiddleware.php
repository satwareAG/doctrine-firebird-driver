<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Satag\DoctrineFirebirdDriver\Compat\Override;

use function in_array;
use function is_resource;
use function is_string;
use function mb_convert_encoding;
use function stream_get_contents;

/**
 * Encodes all string parameters from the PHP encoding to the database encoding
 * before sending to Firebird.
 *
 * This middleware wraps every Statement so that raw DBAL bindValue() calls
 * (and execute() with inline params) transparently encode strings to the
 * database wire encoding, regardless of whether the ORM type system is used.
 *
 * Binary BLOB data (ParameterType::LARGE_OBJECT) is passed through without
 * transcoding to prevent corruption on the write path (#127 symmetric fix).
 * The ParameterType itself distinguishes binary (LARGE_OBJECT) from text
 * (STRING), replacing the earlier NULL-byte heuristic (#148/#149).
 */
final class CharsetStatementMiddleware extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly string $databaseEncoding,
        private readonly string $phpEncoding,
    ) {
        parent::__construct($statement);
    }

    #[Override]
    public function bindValue(string|int $param, mixed $value, ParameterType $type): void
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (is_string($value) && in_array($type, [ParameterType::STRING, ParameterType::ASCII], true)) {
            $value = $this->encodeString($value);
        }

        parent::bindValue($param, $value, $type);
    }

    #[Override]
    public function execute(): ResultInterface
    {
        return new CharsetResultMiddleware(
            parent::execute(),
            $this->databaseEncoding,
            $this->phpEncoding,
        );
    }

    /**
     * Encode a string from PHP encoding to database encoding.
     *
     * Called only for STRING and ASCII parameter types. LARGE_OBJECT (binary
     * BLOB) never reaches this method — it is passed through unchanged in
     * bindValue().
     *
     * Lenient on invalid bytes: returns the original value on conversion
     * failure rather than throwing. Bound parameters are data, not syntax -
     * crashing on bad data is worse than mojibake. See encodeSql() in
     * CharsetConnectionMiddleware for the stricter treatment applied to
     * SQL body literals.
     *
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/117
     */
    private function encodeString(string $value): string
    {
        $encoded = @mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);

        return $encoded === false ? $value : $encoded;
    }
}
