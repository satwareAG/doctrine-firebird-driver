<?php

declare(strict_types=1);

namespace Kafoso\DoctrineFirebirdDriver\Driver\FirebirdInterbase\Exception;


use Doctrine\DBAL\Driver\AbstractException;


/**
 * @internal
 *
 * @psalm-immutable
 */
final class ConnectionFailed extends AbstractException
{
    public static function new(): self
    {
        $error = oci_error();
        assert($error !== false);

        return new self($error['message'], null, $error['code']);
    }
}
