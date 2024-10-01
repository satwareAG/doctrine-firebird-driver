<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

interface ListTableIndexesDispatchEventListener
{
    public function onSchemaIndexDefinition(): void;
}
