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
use function strpos;

/**
 * Encodes all string parameters from the PHP encoding to the database encoding
 * before sending to Firebird.
 *
 * This middleware wraps every Statement so that raw DBAL bindValue() calls
 * (and execute() with inline params) transparently encode strings to the
 * database wire encoding, regardless of whether the ORM type system is used.
 *
 * Binary BLOB data (containing NULL bytes) is passed through without
 * transcoding to prevent corruption on the write path (#127 symmetric fix).
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

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (is_string($value) && in_array($type, [ParameterType::STRING, ParameterType::ASCII, ParameterType::LARGE_OBJECT], true)) {
            $value = $this->encodeString($value);
        }

        return parent::bindValue($param, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function execute($params = null): ResultInterface
    {
        // If inline params are passed (deprecated path), encode them first
        if ($params !== null) {
            foreach ($params as $key => $value) {
        // Note: This checks for PHP stream resources (BLOB content passed as
        // php_stream for LARGE_OBJECT params), NOT Firebird handle objects.
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }

                if (! is_string($value)) {
                    continue;
                }

                $params[$key] = $this->encodeString($value);
            }
        }

        return new CharsetResultMiddleware(
            parent::execute($params),
            $this->databaseEncoding,
            $this->phpEncoding,
        );
    }

    /**
     * Encode a string from PHP encoding to database encoding.
     *
     * Binary strings containing NULL bytes are passed through without
     * transcoding. This prevents corruption of binary BLOB data (JPEG, PNG,
     * etc.) that would be mangled by mb_convert_encoding (e.g., \xFF → ?).
     *
     * Lenient on invalid bytes: returns the original value on conversion
     * failure rather than throwing. Bound parameters are data, not syntax -
     * crashing on bad data is worse than mojibake. See encodeSql() in
     * CharsetConnectionMiddleware for the stricter treatment applied to
     * SQL body literals.
     *
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/117
     *
     * NULL-byte heuristic for write path. No result resource is available at
     * bind time, so fbird_field_info() cannot be used. Binary data containing
     * NULL bytes is passed through without transcoding.
     */
    private function encodeString(string $value): string
    {
        if (strpos($value, "\x00") !== false) {
            return $value;
        }

        $encoded = @mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);

        return $encoded === false ? $value : $encoded;
    }
}
