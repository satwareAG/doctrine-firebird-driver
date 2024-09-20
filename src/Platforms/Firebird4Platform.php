<?php
namespace Satag\DoctrineFirebirdDriver\Platforms;

use Doctrine\DBAL\Exception;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\Firebird3Keywords;
use Satag\DoctrineFirebirdDriver\Platforms\SQL\Builder\FirebirdSelectSQLBuilder;

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
 *
 */
class Firebird4Platform extends Firebird3Platform
{


}
