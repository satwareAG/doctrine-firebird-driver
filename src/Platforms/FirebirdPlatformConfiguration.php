<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Satag\DoctrineFirebirdDriver\Platforms\Exception\InvalidConfigurationException;

use function array_key_exists;
use function get_debug_type;
use function is_int;

/**
 * Configuration for Firebird platform-specific parameters.
 *
 * Allows customizing platform behavior that varies by deployment requirements,
 * such as VARCHAR CAST length for LIKE operations.
 */
final class FirebirdPlatformConfiguration
{
    /**
     * Default CAST length for VARCHAR in LIKE operations.
     *
     * Historical default: 255 characters
     * Maximum allowed: 8191 characters (Firebird UTF8 varchar limit)
     */
    private const DEFAULT_LIKE_CAST_LENGTH = 255;

    /**
     * Minimum allowed CAST length.
     */
    private const MIN_LIKE_CAST_LENGTH = 1;

    /**
     * Maximum allowed CAST length (Firebird UTF8 varchar limit).
     *
     * @link https://firebirdsql.org/file/documentation/chunk/en/refdocs/fblangref40/fblangref40-datatypes-chartypes.html
     */
    private const MAX_LIKE_CAST_LENGTH = 8191;

    /**
     * Default folding of unquoted identifiers.
     *
     * Firebird stores and compares unquoted identifiers in upper-case.
     */
    private const DEFAULT_UNQUOTED_IDENTIFIER_FOLDING = UnquotedIdentifierFolding::UPPER;

    /**
     * Configured LIKE CAST length.
     */
    private int $likeCastLength;

    /**
     * Configured folding of unquoted identifiers.
     */
    private UnquotedIdentifierFolding $unquotedIdentifierFolding;

    /**
     * Creates a new platform configuration.
     *
     * @param array<string, mixed> $options Configuration options
     *
     * @throws InvalidConfigurationException If configuration is invalid.
     */
    public function __construct(array $options = [])
    {
        // Use default if key doesn't exist, but validate if explicitly provided
        $value = array_key_exists('like_cast_length', $options)
            ? $options['like_cast_length']
            : self::DEFAULT_LIKE_CAST_LENGTH;

        $this->likeCastLength = $this->validateLikeCastLength($value);

        $folding = array_key_exists('unquoted_identifier_folding', $options)
            ? $options['unquoted_identifier_folding']
            : self::DEFAULT_UNQUOTED_IDENTIFIER_FOLDING;

        $this->unquotedIdentifierFolding = $this->validateUnquotedIdentifierFolding($folding);
    }

    /**
     * Returns the configured LIKE CAST length.
     *
     * This value determines the VARCHAR length used when casting columns
     * in LIKE operations to ensure proper string comparison.
     *
     * @return int CAST length (1-8191)
     */
    public function getLikeCastLength(): int
    {
        return $this->likeCastLength;
    }

    /**
     * Returns the configured folding of unquoted identifiers.
     *
     * Forwarded to the AbstractPlatform constructor so DBAL does not flag
     * instantiation as deprecated (dbal#6823). Firebird folds unquoted
     * identifiers to upper-case, hence the default.
     */
    public function getUnquotedIdentifierFolding(): UnquotedIdentifierFolding
    {
        return $this->unquotedIdentifierFolding;
    }

    /**
     * Validates the LIKE CAST length parameter.
     *
     * @param mixed $value Value to validate
     *
     * @return int Validated cast length
     *
     * @throws InvalidConfigurationException If value is invalid.
     */
    private function validateLikeCastLength(mixed $value): int
    {
        if (! is_int($value)) {
            throw InvalidConfigurationException::typeMismatch(
                'like_cast_length',
                'integer',
                get_debug_type($value),
            );
        }

        if ($value < self::MIN_LIKE_CAST_LENGTH || $value > self::MAX_LIKE_CAST_LENGTH) {
            throw InvalidConfigurationException::rangeValidation(
                'like_cast_length',
                self::MIN_LIKE_CAST_LENGTH,
                self::MAX_LIKE_CAST_LENGTH,
                $value,
            );
        }

        return $value;
    }

    /**
     * Validates the unquoted identifier folding parameter.
     *
     * @param mixed $value Value to validate
     *
     * @throws InvalidConfigurationException If value is invalid.
     */
    private function validateUnquotedIdentifierFolding(mixed $value): UnquotedIdentifierFolding
    {
        if (! $value instanceof UnquotedIdentifierFolding) {
            throw InvalidConfigurationException::typeMismatch(
                'unquoted_identifier_folding',
                UnquotedIdentifierFolding::class,
                get_debug_type($value),
            );
        }

        return $value;
    }
}
