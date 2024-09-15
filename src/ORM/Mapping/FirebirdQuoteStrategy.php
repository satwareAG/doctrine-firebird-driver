<?php

namespace Satag\DoctrineFirebirdDriver\ORM\Mapping;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;

class FirebirdQuoteStrategy extends DefaultQuoteStrategy
{

    /**
     * @inheritDoc
     */
    public function getColumnAlias(
        string $columnName,
        int $counter,
        AbstractPlatform $platform,
        ?ClassMetadata $class = null,
    ): string {
        return \strtoupper( parent::getColumnAlias($columnName, $counter, $platform, $class));
    }
}
