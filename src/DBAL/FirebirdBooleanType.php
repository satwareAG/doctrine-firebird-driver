<?php

namespace Satag\DoctrineFirebirdDriver\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BooleanType;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

class FirebirdBooleanType extends BooleanType
{
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return get_class($platform) === FirebirdPlatform::class;
    }
}
