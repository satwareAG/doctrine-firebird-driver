<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BooleanType;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Override;

class FirebirdBooleanType extends BooleanType
{
    #[Override]
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return $platform::class === FirebirdPlatform::class;
    }
}
