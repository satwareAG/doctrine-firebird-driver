<?php

/**
 * PHPStan stubs for php-firebird v10.x new functions and constants.
 *
 * These supplement the external satwareag/php-firebird-stubs package.
 * All declarations are in the global namespace.
 *
 * New/changed in v9.0.0:
 * - fbird_create_database(): standalone DB creation
 * - fbird_prepare_ex(): fixed prepare signature
 * - FBIRD_EXCEPTION_MODE_COMPAT: alias for FBIRD_EXCEPTION_MODE_SILENT
 * - fbird_drop_db(): accepts string DSN
 * - fbird_execute(): supports BLOB stream resource as parameter value
 *
 * New/changed in v10.0.0:
 * - fbird_connect()/fbird_pconnect(): now return Firebird\Connection objects
 * - Legacy bridge fully removed (zero isc_db_handle code)
 * - PDO Batch DML: PDO::exec() accepts semicolon-separated multi-statement SQL
 *
 * Fixed in v10.1.0:
 * - Server RAM leak on FB 4.0+ restored via compile-time version gating
 *
 * Fixed in v10.3.5:
 * - Server-side prepared statement leak in fbird_query() SELECT (issue #135)
 *
 * @see https://github.com/satwareAG/php-firebird/releases/tag/v10.3.6
 */

declare(strict_types=1);

/**
 * Alias for FBIRD_EXCEPTION_MODE_SILENT.
 *
 * Compatibility constant that maps to SILENT mode (functions return false
 * on error instead of throwing).
 *
 * @since 9.0.0
 */
const FBIRD_EXCEPTION_MODE_COMPAT = 0;
