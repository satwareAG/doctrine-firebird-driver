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
 */
final class CharsetStatementMiddleware extends AbstractStatementMiddleware
{
    /** @var array<int|string, int> */
    private array $boundTypes = [];

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
        $this->boundTypes[$param] = $type;

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (is_string($value) && in_array($type, [ParameterType::STRING, ParameterType::ASCII, ParameterType::LARGE_OBJECT], true)) {
            $value = mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);
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

                $params[$key] = mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);
            }
        }

        return new CharsetResultMiddleware(
            parent::execute($params),
            $this->databaseEncoding,
            $this->phpEncoding,
            $this->boundTypes,
        );
    }
}
