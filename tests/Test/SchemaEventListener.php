<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test;

/**
 * Interface for schema event listeners used in tests.
 */
interface SchemaEventListener
{
    public function onSchemaCreateTable(): void;

    public function onSchemaCreateTableColumn(): void;

    public function onSchemaDropTable(): void;

    public function onSchemaAlterTable(): void;

    public function onSchemaAlterTableAddColumn(): void;

    public function onSchemaAlterTableRemoveColumn(): void;

    public function onSchemaAlterTableChangeColumn(): void;

    public function onSchemaAlterTableRenameColumn(): void;
}
