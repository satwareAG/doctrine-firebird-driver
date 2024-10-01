<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

interface CreateTableDispatchEventListener
{
    public function onSchemaCreateTable(): void;
}
