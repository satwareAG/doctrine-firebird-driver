<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
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

use function str_contains;
use function strtolower;

/**
 * Reference https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref40/firebird-40-language-reference.html#fblangref40-appx02-sqlcodes
 *
 * Currently we don't get the GDS Code from the interbase driver, therefor we use the SQL-Code and parse the Message Text
 */
final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, Query|null $query): DriverException
    {
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
                if ($this->exceptionContains($exception, ['cno such file or directory'])) {
                    return new DatabaseDoesNotExist($exception, $query);
                }

                return new ConnectionException($exception, $query);

            case -913: // Deadlock detected.
                return new DeadlockException($exception, $query);

            case -922: // Database connection error.
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
}
