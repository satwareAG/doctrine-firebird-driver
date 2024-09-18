<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver;

use Doctrine\DBAL\SQL\Parser\Visitor;

use function count;
use function implode;

/**
 * Firebird Statements only support positional parameters
 */
final class ConvertParameters implements Visitor
{
    private string $convertedSql = ''; // The converted SQL string with positional parameters
    private array $paramMap = [];      // Maps positional parameter indices to named parameters
    private int $paramIndex = 1;       // Tracks the current positional parameter index

    public function acceptPositionalParameter(string $sql): void
    {
        $this->paramMap[$this->paramIndex] = $sql;
        $this->convertedSql .= '?';  // Keep positional parameters as is
        $this->paramIndex++;  // Increment the positional parameter index
    }

    /**
     * Accepts an SQL fragment containing a named parameter
     */
    public function acceptNamedParameter(string $sql): void
    {
        // Extract the named parameter (e.g., :param1)
        $this->paramMap[$this->paramIndex] = $sql;
        $this->convertedSql .= '?';  // Replace with positional placeholder
        $this->paramIndex++;  // Increment the positional parameter index
    }

    public function acceptOther(string $sql): void
    {
        $this->convertedSql .= $sql;  // Append the other SQL fragments directly
    }

    public function getSQL(): string
    {
        return $this->convertedSql;
    }

    /** @return array<array-key, int> */
    public function getParameterMap(): array
    {
        return $this->paramMap;
    }
}
