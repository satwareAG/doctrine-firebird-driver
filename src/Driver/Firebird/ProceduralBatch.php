<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

use Firebird\Connection;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Exception as DriverException;
use Throwable;

use function fbird_batch_add;
use function fbird_batch_add_blob;
use function fbird_batch_cancel;
use function fbird_batch_create;
use function fbird_batch_execute;
use function fbird_prepare_ex;

/**
 * Wrapper around the procedural fbird_batch_* API.
 *
 * The OO Firebird\Batch class has a private constructor and Batch::fromQuery()
 * fails with "invalid batch handle" in php-firebird v10.3.9. This wrapper
 * uses the working procedural API (fbird_batch_create, fbird_batch_add, etc.)
 * as a reliable alternative.
 */
final class ProceduralBatch
{
    /** @var resource Batch handle */
    private mixed $batchHandle;

    private int $rowCount = 0;

    /**
     * @param resource|Connection $connection    Native connection resource or object
     * @param string              $sql           INSERT statement with placeholders
     * @param resource            $transResource Transaction resource
     *
     * @throws DriverException
     */
    public function __construct(mixed $connection, string $sql, mixed $transResource)
    {
        try {
            /** @phpstan-ignore arguments.count */
            $stmt = fbird_prepare_ex($connection, $sql, $transResource);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }

        if ($stmt === false) {
            throw new DriverException('Failed to prepare statement for batch operation.');
        }

        try {
            $batch = fbird_batch_create($stmt, $transResource);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }

        if ($batch === false) {
            throw new DriverException('Failed to create batch handle.');
        }

        $this->batchHandle = $batch;
    }

    /**
     * Add a row of parameters to the batch.
     *
     * @throws DriverException
     */
    public function add(mixed ...$args): bool
    {
        try {
            $result = fbird_batch_add($this->batchHandle, ...$args);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }

        if ($result) {
            $this->rowCount++;
        }

        return $result;
    }

    /**
     * Add a BLOB to the batch and return its ID for use in add().
     *
     * @throws DriverException
     */
    public function addBlob(string $data, int $type = 0): string
    {
        try {
            $blobId = fbird_batch_add_blob($this->batchHandle, $data, $type);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }

        if ($blobId === false) {
            throw new DriverException('Failed to add BLOB to batch.');
        }

        return $blobId;
    }

    /**
     * Execute the batch and return results.
     *
     * @throws DriverException
     */
    public function execute(): ProceduralBatchResult
    {
        try {
            $result = fbird_batch_execute($this->batchHandle);
        } catch (Throwable $e) {
            throw DriverException::fromThrowable($e);
        }

        if ($result === false) {
            throw new DriverException('Batch execution failed.');
        }

        return new ProceduralBatchResult($result);
    }

    /**
     * Cancel the batch without executing.
     */
    public function cancel(): bool
    {
        try {
            return fbird_batch_cancel($this->batchHandle);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get the number of rows added so far.
     */
    public function count(): int
    {
        return $this->rowCount;
    }
}
