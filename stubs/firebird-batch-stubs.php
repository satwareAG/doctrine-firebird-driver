<?php

/**
 * Stub definitions for fbird_batch_* functions (Firebird 4.0+ Batch API).
 *
 * These functions are only available when ext-firebird is compiled with
 * FB_API_VER=40 AND linked against Firebird 4.0+ client libraries at runtime.
 * On CI quality-checks (which uses FB3 client libs), the extension cannot
 * register these functions. This stub ensures PHPStan sees them.
 */

function fbird_batch_create(mixed $query, mixed $trans_identifier = null): mixed {}
function fbird_batch_add(mixed $batch, mixed ...$args): bool {}
function fbird_batch_add_blob(mixed $batch, string $data, int $type = 0): string|false {}
function fbird_batch_register_blob(mixed $batch, string $blob_id): string|false {}
function fbird_batch_execute(mixed $batch): array|false {}
function fbird_batch_cancel(mixed $batch): bool {}
function fbird_batch_get_blob_alignment(mixed $batch): int|false {}
function fbird_batch_append_blob_data(mixed $batch, string $data): bool {}
function fbird_batch_add_blob_stream(mixed $batch, string $data): bool {}
function fbird_batch_set_default_bpb(mixed $batch, string $bpb): bool {}
