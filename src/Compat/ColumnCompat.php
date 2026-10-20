<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Compat;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

use function method_exists;

/**
 * Compatibility helpers for DBAL 4.4 / 4.5 Column API divergence.
 *
 * DBAL 4.5 (doctrine/dbal#7490) deprecates Column::getType() in favour of
 * Column::getTypeName() and resolves the Type instance through the registry.
 * Feature detection (method_exists) keeps one code base on both API shapes;
 * no version sniffing.
 *
 * @see https://github.com/doctrine/dbal/pull/7490
 */
final class ColumnCompat
{
    /**
     * Returns the Type instance of the column, resolved through the registry.
     */
    public static function type(Column $column): Type
    {
        if (method_exists($column, 'getTypeName')) {
            return Type::getType($column->getTypeName());
        }

        return $column->getType();
    }
}
