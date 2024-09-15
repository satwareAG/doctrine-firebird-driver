<?php

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
    /**
     * @param string $paramName
     *
     * @return SQLParserUtilsException
     */
    public static function missingParam($paramName): SQLParserUtilsException
    {
        return new self(
            sprintf('Value for :%1$s not found in params array. Params array key should be "%1$s"', $paramName)
        );
    }

    /**
     * @param string $typeName
     *
     * @return SQLParserUtilsException
     */
    public static function missingType($typeName): SQLParserUtilsException
    {
        return new self(
            sprintf('Value for :%1$s not found in types array. Types array key should be "%1$s"', $typeName)
        );
    }
}
