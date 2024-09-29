<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Doctrine\DBAL\Driver\AbstractException;

class Exception extends AbstractException
{
    public static function fromErrorInfo(string $message, int $code): Exception
    {
        return new self($message, null, $code);
    }
}
