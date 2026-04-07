<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms\Keywords;


use function array_merge;

/**
 * Firebird 5.0 reserved keyword list.
 *
 * Extends Firebird 4 keywords with additions introduced in Firebird 5.0.
 *
 * @link https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref50/firebird-50-language-reference.html#fblangref50-reskeywords-reswords
 */
final class Firebird5Keywords extends Firebird4Keywords
{
    /**
     * {@inheritDoc}
     *
     * Firebird 5.0 added the following reserved keywords:
     * - LATERAL (lateral derived tables, SQL:1999)
     */
    public function getName(): string
    {
        return 'Firebird5';
    }

    protected function getKeywords(): array
    {
        return array_merge(parent::getKeywords(), ['LATERAL']);
    }
}
