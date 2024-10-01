<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\Schema;

interface ListTableColumnsDispatchEventListener
{
    public function onSchemaColumnDefinition(): void;
}
