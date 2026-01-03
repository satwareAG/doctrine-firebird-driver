<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms;

/**
 * Firebird 4.0 introduces TIMESTAMP WITH TIME ZONE and TIME WITH TIME ZONE. These data types allow storage of timestamps with associated time zone information.
 * Todo: Character set OCTETS
 * Since Firebird 4.0 CHAR and VARCHAR with character set OCTETS have synonyms BINARY and VARBINARY.
 * Data in OCTETS encoding are treated as bytes that may not actually be interpreted as characters.
 * OCTETS provides a way to store binary data, which could be the results of some Firebird functions.
 * The database engine has no concept of what it is meant to do with a string of bits in OCTETS,
 * other than just store it and retrieve it. Again, the client side is responsible for validating the data,
 * presenting them in formats that are meaningful to the application and its users and handling any exceptions
 * arising from decoding and encoding them.
 */
use Doctrine\DBAL\Types\Types;

class Firebird4Platform extends Firebird3Platform
{
    /**
     * Firebird 4 returns TIMESTAMP WITH TIME ZONE values as
     * "Y-m-d H:i:s <TimezoneIdentifier>" (example: "2010-04-05 10:10:10 Europe/Berlin").
     */
    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:s e';
    }

    /**
     * Firebird 4 returns TIME WITH TIME ZONE values as
     * "H:i:s <TimezoneIdentifier>" (example: "10:10:10 Europe/Berlin").
     */
    public function getTimeTzFormatString(): string
    {
        return 'H:i:s e';
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, mixed> $column
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUnusedParam
     */
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return 'TIMESTAMP WITH TIME ZONE';
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, mixed> $column
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUnusedParam
     */
    public function getTimeTzTypeDeclarationSQL(array $column): string
    {
        return 'TIME WITH TIME ZONE';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        $this->doctrineTypeMapping['timestamp with time zone'] = Types::DATETIMETZ_MUTABLE;
        $this->doctrineTypeMapping['time with time zone']      = Types::TIME_MUTABLE;
    }
}
