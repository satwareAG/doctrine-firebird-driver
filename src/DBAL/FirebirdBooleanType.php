<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BooleanType;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;

class FirebirdBooleanType extends BooleanType
{
    /** @inheritDoc */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return $platform::class === FirebirdPlatform::class;
    }
}
