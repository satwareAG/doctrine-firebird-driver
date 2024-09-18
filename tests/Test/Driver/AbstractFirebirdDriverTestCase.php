<?php

namespace Satag\DoctrineFirebirdDriver\Test\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\PostgreSQL;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\Tests\Driver\AbstractDriverTestCase;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Schema\FirebirdSchemaManager;

/** @extends \Satag\DoctrineFirebirdDriver\Test\Driver\AbstractDriverTestCase<PostgreSQLPlatform> */
abstract class AbstractFirebirdDriverTestCase extends \Satag\DoctrineFirebirdDriver\Test\Driver\AbstractDriverTestCase
{
    protected function createPlatform(): AbstractPlatform
    {
        return new FirebirdPlatform();
    }

    protected function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return new FirebirdSchemaManager(
            $connection,
            $this->createPlatform(),
        );
    }

    protected function createExceptionConverter(): ExceptionConverter
    {
        return new \Satag\DoctrineFirebirdDriver\Driver\Firebird\ExceptionConverter();
    }
}
