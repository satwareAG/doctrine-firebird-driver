<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\SQL\Parser;

/**
 * SQL parser visitor
 *
 * Copied from Doctrine\DBAL\SQL\Parser\Visitor to avoid Symfony DebugClassLoader
 *
 * @internal deprecation notices triggered by cross-vendor implementation.
 */
interface Visitor
{
    /**
     * Accepts an SQL fragment containing a positional parameter
     */
    public function acceptPositionalParameter(string $sql): void;

    /**
     * Accepts an SQL fragment containing a named parameter
     */
    public function acceptNamedParameter(string $sql): void;

    /**
     * Accepts other SQL fragments
     */
    public function acceptOther(string $sql): void;
}
