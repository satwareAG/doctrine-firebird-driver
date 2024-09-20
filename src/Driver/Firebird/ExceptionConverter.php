<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Driver\Exception\UnknownParameterType;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;

use function preg_match;

final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, Query|null $query): DriverException
    {
        $message = $exception->getCode() . ': ' . $exception->getMessage();
        switch ($exception->getCode()) {
            case -104:
                return new SyntaxErrorException($exception, $query);

            case -204:
                if (preg_match('/.*(dynamic sql error).*(table unknown).*/i', $message)) {
                    return new TableNotFoundException($exception, $query);
                }

                if (preg_match('/.*(dynamic sql error).*(ambiguous field name).*/i', $message)) {
                    return new NonUniqueFieldNameException($exception, $query);
                }

                break;
            case -206:
                if (preg_match('/.*(dynamic sql error).*(table unknown).*/i', $message)) {
                    return new TableNotFoundException($exception, $query);
                }

                if (preg_match('/.*(dynamic sql error).*(column unknown).*/i', $message)) {
                    return new InvalidFieldNameException($exception, $query);
                }

                break;
            case -501:
                $log = true;
            case -803:
                return new UniqueConstraintViolationException($exception, $query);

            case -303: // -303 arithmetic exception, numeric overflow, or string truncation string right truncation
                return new DriverException($exception, $query);

            case -530:
                return new ForeignKeyConstraintViolationException($exception, $query);

            case -607:
                if (preg_match('/.*(unsuccessful metadata update Table).*(already exists).*/i', $message)) {
                    return new TableExistsException($exception, $query);
                }

                if (preg_match('/.*(unsuccessful metadata update Create Table).*(already exists).*/i', $message)) {
                    return new TableExistsException($exception, $query);
                }

                if (
                    preg_match('/.*(unsuccessful metadata update DROP TABLE).*(does not exist).*/i', $message)
                    ||
                    preg_match('/.*(Invalid command Table).*(does not exist).*/i', $message)
                ) {
                    return new TableNotFoundException($exception, $query);
                }

                if (
                    preg_match('/.*(unsuccessful metadata update).*(is not defined).*/i', $message)
                    || preg_match('/.*(unsuccessful metadata update).*(not found).*/i', $message)
                ) {
                    return new DatabaseObjectNotFoundException($exception, $query);
                }

                break;
            case -804:
                if (preg_match('/.*(Data type unknown).*/i', $message)) {
                    return new DriverException($exception, $query);
                }
                return new NotNullConstraintViolationException($exception, $query);

            case -902:
                if (preg_match('/.*(no such file or directory).*/i', $message)) {
                    return new DatabaseDoesNotExist($exception, $query);
                }

                return new ConnectionException($exception, $query);
        }

        return new DriverException($exception, $query);
    }
}
