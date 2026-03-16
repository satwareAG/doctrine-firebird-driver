<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Platforms\Keywords;

use function array_merge;
use Override;

/**
 * Firebird 4.0 reserved keyword list.
 *
 * Extends Firebird 3 keywords with additions introduced in Firebird 4.0.
 *
 * @link https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref40/firebird-40-language-reference.html#fblangref40-reskeywords-reswords
 */
class Firebird4Keywords extends Firebird3Keywords
{
    #[Override]
    public function getName(): string
    {
        return 'Firebird4';
    }

    /**
     * {@inheritDoc}
     *
     * Firebird 4.0 added the following reserved keywords:
     * - BINARY / VARBINARY (OCTETS synonyms)
     * - DECFLOAT (new numeric type)
     * - INT128 (new integer type)
     * - TIMEZONE_HOUR / TIMEZONE_MINUTE (new EXTRACT fields)
     * - UNBOUNDED (window functions)
     * - WINDOW (window functions)
     */
    #[Override]
    protected function getKeywords(): array
    {
        return array_merge(parent::getKeywords(), [
            'BINARY',
            'DECFLOAT',
            'INT128',
            'TIMEZONE_HOUR',
            'TIMEZONE_MINUTE',
            'UNBOUNDED',
            'VARBINARY',
            'WINDOW',
        ]);
    }
}
