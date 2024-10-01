<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\ORM\Mapping;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;

use function strtoupper;

/** @psalm-suppress UnusedClass */
class FirebirdQuoteStrategy extends DefaultQuoteStrategy
{
    public function getColumnAlias(
        string $columnName,
        int $counter,
        AbstractPlatform $platform,
        ClassMetadata|null $class = null,
    ): string {
        return strtoupper(parent::getColumnAlias($columnName, $counter, $platform, $class));
    }
}
