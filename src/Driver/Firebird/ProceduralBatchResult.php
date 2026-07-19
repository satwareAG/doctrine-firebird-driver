<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Driver\Firebird;

/**
 * Result of a procedural batch INSERT operation.
 *
 * Wraps the array returned by fbird_batch_execute() into an object
 * compatible with the BatchTest expectations.
 */
final class ProceduralBatchResult
{
    public readonly int $successCount;
    public readonly int $errorCount;
    public readonly int $totalProcessed;

    /** @param array{total_processed: int, success_count: int, error_count: int} $result */
    public function __construct(array $result)
    {
        $this->totalProcessed = $result['total_processed'] ?? 0;
        $this->successCount   = $result['success_count'] ?? 0;
        $this->errorCount     = $result['error_count'] ?? 0;
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    public function isComplete(): bool
    {
        return $this->errorCount === 0 && $this->successCount === $this->totalProcessed;
    }
}
