<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Platforms;

interface GetAlterTableSqlDispatchEventListener
{
    public function onSchemaAlterTable(): void;

    public function onSchemaAlterTableAddColumn(): void;

    public function onSchemaAlterTableRemoveColumn(): void;

    public function onSchemaAlterTableChangeColumn(): void;

    public function onSchemaAlterTableRenameColumn(): void;
}
