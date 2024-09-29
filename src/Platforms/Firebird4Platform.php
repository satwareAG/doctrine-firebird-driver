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
class Firebird4Platform extends Firebird3Platform
{
}
