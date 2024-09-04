<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;

use function sprintf;

/** @psalm-immutable */
final class DatabaseDoesNotExist extends SchemaException
{
    public static function new(string $database): self
    {
        return new self(
            sprintf('Database "%s" does not exist.', $database),
            -902
        );
    }
}
