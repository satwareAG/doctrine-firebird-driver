<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

interface GetDropTableSqlDispatchEventListener
{
    public function onSchemaDropTable(): void;
}
