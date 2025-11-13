<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms\Exception;

use InvalidArgumentException;

use function sprintf;

/**
 * Exception thrown when platform configuration parameters are invalid.
 *
 * Provides actionable error messages to help users correct configuration issues.
 *
 * phpcs:disable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
 */
final class InvalidConfigurationException extends InvalidArgumentException
{
    /**
     * Creates an exception for type mismatch errors.
     *
     * @param string $parameterName Name of the configuration parameter
     * @param string $expectedType  Expected type (e.g., 'integer', 'string')
     * @param string $actualType    Actual type detected
     */
    public static function typeMismatch(
        string $parameterName,
        string $expectedType,
        string $actualType,
    ): self {
        return new self(sprintf(
            'Configuration parameter "%s" must be of type %s, %s given.',
            $parameterName,
            $expectedType,
            $actualType,
        ));
    }

    /**
     * Creates an exception for out-of-range errors.
     *
     * @param string $parameterName Name of the configuration parameter
     * @param int    $minValue      Minimum allowed value
     * @param int    $maxValue      Maximum allowed value
     * @param int    $actualValue   Actual value provided
     */
    public static function rangeValidation(
        string $parameterName,
        int $minValue,
        int $maxValue,
        int $actualValue,
    ): self {
        return new self(sprintf(
            'Configuration parameter "%s" must be between %d and %d, %d given.',
            $parameterName,
            $minValue,
            $maxValue,
            $actualValue,
        ));
    }
}
