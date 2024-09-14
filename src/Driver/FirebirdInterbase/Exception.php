<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase;

use Doctrine\DBAL\Driver\AbstractException;

use function intval;
use function strval;

class Exception extends AbstractException
{
    /** @param array<string, string> $error */
    public static function fromErrorInfo(array $error): Exception
    {
        return new self(strval($error['message']), null, intval($error['code']));
    }
}
