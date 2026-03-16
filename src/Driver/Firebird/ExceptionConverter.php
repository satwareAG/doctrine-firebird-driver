<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Exception\DatabaseObjectExistsException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;

use function class_exists;
use function str_contains;
use function strtolower;
use function substr;
use Override;

/**
 * Reference https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref40/firebird-40-language-reference.html#fblangref40-appx02-sqlcodes
 *
 * Currently we don't get the GDS Code from the interbase driver, therefor we use the SQL-Code and parse the Message Text
 */
final class ExceptionConverter implements ExceptionConverterInterface
{
    #[Override]
    public function convert(Exception $exception, Query|null $query): DriverException
    {
        // SQLSTATE-based classification (php-firebird v7.0.0-rc.6+ Exception Mode)
        // When Firebird\Exception is thrown with Exception Mode enabled, we can use
        // the standardized SQLSTATE codes for more accurate error classification
        if (class_exists(\Firebird\Exception::class, false)) {
            if ($exception instanceof \Firebird\Exception) {
                $sqlState = $exception->getSqlState();
                if ($sqlState !== '') {
                    $result = $this->convertBySqlState($sqlState, $exception, $query);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        }

        // Fall back to SQLCODE-based classification for backward compatibility
        switch ($exception->getCode()) {
            case -104: // Syntax Error (multiple specific causes, such as invalid syntax, unexpected tokens).
                return new SyntaxErrorException($exception, $query);

            case -204: // Object not found (e.g., table, view, procedure).
                if ($this->exceptionContains($exception, ['table unknown'])) {
                    return new TableNotFoundException($exception, $query);
                }

                if ($this->exceptionContains($exception, ['ambiguous field name'])) {
                    return new NonUniqueFieldNameException($exception, $query);
                }

                return new DatabaseObjectNotFoundException($exception, $query);

            case -206: // Column or field unknown; the SQL engine cannot find a column or field with the specified name
                if ($this->exceptionContains($exception, ['column unknown'])) {
                    return new InvalidFieldNameException($exception, $query);
                }

                break;

            case -303: // arithmetic exception, numeric overflow, or string truncation string right truncation
            case -315: // Cannot change datatype for columns.s
            case -406: // Subscript out of bounds.
            case -413: // Conversion error from string.
            case -501: // Subscript out of bounds.
            case -530: // Foreign key violation.
                return new ForeignKeyConstraintViolationException($exception, $query);

            case -607:
                if ($this->exceptionContains($exception, ['already exist'])) {
                    return new TableExistsException($exception, $query);
                }

                if ($this->exceptionContains($exception, ['does not exist'])) {
                    return new TableNotFoundException($exception, $query);
                }

                if ($this->exceptionContains($exception, ['not found', 'not defined'])) {
                    return new DatabaseObjectNotFoundException($exception, $query);
                }

                break;
            case -625:
                if ($this->exceptionContains($exception, ['value "*** null ***"'])) {
                    return new NotNullConstraintViolationException($exception, $query);
                }

                break;
            case -803: // Unique constraint violation.
                return new UniqueConstraintViolationException($exception, $query);

            case -804:
                if ($this->exceptionContains($exception, ['data type unknown'])) {
                    return new DriverException($exception, $query);
                }

                return new NotNullConstraintViolationException($exception, $query);

            case -901: // General engine error.
            case -902: // Internal errors, database corruption, or connection issues.
                if ($this->exceptionContains($exception, ['no such file or directory'])) {
                    return new DatabaseDoesNotExist($exception, $query);
                }

                if ($this->exceptionContains($exception, ['transaction deadlock'])) {
                    return new DeadlockException($exception, $query);
                }

                // GDS codes 335544721 (net write error), 335544723 (database connection lost),
                // 335544726 (net read error) — connection dropped mid-session
                if ($this->exceptionContains($exception, [
                    'net write error',
                    'net read error',
                    'lost remote part of database',
                    'connection lost to database',
                    'broken pipe',
                ])) {
                    return new ConnectionLost($exception, $query);
                }

                return new ConnectionException($exception, $query);

            case -913: // Deadlock detected.
                return new DeadlockException($exception, $query);

            case -922: // Database connection error.
                // GDS 335544723: database connection lost — arrives as -922 in some Firebird versions
                if ($this->exceptionContains($exception, [
                    'net write error',
                    'net read error',
                    'lost remote part of database',
                    'connection lost to database',
                    'broken pipe',
                ])) {
                    return new ConnectionLost($exception, $query);
                }

                return new ConnectionException($exception, $query);

            case -955: // Object already exists. Happens during attempts to create an object that duplicates an existing one.
                if ($this->exceptionContains($exception, ['already exists'])) {
                    return new DatabaseObjectExistsException($exception, $query);
                }

                break;

            case -979: // Lock wait timeout, usually during transactional conflicts.
                return new LockWaitTimeoutException($exception, $query);
        }

        return new DriverException($exception, $query);
    }

    /** @param string[] $keywords */
    private function exceptionContains(Exception $exception, array $keywords): bool
    {
        $normalizedMessage = strtolower($exception->getMessage());
        foreach ($keywords as $keyword) {
            if (str_contains($normalizedMessage, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert exception using SQLSTATE code (SQL:2003 standard).
     *
     * SQLSTATE format: Class (2 chars) + Subclass (3 chars)
     * Only the class (first 2 characters) is used for classification.
     *
     * @link https://en.wikipedia.org/wiki/SQLSTATE
     * @link https://www.firebirdsql.org/file/documentation/html/en/refdocs/fblangref50/firebird-50-language-reference.html#fblangref50-appx02-sqlstates
     *
     * @return DriverException|null Returns specific exception or null to fall back to SQLCODE
     */
    private function convertBySqlState(string $sqlState, Exception $exception, Query|null $query): DriverException|null
    {
        // Extract SQLSTATE class (first 2 characters)
        $class = substr($sqlState, 0, 2);

        return match ($class) {
            // Class 08: Connection Exception
            // 08006 = connection failure, 08007 = transaction resolution unknown — both indicate lost connection
            '08' => match ($sqlState) {
                '08006', '08007' => new ConnectionLost($exception, $query),
                default          => new ConnectionException($exception, $query),
            },

            // Class 21: Cardinality Violation
            '21' => new DriverException($exception, $query),

            // Class 22: Data Exception (includes string truncation, numeric overflow)
            '22' => new DriverException($exception, $query),

            // Class 23: Integrity Constraint Violation
            '23' => $this->convertConstraintViolation($sqlState, $exception, $query),

            // Class 28: Invalid Authorization Specification
            '28' => new ConnectionException($exception, $query),

            // Class 40: Transaction Rollback (includes deadlock)
            '40' => new DeadlockException($exception, $query),

            // Class 42: Syntax Error or Access Rule Violation
            '42' => new SyntaxErrorException($exception, $query),

            // Class HY: General Error (fallback)
            'HY' => null, // Fall back to SQLCODE classification

            // Unknown SQLSTATE class - fall back to SQLCODE
            default => null,
        };
    }

    /**
     * Convert Class 23 (Integrity Constraint Violation) to specific exceptions.
     *
     * Subclass analysis for more accurate classification:
     * - 23000: Generic integrity constraint violation
     * - 23001: Restrict violation (e.g., foreign key)
     * - 23502: NOT NULL constraint violation
     * - 23503: Foreign key constraint violation
     * - 23505: Unique constraint violation
     */
    private function convertConstraintViolation(string $sqlState, Exception $exception, Query|null $query): DriverException
    {
        return match ($sqlState) {
            '23502' => new NotNullConstraintViolationException($exception, $query),
            '23503' => new ForeignKeyConstraintViolationException($exception, $query),
            '23505' => new UniqueConstraintViolationException($exception, $query),
            default => new DriverException($exception, $query),
        };
    }
}
