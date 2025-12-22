<?php

/**
 * Firebird Extension Stubs
 * Generated for php-firebird v7.0.0-rc.1+ official satwareAG/php-firebird extension
 *
 * php-firebird v7.0.0-rc.1 uses FBIRD_* constants exclusively (no IBASE_* aliases).
 * The fbird_* functions are the primary API.
 *
 * @see https://github.com/satwareAG/php-firebird
 */

// Core constants
define('FBIRD_DEFAULT', 0);
define('FBIRD_CREATE', 0);
define('FBIRD_TEXT', 1);
define('FBIRD_FETCH_BLOBS', 1);
define('FBIRD_FETCH_ARRAYS', 2);
define('FBIRD_UNIXTIME', 4);
define('FBIRD_FETCH_DATE_OBJ', 8);
define('FBIRD_VER', 10);

// Transaction access modes
define('FBIRD_WRITE', 1);
define('FBIRD_READ', 2);

// Transaction isolation levels
define('FBIRD_COMMITTED', 8);
define('FBIRD_CONSISTENCY', 16);
define('FBIRD_CONCURRENCY', 4);

// Record version handling
define('FBIRD_REC_VERSION', 64);
define('FBIRD_REC_NO_VERSION', 32);

// Lock resolution
define('FBIRD_NOWAIT', 256);
define('FBIRD_WAIT', 128);
define('FBIRD_LOCK_TIMEOUT', 512);

// Lock modes
define('FBIRD_LOCK_SHARED', 1024);
define('FBIRD_LOCK_PROTECTED', 2048);
define('FBIRD_LOCK_EXCLUSIVE', 4096);
define('FBIRD_LOCK_READ', 8192);
define('FBIRD_LOCK_WRITE', 16384);

// Read consistency (Firebird 4+)
define('FBIRD_READ_CONSISTENCY', 32768);

// Event handling
define('FBIRD_EVENT_TIMEOUT', -2);

// Connection options
define('FBIRD_CONNECT_FORCE_NEW', 2);

// Blob seek modes
define('FBIRD_BLOB_SEEK_SET', 0);
define('FBIRD_BLOB_SEEK_CUR', 1);
define('FBIRD_BLOB_SEEK_END', 2);

// Backup options
define('FBIRD_BKP_IGNORE_CHECKSUMS', 1);
define('FBIRD_BKP_IGNORE_LIMBO', 2);
define('FBIRD_BKP_METADATA_ONLY', 4);
define('FBIRD_BKP_NO_GARBAGE_COLLECT', 8);
define('FBIRD_BKP_OLD_DESCRIPTIONS', 16);
define('FBIRD_BKP_NON_TRANSPORTABLE', 32);
define('FBIRD_BKP_CONVERT', 64);

// Restore options
define('FBIRD_RES_DEACTIVATE_IDX', 256);
define('FBIRD_RES_NO_SHADOW', 512);
define('FBIRD_RES_NO_VALIDITY', 1024);
define('FBIRD_RES_ONE_AT_A_TIME', 2048);
define('FBIRD_RES_REPLACE', 4096);
define('FBIRD_RES_CREATE', 8192);
define('FBIRD_RES_USE_ALL_SPACE', 16384);

// Database property options
define('FBIRD_PRP_PAGE_BUFFERS', 5);
define('FBIRD_PRP_SWEEP_INTERVAL', 6);
define('FBIRD_PRP_SHUTDOWN_DB', 7);
define('FBIRD_PRP_DENY_NEW_TRANSACTIONS', 10);
define('FBIRD_PRP_DENY_NEW_ATTACHMENTS', 9);
define('FBIRD_PRP_RESERVE_SPACE', 11);
define('FBIRD_PRP_RES_USE_FULL', 35);
define('FBIRD_PRP_RES', 36);
define('FBIRD_PRP_WRITE_MODE', 12);
define('FBIRD_PRP_WM_ASYNC', 37);
define('FBIRD_PRP_WM_SYNC', 38);
define('FBIRD_PRP_ACCESS_MODE', 13);
define('FBIRD_PRP_AM_READONLY', 39);
define('FBIRD_PRP_AM_READWRITE', 40);
define('FBIRD_PRP_SET_SQL_DIALECT', 14);
define('FBIRD_PRP_ACTIVATE', 256);
define('FBIRD_PRP_DB_ONLINE', 512);

// Repair options
define('FBIRD_RPR_CHECK_DB', 16);
define('FBIRD_RPR_IGNORE_CHECKSUM', 32);
define('FBIRD_RPR_KILL_SHADOWS', 64);
define('FBIRD_RPR_MEND_DB', 4);
define('FBIRD_RPR_VALIDATE_DB', 1);
define('FBIRD_RPR_FULL', 128);
define('FBIRD_RPR_SWEEP_DB', 2);

// Statistics options
define('FBIRD_STS_DATA_PAGES', 1);
define('FBIRD_STS_DB_LOG', 2);
define('FBIRD_STS_HDR_PAGES', 4);
define('FBIRD_STS_IDX_PAGES', 8);
define('FBIRD_STS_SYS_RELATIONS', 16);

// Service manager info
define('FBIRD_SVC_SERVER_VERSION', 55);
define('FBIRD_SVC_IMPLEMENTATION', 56);
define('FBIRD_SVC_GET_ENV', 59);
define('FBIRD_SVC_GET_ENV_LOCK', 60);
define('FBIRD_SVC_GET_ENV_MSG', 61);
define('FBIRD_SVC_USER_DBPATH', 58);
define('FBIRD_SVC_SVR_DB_INFO', 50);
define('FBIRD_SVC_GET_USERS', 68);

/**
 * Connect to a Firebird database
 * @param string|null $database
 * @param string|null $username
 * @param string|null $password
 * @param string|null $charset
 * @param int|null $buffers
 * @param int|null $dialect
 * @param string|null $role
 * @return resource|false
 */
function fbird_connect($database = null, $username = null, $password = null, $charset = null, $buffers = null, $dialect = null, $role = null) {}

/**
 * Open a persistent connection to a Firebird database
* @param string|null $database
 * @param string|null $username
 * @param string|null $password
 * @param string|null $charset
 * @param int|null $buffers
 * @param int|null $dialect
 * @param string|null $role
 * @return resource|false
 */
function fbird_pconnect($database = null, $username = null, $password = null, $charset = null, $buffers = null, $dialect = null, $role = null) {}

/**
 * Close a connection to a Firebird database
 * @param resource|null $link_identifier
 * @return bool
 */
function fbird_close($link_identifier = null) {}

/**
 * Drop a database
 * @param resource|null $link_identifier
 * @return bool
 */
function fbird_drop_db($link_identifier = null) {}

/**
 * Execute a query on a Firebird database
 * @param resource|null $link_identifier
 * @param string|null $query
 * @param int|null $bind_arg
 * @return resource|bool
 */
function fbird_query($link_identifier = null, $query = null, $bind_arg = null) {}

/**
 * Fetch a row from an InterBase result identifier
 * @param resource $result
 * @param int|null $fetch_flags
 * @return array|false
 */
function fbird_fetch_row($result, $fetch_flags = null) {}

/**
 * Fetch a result row as an associative array
 * @param resource $result
 * @param int|null $fetch_flags
 * @return array|false
 */
function fbird_fetch_assoc($result, $fetch_flags = null) {}

/**
 * Fetch an object from an InterBase database
 * @param resource $result
 * @param int|null $fetch_flags
 * @return object|false
 */
function fbird_fetch_object($result, $fetch_flags = null) {}

/**
 * Free a result set
 * @param resource $result
 * @return bool
 */
function fbird_free_result($result) {}

/**
 * Assigns a name to a result set
 * @param resource $result
 * @param string $name
 * @return bool
 */
function fbird_name_result($result, $name) {}

/**
 * Prepare a query for later execution
 * @param resource|null $link_identifier
 * @param string|resource|null $query_or_trans
 * @param string|null $query
 * @return resource|false
 */
function fbird_prepare($link_identifier = null, $query_or_trans = null, $query = null) {}

/**
 * Execute a prepared query
 * @param resource $query
 * @param mixed ...$bind_arg
 * @return resource|bool
 */
function fbird_execute($query, ...$bind_arg) {}

/**
 * Free memory allocated by a prepared query
 * @param resource $query
 * @return bool
 */
function fbird_free_query($query) {}

/**
 * Execute a SQL statement with an explicit transaction
 *
 * Executes a DML statement (INSERT, UPDATE, DELETE) within the given transaction
 * and returns the number of affected rows. The transaction must be committed
 * separately for changes to persist.
 *
 * @param resource $trans_handle Transaction resource from fbird_trans() or fbird_trans_start()
 * @param string $query SQL statement to execute
 * @param array|null $params Optional array of bind parameters
 * @return int Number of affected rows
 * @since php-firebird 6.2.0
 */
function fbird_execute_statement($trans_handle, string $query, ?array $params = null): int {}

/**
 * Execute a SQL query with an explicit transaction
 *
 * Executes a SELECT query within the given transaction and returns a result
 * resource for fetching rows. The transaction must be managed separately.
 *
 * @param resource $trans_handle Transaction resource from fbird_trans() or fbird_trans_start()
 * @param string $query SQL query to execute
 * @param array|null $params Optional array of bind parameters
 * @return resource|false Result resource on success, false on failure
 * @since php-firebird 6.2.0
 */
function fbird_execute_query($trans_handle, string $query, ?array $params = null) {}

/**
 * Execute SQL in an autonomous transaction (auto-commit)
 *
 * Executes a SQL statement in a separate autonomous transaction that is
 * automatically committed on success or rolled back on failure. This is
 * useful for DDL statements or operations that should not be affected
 * by the current transaction state.
 *
 * @param resource $link_identifier Connection resource from fbird_connect() or fbird_pconnect()
 * @param string $query SQL statement to execute
 * @param array|null $params Optional array of bind parameters
 * @return int|resource|false Affected rows for DML, result resource for SELECT, false on failure
 * @since php-firebird 6.2.0
 */
function fbird_execute_auto($link_identifier, string $query, ?array $params = null): int|false {}

/**
 * Increments the named generator and returns its new value
 * @param string $generator
 * @param int|null $increment
 * @param resource|null $link_identifier
 * @return mixed
 */
function fbird_gen_id($generator, $increment = null, $link_identifier = null) {}

/**
 * Get the number of fields in a result set
 * @param resource $query_result
 * @return int
 */
function fbird_num_fields($query_result) {}

/**
 * Return the number of parameters in a prepared query
 * @param resource $query
 * @return int
 */
function fbird_num_params($query) {}

/**
 * Return the number of rows that were affected by the previous query
 * @param resource|null $link_identifier
 * @return int
 */
function fbird_affected_rows($link_identifier = null) {}

/**
 * Get information about a field
 * @param resource $query_result
 * @param int $field_number
 * @return array
 */
function fbird_field_info($query_result, $field_number) {}

/**
 * Return information about a parameter in a prepared query
 * @param resource $query
 * @param int $field_number
 * @return array
 */
function fbird_param_info($query, $field_number) {}

/**
 * Start a transaction
 * @param resource|null $link_identifier
 * @param array|null $options
 * @return resource|bool
 */
function fbird_trans_start($link_identifier, ?array $options = null) {}

/**
 * Create a savepoint in a transaction
 * @param resource $trans_handle
 * @param string $name
 * @return bool
 */
function fbird_savepoint($trans_handle, $name) {}

/**
 * Rollback to a savepoint in a transaction
 * @param resource $trans_handle
 * @param string $name
 * @return bool
 */
function fbird_rollback_savepoint($trans_handle, $name) {}

/**
 * Release a savepoint in a transaction
 * @param resource $trans_handle
 * @param string $name
 * @return bool
 */
function fbird_release_savepoint($trans_handle, $name) {}

/**
 * Return information about a transaction
 * @param resource $trans_handle
 * @return array
 */
function fbird_trans_info($trans_handle) {}

/**
 * Begin a transaction
 * @param int|null $trans_args
 * @param resource|null $link_identifier
 * @return resource|bool
 */
function fbird_trans($trans_args = null, $link_identifier = null) {}

/**
 * Commit a transaction
 * @param resource|null $link_identifier
 * @return bool
 */
function fbird_commit($link_identifier = null) {}

/**
 * Roll back a transaction
 * @param resource|null $link_identifier
 * @return bool
 */
function fbird_rollback($link_identifier = null) {}

/**
 * Commit a transaction without closing it
 * @param resource|null $link_identifier
 * @return bool
 */
function fbird_commit_ret($link_identifier = null) {}

/**
 * Roll back a transaction without closing it
 * @param resource|null $link_identifier
 * @return bool
 */
function fbird_rollback_ret($link_identifier = null) {}

/**
 * Return blob length and other useful info
 * @param resource|null $link_identifier
 * @param string|null $blob_id
 * @return array
 */
function fbird_blob_info($link_identifier = null, $blob_id = null) {}

/**
 * Create a blob for adding data
 * @param resource|null $link_identifier
 * @return resource|false
 */
function fbird_blob_create($link_identifier = null) {}

/**
 * Add data into a created blob
 * @param resource $blob_handle
 * @param string $data
 * @return bool
 */
function fbird_blob_add($blob_handle, $data) {}

/**
 * Cancel creating blob
 * @param resource $blob_handle
 * @return bool
 */
function fbird_blob_cancel($blob_handle) {}

/**
 * Close blob
 * @param resource $blob_handle
 * @return mixed
 */
function fbird_blob_close($blob_handle) {}

/**
 * Open blob for retrieving data parts
 * @param resource|null $link_identifier
 * @param string|null $blob_id
 * @return resource|false
 */
function fbird_blob_open($link_identifier = null, $blob_id = null) {}

/**
 * Get len bytes data from open blob
 * @param resource $blob_handle
 * @param int $len
 * @return string|false
 */
function fbird_blob_get($blob_handle, $len) {}

/**
 * Output blob contents to browser
 * @param resource|null $link_identifier
 * @param string|null $blob_id
 * @return bool
 */
function fbird_blob_echo($link_identifier = null, $blob_id = null) {}

/**
 * Create blob, copy file in it, and close it
 * @param resource|null $link_identifier
 * @param resource|null $file
 * @return string|false
 */
function fbird_blob_import($link_identifier = null, $file = null) {}

/**
 * Create a blob as a PHP stream for writing
 *
 * Creates a new blob and returns it as a PHP stream resource. Data can be
 * written to the blob using standard PHP stream functions like fwrite().
 * The stream must be closed with fclose() to finalize the blob.
 *
 * @param resource|null $link_identifier Connection resource from fbird_connect() or fbird_pconnect()
 * @return resource|false PHP stream resource on success, false on failure
 * @since php-firebird 6.2.0
 */
function fbird_blob_create_stream($link_identifier = null) {}

/**
 * Open an existing blob as a PHP stream for reading
 *
 * Opens an existing blob by its ID and returns it as a PHP stream resource.
 * Data can be read using standard PHP stream functions like fread() and fgets().
 * The stream must be closed with fclose() when done.
 *
 * @param resource|null $link_identifier Connection resource from fbird_connect() or fbird_pconnect()
 * @param string|null $blob_id The blob ID to open (from a BLOB column)
 * @return resource|false PHP stream resource on success, false on failure
 * @since php-firebird 6.2.0
 */
function fbird_blob_open_stream($link_identifier = null, $blob_id = null) {}

/**
 * List attachments that are blocking access to a table
 *
 * Queries the MON$TABLE_BLOCKERS system table to find all active attachments
 * that hold locks on the specified table. Useful for migration and maintenance
 * scenarios where you need to identify connections blocking DDL operations.
 *
 * @param resource $link_identifier Connection resource from fbird_connect() or fbird_pconnect()
 * @param string $table_name Name of the table to check for blockers
 * @return array|false Array of blocker info (MON$ATTACHMENT_ID, MON$USER, etc.) or false on error
 * @since php-firebird 6.2.0
 */
function fbird_list_table_blockers($link_identifier, string $table_name) {}

/**
 * Kill a specific database attachment
 *
 * Terminates another database connection by attachment ID. Requires SYSDBA
 * privileges or owner rights. Use with caution as this immediately disconnects
 * the target session without warning.
 *
 * @param resource $link_identifier Connection resource from fbird_connect() or fbird_pconnect()
 * @param int $attachment_id The MON$ATTACHMENT_ID of the attachment to kill
 * @return bool True on success, false on failure
 * @since php-firebird 6.2.0
 */
function fbird_kill_attachment($link_identifier, int $attachment_id): bool {}

/**
 * Force drop a table by killing blocking attachments first
 *
 * Combines fbird_list_table_blockers() and fbird_kill_attachment() to forcefully
 * drop a table that may have active connections. All blocking attachments are
 * terminated before the DROP TABLE is executed. Requires SYSDBA privileges.
 *
 * WARNING: This is a destructive operation that will terminate other sessions
 * and permanently delete the table and its data.
 *
 * @param resource $link_identifier Connection resource from fbird_connect() or fbird_pconnect()
 * @param string $table_name Name of the table to drop
 * @return bool True on success, false on failure
 * @since php-firebird 6.2.0
 */
function fbird_drop_table_force($link_identifier, string $table_name): bool {}

/**
 * Return error messages
 * @return string|false
 */
function fbird_errmsg() {}

/**
 * Return error code
 * @return int|false
 */
function fbird_errcode() {}

/**
 * Add a user to a security database
 * @param resource $service_handle
 * @param string $user_name
 * @param string $password
 * @param string|null $first_name
 * @param string|null $middle_name
 * @param string|null $last_name
 * @return bool
 */
function fbird_add_user($service_handle, $user_name, $password, $first_name = null, $middle_name = null, $last_name = null) {}

/**
 * Modify a user to a security database
 * @param resource $service_handle
 * @param string $user_name
 * @param string $password
 * @param string|null $first_name
 * @param string|null $middle_name
 * @param string|null $last_name
 * @return bool
 */
function fbird_modify_user($service_handle, $user_name, $password, $first_name = null, $middle_name = null, $last_name = null) {}

/**
 * Delete a user from a security database
 * @param resource $service_handle
 * @param string $user_name
 * @param string $password
 * @param string|null $first_name
 * @param string|null $middle_name
 * @param string|null $last_name
 * @return bool
 */
function fbird_delete_user($service_handle, $user_name, $password, $first_name = null, $middle_name = null, $last_name = null) {}

/**
 * Connect to the service manager
 * @param string|null $host
 * @param string|null $dba_username
 * @param string|null $dba_password
 * @return resource|false
 */
function fbird_service_attach($host = null, $dba_username = null, $dba_password = null) {}

/**
 * Disconnect from the service manager
 * @param resource $service_handle
 * @return bool
 */
function fbird_service_detach($service_handle) {}

/**
 * Initiates a backup task in the service manager and returns immediately
 * @param resource $service_handle
 * @param string $source_db
 * @param string $dest_file
 * @param int|null $options
 * @param bool|null $verbose
 * @return mixed
 */
function fbird_backup($service_handle, $source_db, $dest_file, $options = null, $verbose = null) {}

/**
 * Initiates a restore task in the service manager and returns immediately
 * @param resource $service_handle
 * @param string $source_file
 * @param string $dest_db
 * @param int|null $options
 * @param bool|null $verbose
 * @return mixed
 */
function fbird_restore($service_handle, $source_file, $dest_db, $options = null, $verbose = null) {}

/**
 * Execute a maintenance command on the database server
 * @param resource $service_handle
 * @param string $db
 * @param int $action
 * @param int|null $argument
 * @return bool
 */
function fbird_maintain_db($service_handle, $db, $action, $argument = null) {}

/**
 * Request statistics about a database
 * @param resource $service_handle
 * @param string $db
 * @param int $action
 * @param int|null $argument
 * @return string
 */
function fbird_db_info($service_handle, $db, $action, $argument = null) {}

/**
 * Request statistics about a database server
 * @param resource $service_handle
 * @param int $action
 * @return string
 */
function fbird_server_info($service_handle, $action) {}

/**
 * Wait for an event to be posted by the database
 * @param resource $link_identifier
 * @param string|null $event
 * @param string|null $event2
 * @return string
 */
function fbird_wait_event($link_identifier, $event = null, $event2 = null) {}

/**
 * Register a callback function to be called when events are posted
 * @param resource $link_identifier
 * @param callable $handler
 * @param string|null $event
 * @param string|null $event2
 * @return resource
 */
function fbird_set_event_handler($link_identifier, $handler, $event = null, $event2 = null) {}

/**
 * Cancels a registered event handler
 * @param resource $event
 * @return bool
 */
function fbird_free_event_handler($event) {}

/**
 * @return string
 */
function fbird_get_client_version() {}

/**
 * @return int
 */
function fbird_get_client_major_version() {}

/**
 * @return int
 */
function fbird_get_client_minor_version() {}

// Note: Legacy ibase_* alias functions removed in php-firebird v7.0.0-rc.1
// Use fbird_* functions instead

/**
 * Execute a query in a specific transaction context
 *
 * UNIQUE TO php-firebird: Executes a SQL query within a specific transaction context.
 * This allows multiple concurrent transactions on a single connection with explicit
 * control over which transaction each query uses.
 *
 * @param resource $link_identifier Connection resource from fbird_connect() or fbird_pconnect()
 * @param resource $trans_handle Transaction resource from fbird_trans() or fbird_trans_start()
 * @param string $query SQL query to execute
 * @param array $params Optional array of bind parameters
 * @return resource|int|false Result resource for SELECT, affected rows for DML, false on failure
 * @since php-firebird 7.0.0
 */
function fbird_query_params_tx($link_identifier, $trans_handle, string $query, array $params = []) {}

/**
 * Execute a query with bind parameters
 *
 * @param resource $link_identifier Connection resource
 * @param string $query SQL query to execute
 * @param array $params Optional array of bind parameters
 * @return resource|int|false Result resource for SELECT, affected rows for DML, false on failure
 * @since php-firebird 7.0.0
 */
function fbird_query_params($link_identifier, string $query, array $params = []) {}

/**
 * Execute a prepared statement with bind parameters
 *
 * @param resource $statement Prepared statement resource
 * @param array $params Array of bind parameters
 * @return resource|int|false Result resource or affected rows
 * @since php-firebird 7.0.0
 */
function fbird_execute_params($statement, array $params = []) {}

/**
 * Begin a transaction with options
 *
 * @param resource $link_identifier Connection resource
 * @param int $trans_args Transaction arguments/flags
 * @return resource|false Transaction resource or false on failure
 * @since php-firebird 7.0.0
 */
function fbird_trans_begin($link_identifier, int $trans_args = FBIRD_DEFAULT) {}
