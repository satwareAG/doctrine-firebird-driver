<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Override;

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
 * transparently encode strings to the database wire encoding, regardless of
 * whether the ORM type system is used.
 */
final class CharsetStatementMiddleware extends AbstractStatementMiddleware
{
    /** @var array<int|string, ParameterType> */
    private array $boundTypes = [];

    public function __construct(
        Statement $statement,
        private readonly string $databaseEncoding,
        private readonly string $phpEncoding,
    ) {
        parent::__construct($statement);
    }

    /**
     * Encode string parameters from PHP encoding to database encoding before binding.
     *
     * For TEXT BLOB columns, stream resources are extracted and transcoded.
     * Supported parameter types: STRING, ASCII, and LARGE_OBJECT.
     *
     * {@inheritDoc}
     */
    #[Override]
    public function bindValue(string|int $param, mixed $value, ParameterType $type): void
    {
        $this->boundTypes[$param] = $type;

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (is_string($value) && in_array($type, [ParameterType::STRING, ParameterType::ASCII, ParameterType::LARGE_OBJECT], true)) {
            $value = mb_convert_encoding($value, $this->databaseEncoding, $this->phpEncoding);
        }

        parent::bindValue($param, $value, $type);
    }

    /**
     * Execute the statement and wrap the result in CharsetResultMiddleware.
     *
     * {@inheritDoc}
     */
    #[Override]
    public function execute(): ResultInterface
    {
        return new CharsetResultMiddleware(
            parent::execute(),
            $this->databaseEncoding,
            $this->phpEncoding,
            $this->boundTypes,
        );
    }
}
