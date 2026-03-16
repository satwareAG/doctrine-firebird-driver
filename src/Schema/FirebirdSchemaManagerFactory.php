<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Override;

/**
 * Creates a FirebirdSchemaManager for Firebird connections.
 *
 * Implements the DBAL 3.6+ SchemaManagerFactory interface, which is mandatory
 * in DBAL 4.x. Registering this factory via Configuration::setSchemaManagerFactory()
 * ensures that the Firebird-specific schema manager is always used, regardless
 * of the platform detection path.
 *
 * Usage:
 *   $config = new \Doctrine\DBAL\Configuration();
 *   $config->setSchemaManagerFactory(new FirebirdSchemaManagerFactory());
 *
 * Closes #70
 */
final class FirebirdSchemaManagerFactory implements SchemaManagerFactory
{
    /**
     * {@inheritDoc}
     *
     * @return AbstractSchemaManager<FirebirdPlatform>
     */
    #[Override]
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        /** @var FirebirdPlatform $platform */
        $platform = $connection->getDatabasePlatform();

        return new FirebirdSchemaManager($connection, $platform);
    }
}
