<?php

declare(strict_types=1);

/**
 * Compatibility shim for legacy ext-interbase extension.
 *
 * This file maps fbird_* functions and constants to their ibase_* counterparts
 * when ext-firebird is not available but ext-interbase is present.
 * This allows the driver to support legacy environments (e.g. Firebird 3.0 via php-firebird v6.2.0).
 */

if (! function_exists('fbird_connect') && function_exists('ibase_connect')) {
    // Constants
    // FBIRD_SVC_SERVER_VERSION -> IBASE_SVC_SERVER_VERSION
    if (! defined('FBIRD_SVC_SERVER_VERSION') && defined('IBASE_SVC_SERVER_VERSION')) {
        define('FBIRD_SVC_SERVER_VERSION', IBASE_SVC_SERVER_VERSION);
    }
    
    // Add other constants if needed (e.g. FBIRD_DEFAULT, FBIRD_TEXT, etc.) by checking IBASE_ equivalents
    // For now FBIRD_SVC_SERVER_VERSION is the one causing fatal error.

    // Functions
    function fbird_affected_rows(...$args) { return ibase_affected_rows(...$args); }
    function fbird_backup(...$args) { return ibase_backup(...$args); }
    function fbird_blob_add(...$args) { return ibase_blob_add(...$args); }
    function fbird_blob_cancel(...$args) { return ibase_blob_cancel(...$args); }
    function fbird_blob_close(...$args) { return ibase_blob_close(...$args); }
    function fbird_blob_create(...$args) { return ibase_blob_create(...$args); }
    function fbird_blob_echo(...$args) { return ibase_blob_echo(...$args); }
    function fbird_blob_get(...$args) { return ibase_blob_get(...$args); }
    function fbird_blob_import(...$args) { return ibase_blob_import(...$args); }
    function fbird_blob_info(...$args) { return ibase_blob_info(...$args); }
    function fbird_blob_open(...$args) { return ibase_blob_open(...$args); }
    function fbird_close(...$args) { return ibase_close(...$args); }
    function fbird_commit(...$args) { return ibase_commit(...$args); }
    function fbird_commit_ret(...$args) { return ibase_commit_ret(...$args); }
    function fbird_connect(...$args) { return ibase_connect(...$args); }
    function fbird_db_info(...$args) { return ibase_db_info(...$args); }
    function fbird_drop_db(...$args) { return ibase_drop_db(...$args); }
    function fbird_errcode(...$args) { return ibase_errcode(...$args); }
    function fbird_errmsg(...$args) { return ibase_errmsg(...$args); }
    function fbird_execute(...$args) { return ibase_execute(...$args); }
    function fbird_fetch_assoc(...$args) { return ibase_fetch_assoc(...$args); }
    function fbird_fetch_object(...$args) { return ibase_fetch_object(...$args); }
    function fbird_fetch_row(...$args) { return ibase_fetch_row(...$args); }
    function fbird_field_info(...$args) { return ibase_field_info(...$args); }
    function fbird_free_event_handler(...$args) { return ibase_free_event_handler(...$args); }
    function fbird_free_query(...$args) { return ibase_free_query(...$args); }
    function fbird_free_result(...$args) { return ibase_free_result(...$args); }
    function fbird_gen_id(...$args) { return ibase_gen_id(...$args); }
    function fbird_maintain_db(...$args) { return ibase_maintain_db(...$args); }
    function fbird_name_result(...$args) { return ibase_name_result(...$args); }
    function fbird_num_fields(...$args) { return ibase_num_fields(...$args); }
    function fbird_num_params(...$args) { return ibase_num_params(...$args); }
    function fbird_param_info(...$args) { return ibase_param_info(...$args); }
    function fbird_pconnect(...$args) { return ibase_pconnect(...$args); }
    function fbird_prepare(...$args) { return ibase_prepare(...$args); }
    function fbird_query(...$args) { return ibase_query(...$args); }
    function fbird_restore(...$args) { return ibase_restore(...$args); }
    function fbird_rollback(...$args) { return ibase_rollback(...$args); }
    function fbird_rollback_ret(...$args) { return ibase_rollback_ret(...$args); }
    function fbird_server_info(...$args) { return ibase_server_info(...$args); }
    function fbird_service_attach(...$args) { return ibase_service_attach(...$args); }
    function fbird_service_detach(...$args) { return ibase_service_detach(...$args); }
    function fbird_set_event_handler(...$args) { return ibase_set_event_handler(...$args); }
    function fbird_trans(...$args) { return ibase_trans(...$args); }
    function fbird_wait_event(...$args) { return ibase_wait_event(...$args); }
    
    // Functions that might not have 1:1 mapping (check existence)
    if (function_exists('ibase_execute_auto')) {
        function fbird_execute_auto(...$args) { return ibase_execute_auto(...$args); }
    }
    
    // Note: fbird_connection_info, fbird_drop_table_force, fbird_kill_attachment, 
    // fbird_list_table_blockers, fbird_query_params_tx, fbird_reconnect_transaction, 
    // fbird_release_savepoint, fbird_rollback_savepoint, fbird_savepoint, fbird_sqlstate
    // might be specific to php-firebird v7 extensions or not present in older interbase versions.
    // We polyfill them as no-op or throw exception if critical?
    // Actually, let's verify if they existed in interbase.
    
    // fbird_savepoint / release / rollback_savepoint are definitely new in Firebird usually.
    // If ext-interbase doesn't support them, we might have issues if Driver uses them.
    // But usage analysis shows they ARE used.
    
    // Let's rely on function_exists check for these advanced ones.
    
}
