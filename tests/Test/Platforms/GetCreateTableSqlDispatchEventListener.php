<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

interface GetCreateTableSqlDispatchEventListener
{
    public function onSchemaCreateTable(): void;

    public function onSchemaCreateTableColumn(): void;
}
