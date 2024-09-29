<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver;

use Doctrine\DBAL\Exception;

use function sprintf;

/**
 * Doctrine\DBAL\ConnectionException
 *
 * @psalm-immutable
 */
class SQLParserUtilsException extends Exception
{
    public static function missingParam(string $paramName): SQLParserUtilsException
    {
        return new self(
            sprintf('Value for :%1$s not found in params array. Params array key should be "%1$s"', $paramName),
        );
    }

    public static function missingType(string $typeName): SQLParserUtilsException
    {
        return new self(
            sprintf('Value for :%1$s not found in types array. Types array key should be "%1$s"', $typeName),
        );
    }
}
