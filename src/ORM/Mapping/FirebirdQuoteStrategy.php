<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\ORM\Mapping;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Satag\DoctrineFirebirdDriver\Compat\Override;

use function strtoupper;

/** @psalm-suppress UnusedClass */
final class FirebirdQuoteStrategy extends DefaultQuoteStrategy
{
    #[Override]
    public function getColumnAlias(
        string $columnName,
        int $counter,
        AbstractPlatform $platform,
        ClassMetadata|null $class = null,
    ): string {
        return strtoupper(parent::getColumnAlias($columnName, $counter, $platform, $class));
    }
}
