<?php

/**
 * PHP Firebird Extension Stubs
 *
 * This file provides stub declarations for static analysis tools (PHPStan, Psalm)
 * and IDEs (PhpStorm, VSCode Intelephense) for code completion and type checking.
 *
 * The actual functions are provided by the php-firebird C extension.
 * This stub file does not contain any implementation.
 *
 * @package   php-firebird-stubs
 * @version   10.3.6
 * @author    satware AG <info@satware.com>
 * @copyright 2025-2026 satware AG
 * @license   PHP-3.01 https://www.php.net/license/3_01.txt
 * @link      https://github.com/satwareAG/php-firebird
 *
 * @since 7.0.0
 */

declare(strict_types=1);

// Skip if the extension is already loaded (runtime)
if (extension_loaded('fbird')) {
    return;
}

// ============================================================================
// GENERAL CONSTANTS
// ============================================================================

/** @var int Default/no special flags */
const FBIRD_DEFAULT = 0;

/** @var int Create database flag (used in fbird_query) */
const FBIRD_CREATE = 0;

/** @var int Force new connection (bypass connection reuse) */
const FBIRD_CONNECT_FORCE_NEW = 2;

/** @var int Extension version (e.g. 100 for 10.x) */
const FBIRD_VER = 100;

// ============================================================================
// EXCEPTION MODE CONSTANTS
// ============================================================================

/** @var int Exception mode: suppress errors (default) */
const FBIRD_EXCEPTION_MODE_SILENT = 0;

/** @var int Exception mode: throw exceptions on errors */
const FBIRD_EXCEPTION_MODE_THROW = 1;

// ============================================================================
// FETCH FLAGS
// ============================================================================

/** @var int Fetch BLOBs as strings instead of blob IDs */
const FBIRD_TEXT = 1;

/** @var int Fetch BLOBs as strings (alias for FBIRD_TEXT) */
const FBIRD_FETCH_BLOBS = 1;

/** @var int Fetch arrays as PHP arrays */
const FBIRD_FETCH_ARRAYS = 2;

/** @var int Return timestamps as Unix timestamps */
const FBIRD_UNIXTIME = 4;

/** @var int Return date/time columns as DateTimeImmutable objects */
const FBIRD_FETCH_DATE_OBJ = 8;

// ============================================================================
// TRANSACTION ACCESS MODES
// ============================================================================

/** @var int Read-write transaction (default) */
const FBIRD_WRITE = 1;

/** @var int Read-only transaction */
const FBIRD_READ = 2;

// ============================================================================
// TRANSACTION ISOLATION LEVELS
// ============================================================================

/** @var int SNAPSHOT isolation (CONCURRENCY) */
const FBIRD_CONCURRENCY = 4;

/** @var int READ COMMITTED isolation */
const FBIRD_COMMITTED = 8;

/** @var int SNAPSHOT TABLE STABILITY isolation (CONSISTENCY) */
const FBIRD_CONSISTENCY = 16;

/** @var int Read committed: use record version (default for COMMITTED) */
const FBIRD_REC_VERSION = 64;

/** @var int Read committed: no record version */
const FBIRD_REC_NO_VERSION = 32;

/** @var int Read committed with READ CONSISTENCY (Firebird 4.0+) */
const FBIRD_READ_CONSISTENCY = 32768;

// ============================================================================
// TRANSACTION LOCK RESOLUTION
// ============================================================================

/** @var int Wait for lock resolution */
const FBIRD_WAIT = 128;

/** @var int No wait - fail immediately on lock conflict */
const FBIRD_NOWAIT = 256;

/** @var int Lock timeout flag (used with FBIRD_WAIT) */
const FBIRD_LOCK_TIMEOUT = 512;

// ============================================================================
// TABLE RESERVATION LOCK TYPES
// ============================================================================

/** @var int Shared table lock */
const FBIRD_LOCK_SHARED = 1024;

/** @var int Protected table lock */
const FBIRD_LOCK_PROTECTED = 2048;

/** @var int Exclusive table lock */
const FBIRD_LOCK_EXCLUSIVE = 4096;

// ============================================================================
// TABLE RESERVATION ACCESS TYPES
// ============================================================================

/** @var int Read access for table reservation */
const FBIRD_LOCK_READ = 8192;

/** @var int Write access for table reservation */
const FBIRD_LOCK_WRITE = 16384;

// ============================================================================
// EVENT CONSTANTS
// ============================================================================

/** @var int Event timeout return value */
const FBIRD_EVENT_TIMEOUT = -2;

// ============================================================================
// BLOB SEEK CONSTANTS
// ============================================================================

/** @var int Seek from beginning of BLOB */
const FBIRD_BLOB_SEEK_SET = 0;

/** @var int Seek from current position */
const FBIRD_BLOB_SEEK_CUR = 1;

/** @var int Seek from end of BLOB */
const FBIRD_BLOB_SEEK_END = 2;

// ============================================================================
// SERVICE API CONSTANTS - BACKUP OPTIONS
// ============================================================================

/** @var int */
const FBIRD_BKP_IGNORE_CHECKSUMS = 1;
/** @var int */
const FBIRD_BKP_IGNORE_LIMBO = 2;
/** @var int */
const FBIRD_BKP_METADATA_ONLY = 4;
/** @var int */
const FBIRD_BKP_NO_GARBAGE_COLLECT = 8;
/** @var int */
const FBIRD_BKP_OLD_DESCRIPTIONS = 16;
/** @var int */
const FBIRD_BKP_NON_TRANSPORTABLE = 32;
/** @var int */
const FBIRD_BKP_CONVERT = 64;

// ============================================================================
// SERVICE API CONSTANTS - RESTORE OPTIONS
// ============================================================================

/** @var int */
const FBIRD_RES_DEACTIVATE_IDX = 256;
/** @var int */
const FBIRD_RES_NO_SHADOW = 512;
/** @var int */
const FBIRD_RES_NO_VALIDITY = 1024;
/** @var int */
const FBIRD_RES_ONE_AT_A_TIME = 2048;
/** @var int */
const FBIRD_RES_REPLACE = 4096;
/** @var int */
const FBIRD_RES_CREATE = 8192;
/** @var int */
const FBIRD_RES_USE_ALL_SPACE = 16384;

// ============================================================================
// SERVICE API CONSTANTS - DATABASE PROPERTIES
// ============================================================================

/** @var int */
const FBIRD_PRP_PAGE_BUFFERS = 5;
/** @var int */
const FBIRD_PRP_SWEEP_INTERVAL = 6;
/** @var int */
const FBIRD_PRP_SHUTDOWN_DB = 7;
/** @var int */
const FBIRD_PRP_DENY_NEW_TRANSACTIONS = 10;
/** @var int */
const FBIRD_PRP_DENY_NEW_ATTACHMENTS = 9;
/** @var int */
const FBIRD_PRP_RESERVE_SPACE = 11;
/** @var int */
const FBIRD_PRP_RES_USE_FULL = 35;
/** @var int */
const FBIRD_PRP_RES = 36;
/** @var int */
const FBIRD_PRP_WRITE_MODE = 12;
/** @var int */
const FBIRD_PRP_WM_ASYNC = 37;
/** @var int */
const FBIRD_PRP_WM_SYNC = 38;
/** @var int */
const FBIRD_PRP_ACCESS_MODE = 13;
/** @var int */
const FBIRD_PRP_AM_READONLY = 39;
/** @var int */
const FBIRD_PRP_AM_READWRITE = 40;
/** @var int */
const FBIRD_PRP_SET_SQL_DIALECT = 14;
/** @var int */
const FBIRD_PRP_ACTIVATE = 256;
/** @var int */
const FBIRD_PRP_DB_ONLINE = 512;

// ============================================================================
// SERVICE API CONSTANTS - REPAIR OPTIONS
// ============================================================================

/** @var int */
const FBIRD_RPR_CHECK_DB = 16;
/** @var int */
const FBIRD_RPR_IGNORE_CHECKSUM = 32;
/** @var int */
const FBIRD_RPR_KILL_SHADOWS = 64;
/** @var int */
const FBIRD_RPR_MEND_DB = 4;
/** @var int */
const FBIRD_RPR_VALIDATE_DB = 1;
/** @var int */
const FBIRD_RPR_FULL = 128;
/** @var int */
const FBIRD_RPR_SWEEP_DB = 2;

// ============================================================================
// SERVICE API CONSTANTS - STATISTICS
// ============================================================================

/** @var int */
const FBIRD_STS_DATA_PAGES = 1;
/** @var int */
const FBIRD_STS_DB_LOG = 2;
/** @var int */
const FBIRD_STS_HDR_PAGES = 4;
/** @var int */
const FBIRD_STS_IDX_PAGES = 8;
/** @var int */
const FBIRD_STS_SYS_RELATIONS = 16;

// ============================================================================
// SERVICE API CONSTANTS - SERVER INFO
// ============================================================================

/** @var int */
const FBIRD_SVC_SERVER_VERSION = 55;
/** @var int */
const FBIRD_SVC_IMPLEMENTATION = 56;
/** @var int */
const FBIRD_SVC_GET_ENV = 59;
/** @var int */
const FBIRD_SVC_GET_ENV_LOCK = 60;
/** @var int */
const FBIRD_SVC_GET_ENV_MSG = 61;
/** @var int */
const FBIRD_SVC_USER_DBPATH = 58;
/** @var int */
const FBIRD_SVC_SVR_DB_INFO = 50;
/** @var int */
const FBIRD_SVC_GET_USERS = 68;

// ============================================================================
// CONNECTION FUNCTIONS
// ============================================================================

/**
 * Connect to a Firebird database.
 *
 * @param string      $database Database path or connection string
 * @param string|null $username Username for authentication
 * @param string|null $password Password for authentication
 * @param string|null $charset  Character set for the connection
 * @param int         $buffers  Number of database cache buffers
 * @param int         $dialect  SQL dialect (1, 2, or 3)
 * @param string|null $role     SQL role name
 * @param int         $flags    Connection flags (e.g. FBIRD_CONNECT_FORCE_NEW)
 * Since v10.0.0, returns a Firebird\Connection object instead of a resource.
 * The object is accepted by all fbird_* functions that previously took resources.
 *
 * @return \Firebird\Connection|false Connection object on success, false on failure
 * @since 7.0.0
 * @since 10.0.0 Returns \Firebird\Connection object instead of resource
 */
function fbird_connect(
    string $database,
    ?string $username = null,
    ?string $password = null,
    ?string $charset = null,
    int $buffers = 0,
    int $dialect = 3,
    ?string $role = null,
    int $flags = 0
): mixed {}

/**
 * Open a persistent connection to a Firebird database.
 *
 * @param string      $database Database path or connection string
 * @param string|null $username Username for authentication
 * @param string|null $password Password for authentication
 * @param string|null $charset  Character set for the connection
 * @param int         $buffers  Number of database cache buffers
 * @param int         $dialect  SQL dialect (1, 2, or 3)
 * @param string|null $role     SQL role name
 * Since v10.0.0, returns a Firebird\Connection object instead of a resource.
 * The object is accepted by all fbird_* functions that previously took resources.
 *
 * @return \Firebird\Connection|false Connection object on success, false on failure
 * @since 7.0.0
 * @since 10.0.0 Returns \Firebird\Connection object instead of resource
 */
function fbird_pconnect(
    string $database,
    ?string $username = null,
    ?string $password = null,
    ?string $charset = null,
    int $buffers = 0,
    int $dialect = 3,
    ?string $role = null
): mixed {}

/**
 * Close a Firebird database connection.
 *
 * Since v10.0.0, accepts Firebird\Connection objects in addition to resources.
 *
 * @param resource|\Firebird\Connection|null $connection Connection resource or object
 * @return bool True on success, false on failure
 * @since 7.0.0
 * @since 10.0.0 Accepts \Firebird\Connection objects
 */
function fbird_close(mixed $connection = null): bool {}

/**
 * Drop a Firebird database.
 *
 * @param resource|string|null $connection Connection resource or database path (v9.0.0+)
 * @param string|null          $username   Username (if path used)
 * @param string|null          $password   Password (if path used)
 * @return bool True on success, false on failure
 * @since 7.0.0
 */
function fbird_drop_db(mixed $connection = null, ?string $username = null, ?string $password = null): bool {}

/**
 * Create a new Firebird database.
 *
 * Creates a database at the specified path with optional credentials, charset,
 * and page size. Returns a connection resource to the newly created database.
 *
 * @param string      $database  Database connection string (e.g. "host:/path/to/db.fdb")
 * @param string|null $username  Username (default: SYSDBA)
 * @param string|null $password  Password
 * @param string|null $charset   Character set (default: UTF8)
 * @param int|null    $page_size Page size in bytes (default: 8192)
 * @return resource|false Connection resource on success, false on failure
 * @since 9.0.0
 */
function fbird_create_database(
    string $database,
    ?string $username = null,
    ?string $password = null,
    ?string $charset = null,
    ?int $page_size = null
): mixed {}

/**
 * Get the last insert ID using a named generator/sequence.
 *
 * Firebird does not have implicit auto-increment IDs. This function queries
 * the current value of a named generator (sequence) using GEN_ID(sequence, 0).
 *
 * @param resource $link_identifier Connection resource
 * @param string   $sequence        Generator/sequence name
 * @return int|false Current generator value or false on failure
 * @since 8.2.0
 */
function fbird_last_insert_id(mixed $link_identifier, ?string $sequence = null): int|false {}

// ============================================================================
// QUERY FUNCTIONS
// ============================================================================

/**
 * Execute a query against a Firebird database.
 *
 * @param resource|string $link_or_query Connection/transaction or query string
 * @param mixed           ...$args       Additional arguments
 * @return resource|bool Result resource or bool for DML
 * @since 7.0.0
 */
function fbird_query(mixed $link_or_query, mixed ...$args): mixed {}

/**
 * Prepare a SQL statement for later execution.
 *
 * @param resource|string|null $link_or_trans_or_query   First argument
 * @param resource|string|null $link_or_trans_or_query_2 Second argument
 * @param string|null          $query                    Query string
 * @return resource|false Prepared statement resource
 * @since 7.0.0
 */
function fbird_prepare(
    mixed $link_or_trans_or_query,
    mixed $link_or_trans_or_query_2 = null,
    ?string $query = null
): mixed {}

/**
 * Prepare a SQL statement with a fixed, non-shifting signature.
 *
 * Unlike fbird_prepare() which uses argument-shifting heuristics,
 * this function has a clear signature: connection first, query second,
 * optional transaction third.
 *
 * @param resource    $link_identifier Connection resource
 * @param string      $query           SQL query string
 * @param resource|null $trans_handle  Transaction resource (optional)
 * @return resource|false Prepared statement resource or false on failure
 * @since 9.0.0
 */
function fbird_prepare_ex(
    mixed $link_identifier,
    string $query,
    mixed $trans_handle = null
): mixed {}

/**
 * Execute a prepared statement.
 *
 * @param resource $query     Prepared statement resource
 * @param mixed    ...$bind_args Bind parameters
 * @return resource|bool Result resource or bool
 * @since 7.0.0
 */
function fbird_execute(mixed $query, mixed ...$bind_args): mixed {}

/**
 * Execute a DML/DDL statement with parameters within an explicit transaction.
 *
 * Prepares and executes a non-SELECT SQL statement atomically. Throws an error
 * if a SELECT statement is given; use fbird_execute_query() for those.
 *
 * @param resource               $trans_handle Transaction resource
 * @param string                 $query        SQL DML/DDL statement
 * @param array<int, mixed>|null $params       Bind parameters (optional)
 * @return int|false Affected-row count (0 for DDL) or false on error
 * @since 7.0.0
 */
function fbird_execute_statement(mixed $trans_handle, string $query, ?array $params = null): int|false {}

/**
 * Execute a SELECT/RETURNING statement with parameters within an explicit transaction.
 *
 * Prepares and executes a SELECT query atomically and returns a result resource.
 * Throws an error if a DML statement is given; use fbird_execute_statement() for those.
 *
 * @param resource               $trans_handle Transaction resource
 * @param string                 $query        SQL SELECT or RETURNING statement
 * @param array<int, mixed>|null $params       Bind parameters (optional)
 * @return resource|false Result resource or false on error
 * @since 7.0.0
 */
function fbird_execute_query(mixed $trans_handle, string $query, ?array $params = null): mixed {}

/**
 * Execute any SQL statement via an auto-managed transaction on an OO API connection.
 *
 * Requires a Firebird 3.0+ OO API connection. Auto-detects
 * SELECT vs DML/DDL and returns the appropriate result.
 *
 * @param resource               $link_identifier Database connection resource (OO API)
 * @param string                 $query           SQL statement
 * @param array<int, mixed>|null $params          Bind parameters (optional)
 * @return resource|int|false Result resource (SELECT), int (DML affected rows), or false
 * @since 7.0.0
 */
function fbird_execute_auto(mixed $link_identifier, string $query, ?array $params = null): mixed {}

/**
 * Execute a parameterized query with explicit link and transaction.
 *
 * Required by doctrine-firebird-driver: passes both connection and explicit
 * transaction handle simultaneously (unlike fbird_execute_query which infers
 * the link from the transaction).
 *
 * @param resource     $link_identifier Connection resource
 * @param resource     $trans_handle    Transaction resource
 * @param string       $query           SQL statement
 * @param array<mixed>|null $params     Bind parameters (optional)
 * @return resource|int|false           Result for SELECT, row count for DML, false on error
 * @since 7.1.0
 */
function fbird_query_params_tx(mixed $link_identifier, mixed $trans_handle, string $query, ?array $params = null): mixed {}

/**
 * Free a prepared statement.
 *
 * @param resource $query Prepared statement resource
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_free_query(mixed $query): bool {}

/**
 * Free a result set.
 *
 * @param resource $result Result resource
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_free_result(mixed $result): bool {}

// ============================================================================
// FETCH FUNCTIONS
// ============================================================================

/**
 * Fetch a row as a numeric array.
 *
 * @param resource $result      Result resource
 * @param int      $fetch_flags Fetch flags (e.g. FBIRD_TEXT, FBIRD_FETCH_DATE_OBJ)
 * @return array<int, mixed>|false Row data or false
 * @since 7.0.0
 */
function fbird_fetch_row(mixed $result, int $fetch_flags = 0): array|false {}

/**
 * Fetch a row as an associative array.
 *
 * @param resource $result      Result resource
 * @param int      $fetch_flags Fetch flags (e.g. FBIRD_TEXT, FBIRD_FETCH_DATE_OBJ)
 * @return array<string, mixed>|false Row data or false
 * @since 7.0.0
 */
function fbird_fetch_assoc(mixed $result, int $fetch_flags = 0): array|false {}

/**
 * Fetch a row as an object.
 *
 * @param resource $result      Result resource
 * @param int      $fetch_flags Fetch flags (e.g. FBIRD_TEXT, FBIRD_FETCH_DATE_OBJ)
 * @return object|false Row object or false
 * @since 7.0.0
 */
function fbird_fetch_object(mixed $result, int $fetch_flags = 0): object|false {}

/**
 * Name a result set for cursor operations.
 *
 * @param resource $result Result resource
 * @param string   $name   Cursor name
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_name_result(mixed $result, string $name): bool {}

// ============================================================================
// FIELD/PARAMETER INFO FUNCTIONS
// ============================================================================

/**
 * Get number of fields in result set.
 *
 * @param resource $result Result resource
 * @return int|false Number of fields or false
 * @since 7.0.0
 */
function fbird_num_fields(mixed $result): int|false {}

/**
 * Get number of parameters in prepared statement.
 *
 * @param resource $query Prepared statement resource
 * @return int|false Number of parameters or false
 * @since 7.0.0
 */
function fbird_num_params(mixed $query): int|false {}

/**
 * Get number of affected rows.
 *
 * @param resource|null $link Connection or statement resource
 * @return int Number of affected rows
 * @since 7.0.0
 */
function fbird_affected_rows(mixed $link = null): int {}

/**
 * Get field information.
 *
 * @param resource $result       Result resource
 * @param int      $field_number Field index (0-based)
 * @return array<string, mixed>|false Field info or false
 * @since 7.0.0
 */
function fbird_field_info(mixed $result, int $field_number): array|false {}

/**
 * Get parameter information.
 *
 * @param resource $query        Prepared statement resource
 * @param int      $param_number Parameter index (0-based)
 * @return array<string, mixed>|false Parameter info or false
 * @since 7.0.0
 */
function fbird_param_info(mixed $query, int $param_number): array|false {}

// ============================================================================
// TRANSACTION FUNCTIONS
// ============================================================================

/**
 * Start a transaction.
 *
 * @param resource|int|null $link_or_flags Connection resource or transaction flags
 * @param mixed             ...$args       Additional connection resources for multi-db transaction
 * @return resource|false Transaction resource
 * @since 7.0.0
 */
function fbird_trans(mixed $link_or_flags = null, mixed ...$args): mixed {}

/**
 * Start a transaction with options.
 *
 * @param resource                                     $link    Connection resource
 * @param int|array<string, array<string, int>|bool|int> $options Transaction options
 * @return resource|false Transaction resource
 * @since 7.0.0
 */
function fbird_trans_start(mixed $link, mixed $options = 0): mixed {}

/**
 * Commit a transaction.
 *
 * @param resource|null $link Transaction or connection resource
 * @return bool True on success, false on failure
 * @since 7.0.0
 */
function fbird_commit(mixed $link = null): bool {}

/**
 * Rollback a transaction.
 *
 * @param resource|null $link Transaction or connection resource
 * @return bool True on success, false on failure
 * @since 7.0.0
 */
function fbird_rollback(mixed $link = null): bool {}

/**
 * Commit and retain a transaction.
 *
 * @param resource|null $link Transaction or connection resource
 * @return bool True on success, false on failure
 * @since 7.0.0
 */
function fbird_commit_ret(mixed $link = null): bool {}

/**
 * Rollback and retain a transaction.
 *
 * @param resource|null $link Transaction or connection resource
 * @return bool True on success, false on failure
 * @since 7.0.0
 */
function fbird_rollback_ret(mixed $link = null): bool {}

/**
 * Create a savepoint.
 *
 * @param resource $link Transaction resource
 * @param string   $name Savepoint name
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_savepoint(mixed $link, string $name): bool {}

/**
 * Rollback to a savepoint.
 *
 * @param resource $link Transaction resource
 * @param string   $name Savepoint name
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_rollback_savepoint(mixed $link, string $name): bool {}

/**
 * Release a savepoint.
 *
 * @param resource $link Transaction resource
 * @param string   $name Savepoint name
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_release_savepoint(mixed $link, string $name): bool {}

/**
 * Get transaction information.
 *
 * @param resource $trans_handle Transaction resource
 * @return array<string, mixed>|false Transaction info or false
 * @since 7.0.0
 */
function fbird_trans_info(mixed $trans_handle): array|false {}

// ============================================================================
// BLOB FUNCTIONS
// ============================================================================

/**
 * Create a blob for adding data.
 *
 * @param resource|null $link Database connection
 * @return resource|false Blob handle or false
 * @since 7.0.0
 */
function fbird_blob_create(mixed $link = null): mixed {}

/**
 * Add data to a blob.
 *
 * @param resource $blob Blob handle
 * @param string   $data Data to add
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_blob_add(mixed $blob, string $data): bool {}

/**
 * Close a blob and get its ID.
 *
 * @param resource $blob Blob handle
 * @return string|false Blob ID or false
 * @since 7.0.0
 */
function fbird_blob_close(mixed $blob): string|false {}

/**
 * Cancel a blob.
 *
 * @param resource $blob Blob handle
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_blob_cancel(mixed $blob): bool {}

/**
 * Open a blob for reading.
 *
 * @param resource|string $link_or_blob_id Connection or blob ID
 * @param string|null     $blob_id         Blob ID
 * @return resource|false Blob handle or false
 * @since 7.0.0
 */
function fbird_blob_open(mixed $link_or_blob_id, ?string $blob_id = null): mixed {}

/**
 * Get data from a blob.
 *
 * @param resource $blob   Blob handle
 * @param int      $length Number of bytes to read
 * @return string|false Data or false
 * @since 7.0.0
 */
function fbird_blob_get(mixed $blob, int $length): string|false {}

/**
 * Output a blob to the browser.
 *
 * @param resource|string $link_or_id Connection or blob ID
 * @param string|null     $blob_id    Blob ID
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_blob_echo(mixed $link_or_id, ?string $blob_id = null): bool {}

/**
 * Get blob information.
 *
 * @param resource|string $link_or_id Connection or blob ID
 * @param string|null     $blob_id    Blob ID
 * @return array<string, mixed>|false Blob info or false
 * @since 7.0.0
 */
function fbird_blob_info(mixed $link_or_id, ?string $blob_id = null): array|false {}

/**
 * Import a file as a blob.
 *
 * @param resource $link Database connection
 * @param resource $file File handle (e.g. from fopen)
 * @return string|false Blob ID or false
 * @since 7.0.0
 */
function fbird_blob_import(mixed $link, mixed $file): string|false {}

/**
 * Create a blob stream.
 *
 * @param resource|null $link Database connection
 * @return resource|false Stream handle or false
 * @since 7.0.0
 */
function fbird_blob_create_stream(mixed $link = null): mixed {}

/**
 * Open a blob as a stream.
 *
 * @param resource|string $link_or_id Connection or blob ID
 * @param string|null     $blob_id    Blob ID
 * @return resource|false Stream handle or false
 * @since 7.0.0
 */
function fbird_blob_open_stream(mixed $link_or_id, ?string $blob_id = null): mixed {}

/**
 * Create a seekable blob.
 *
 * @param resource|null $link Database connection
 * @return resource|false Blob handle or false
 * @since 7.0.0
 */
function fbird_blob_create_seekable(mixed $link = null): mixed {}

/**
 * Open a blob as seekable.
 *
 * @param resource|string $link_or_id Connection or blob ID
 * @param string|null     $blob_id    Blob ID
 * @return resource|false Blob handle or false
 * @since 7.0.0
 */
function fbird_blob_open_seekable(mixed $link_or_id, ?string $blob_id = null): mixed {}

/**
 * Seek within a blob.
 *
 * @param resource $blob   Blob handle
 * @param int      $offset Offset in bytes
 * @param int      $whence Seek mode (FBIRD_BLOB_SEEK_*)
 * @return int|false New position or false
 * @since 7.0.0
 */
function fbird_blob_seek(mixed $blob, int $offset, int $whence = 0): int|false {}

// ============================================================================
// GENERATOR FUNCTIONS
// ============================================================================

/**
 * Get the next value from a generator/sequence.
 *
 * @param string        $generator Generator name
 * @param int           $increment Increment value
 * @param resource|null $link      Connection resource
 * @return int|string|false Generator value or false
 * @since 7.0.0
 */
function fbird_gen_id(string $generator, int $increment = 1, mixed $link = null): int|string|false {}

// ============================================================================
// ERROR FUNCTIONS
// ============================================================================

/**
 * Return the last error message.
 *
 * @return string|false Error message or false
 * @since 7.0.0
 */
function fbird_errmsg(): string|false {}

/**
 * Return the last error code.
 *
 * @return int|false Error code or false
 * @since 7.0.0
 */
function fbird_errcode(): int|false {}

/**
 * Return the SQLSTATE error code.
 *
 * @return string|false 5-character SQLSTATE code or false
 * @since 7.0.0
 */
function fbird_sqlstate(): string|false {}

/**
 * Escape a string for use in SQL.
 *
 * @param string $string String to escape
 * @return string Escaped string
 * @since 7.0.0
 */
function fbird_escape_string(string $string): string {}

/**
 * Set the exception mode.
 *
 * @param int $mode FBIRD_EXCEPTION_MODE_SILENT or FBIRD_EXCEPTION_MODE_THROW
 * @return bool True on success
 * @since 9.0.0
 */
function fbird_set_exception_mode(int $mode): bool {}

/**
 * Get the current exception mode.
 *
 * @return int Current exception mode (FBIRD_EXCEPTION_MODE_SILENT or FBIRD_EXCEPTION_MODE_THROW)
 * @since 9.0.0
 */
function fbird_get_exception_mode(): int {}

// ============================================================================
// EVENT FUNCTIONS
// ============================================================================

/**
 * Wait for a database event (blocking).
 *
 * @param resource|string $link_or_event Connection or event name
 * @param string          ...$events     Event names
 * @return string|false Event name or false
 * @since 7.0.0
 */
function fbird_wait_event(mixed $link_or_event, string ...$events): string|false {}

/**
 * Set an event handler callback.
 *
 * @param resource|callable $link_or_callback Connection or callback
 * @param callable|string   $callback_or_event Callback or event name
 * @param string            ...$events        Event names
 * @return resource|false Event handler or false
 * @since 7.0.0
 */
function fbird_set_event_handler(mixed $link_or_callback, mixed $callback_or_event, string ...$events): mixed {}

/**
 * Poll for events (non-blocking).
 *
 * @param resource $event      Event handler resource
 * @param int      $timeout_ms Timeout in milliseconds (0 = non-blocking)
 * @return array<string, int>|int|false Event counts or timeout (-2)
 * @since 7.0.0
 */
function fbird_poll_event(mixed $event, int $timeout_ms = 0): mixed {}

/**
 * Free an event handler.
 *
 * @param resource $event Event handler resource
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_free_event_handler(mixed $event): bool {}

// ============================================================================
// SERVICE MANAGER FUNCTIONS
// ============================================================================

/**
 * Attach to the service manager.
 *
 * @param string $host     Host name (e.g. "localhost")
 * @param string $username Username
 * @param string $password Password
 * @return resource|false Service handle or false
 * @since 7.0.0
 */
function fbird_service_attach(string $host, string $username, string $password): mixed {}

/**
 * Detach from the service manager.
 *
 * @param resource $service Service handle
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_service_detach(mixed $service): bool {}

/**
 * Backup a database.
 *
 * @param resource $service Service handle
 * @param string   $source  Source database path
 * @param string   $dest    Destination backup file path
 * @param int      $options Backup options (FBIRD_BKP_*)
 * @param bool     $verbose Verbose output
 * @return mixed Result
 * @since 7.0.0
 */
function fbird_backup(mixed $service, string $source, string $dest, int $options = 0, bool $verbose = false): mixed {}

/**
 * Restore a database.
 *
 * @param resource $service Service handle
 * @param string   $source  Source backup file path
 * @param string   $dest    Destination database path
 * @param int      $options Restore options (FBIRD_RES_*)
 * @param bool     $verbose Verbose output
 * @return mixed Result
 * @since 7.0.0
 */
function fbird_restore(mixed $service, string $source, string $dest, int $options = 0, bool $verbose = false): mixed {}

/**
 * Perform database maintenance.
 *
 * @param resource $service  Service handle
 * @param string   $db       Database path
 * @param int      $action   Maintenance action (FBIRD_PRP_* or FBIRD_RPR_*)
 * @param int      $argument Action argument
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_maintain_db(mixed $service, string $db, int $action, int $argument = 0): bool {}

/**
 * Get database information via service manager.
 *
 * @param resource $service  Service handle
 * @param string   $db       Database path
 * @param int      $action   Information action
 * @param int      $argument Action argument
 * @return string|false Information string or false
 * @since 7.0.0
 */
function fbird_db_info(mixed $service, string $db, int $action, int $argument = 0): string|false {}

/**
 * Get server information via service manager.
 *
 * @param resource $service Service handle
 * @param int      $action  Information action (FBIRD_SVC_*)
 * @return string|false Information string or false
 * @since 7.0.0
 */
function fbird_server_info(mixed $service, int $action): string|false {}

// ============================================================================
// USER MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Add a user to the Firebird security database.
 *
 * @param resource    $service     Service handle
 * @param string      $username    Username
 * @param string      $password    Password
 * @param string|null $first_name  First name
 * @param string|null $middle_name Middle name
 * @param string|null $last_name   Last name
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_add_user(
    mixed $service,
    string $username,
    string $password,
    ?string $first_name = null,
    ?string $middle_name = null,
    ?string $last_name = null
): bool {}

/**
 * Modify a user in the Firebird security database.
 *
 * @param resource    $service     Service handle
 * @param string      $username    Username
 * @param string      $password    Password
 * @param string|null $first_name  First name
 * @param string|null $middle_name Middle name
 * @param string|null $last_name   Last name
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_modify_user(
    mixed $service,
    string $username,
    string $password,
    ?string $first_name = null,
    ?string $middle_name = null,
    ?string $last_name = null
): bool {}

/**
 * Delete a user from the Firebird security database.
 *
 * @param resource $service  Service handle
 * @param string   $username Username
 * @return bool True on success
 * @since 7.0.0
 */
function fbird_delete_user(mixed $service, string $username): bool {}

// ============================================================================
// VERSION FUNCTIONS
// ============================================================================

/**
 * Get Firebird client library version string.
 *
 * @return string Version string
 * @since 7.0.0
 */
function fbird_get_client_version(): string {}

/**
 * Get Firebird client library major version.
 *
 * @return int Major version number
 * @since 7.0.0
 */
function fbird_get_client_major_version(): int {}

/**
 * Get Firebird client library minor version.
 *
 * @return int Minor version number
 * @since 7.0.0
 */
function fbird_get_client_minor_version(): int {}

// ============================================================================
// CONNECTION INFO FUNCTIONS
// ============================================================================

/**
 * Get database connection statistics and information.
 *
 * @param resource|null $link_identifier Database connection resource
 * @return array<string, int>|false Connection statistics or false
 * @since 7.0.0
 */
function fbird_connection_info(mixed $link_identifier = null): array|false {}

// ============================================================================
// LIMBO TRANSACTION FUNCTIONS
// ============================================================================

/**
 * Get list of limbo (in-doubt) transaction IDs.
 *
 * @param resource|null $link_identifier Database connection
 * @param int           $max_count       Maximum count
 * @return array<int, int>|false Array of transaction IDs or false
 * @since 7.0.0
 */
function fbird_get_limbo_transactions(mixed $link_identifier = null, int $max_count = 100): array|false {}

/**
 * Reconnect to a limbo transaction for recovery.
 *
 * @param resource $link_identifier Database connection
 * @param int      $transaction_id  Transaction ID
 * @return resource|false Transaction handle or false
 * @since 7.0.0
 */
function fbird_reconnect_transaction(mixed $link_identifier, int $transaction_id): mixed {}

// ============================================================================
// BATCH API FUNCTIONS (Firebird 4.0+)
// ============================================================================

/**
 * Create a batch from a prepared statement.
 *
 * @param resource      $query            Prepared statement
 * @param resource|null $trans_identifier Transaction handle
 * @return resource|false Batch handle or false
 * @since 9.0.0
 */
function fbird_batch_create(mixed $query, mixed $trans_identifier = null): mixed {}

/**
 * Add a row of parameters to the batch.
 *
 * @param resource $batch  Batch handle
 * @param mixed    ...$args Parameters matching the statement
 * @return bool True on success
 * @since 9.0.0
 */
function fbird_batch_add(mixed $batch, mixed ...$args): bool {}

/**
 * Create an inline BLOB in batch context.
 *
 * @param resource $batch Batch handle
 * @param string   $data  BLOB data
 * @param int      $type  BLOB type
 * @return string|false BLOB ID or false
 * @since 9.0.0
 */
function fbird_batch_add_blob(mixed $batch, string $data, int $type = 0): string|false {}

/**
 * Register an existing BLOB for batch use.
 *
 * @param resource $batch   Batch handle
 * @param string   $blob_id BLOB ID
 * @return string|false Batch BLOB ID or false
 * @since 9.0.0
 */
function fbird_batch_register_blob(mixed $batch, string $blob_id): string|false {}

/**
 * Execute the batch.
 *
 * @param resource $batch Batch handle
 * @return array{total_processed: int, success_count: int, error_count: int}|false Results or false
 * @since 9.0.0
 */
function fbird_batch_execute(mixed $batch): array|false {}

/**
 * Cancel the batch without executing.
 *
 * @param resource $batch Batch handle
 * @return bool True on success
 * @since 9.0.0
 */
function fbird_batch_cancel(mixed $batch): bool {}

/**
 * Returns the BLOB alignment requirement for this batch, in bytes.
 *
 * Only available with Firebird 4.0+.
 *
 * @param resource $batch Batch resource
 * @return int|false Alignment in bytes, or false on error
 * @since 9.0.0
 */
function fbird_batch_get_blob_alignment(mixed $batch): int|false {}

/**
 * Append a chunk of data to the BLOB currently being constructed in the batch.
 *
 * @param resource $batch Batch resource
 * @param string $data Binary chunk
 * @return bool TRUE on success, FALSE on failure
 * @since 9.0.0
 */
function fbird_batch_append_blob_data(mixed $batch, string $data): bool {}

/**
 * Add BLOB data to the batch using the IBatch addBlobStream protocol.
 *
 * @param resource $batch Batch resource
 * @param string $data Binary BLOB stream data
 * @return bool TRUE on success, FALSE on failure
 * @since 9.0.0
 */
function fbird_batch_add_blob_stream(mixed $batch, string $data): bool {}

/**
 * Set the default BLOB Property Block (BPB) for all BLOBs in this batch.
 *
 * @param resource $batch Batch resource
 * @param string $bpb Raw binary BPB data
 * @return bool TRUE on success, FALSE on failure
 * @since 9.0.0
 */
function fbird_batch_set_default_bpb(mixed $batch, string $bpb): bool {}

// ============================================================================
// INSPECTION FUNCTIONS
// ============================================================================

/**
 * Kill a database attachment by ID.
 *
 * @param resource $link_or_trans Connection or transaction
 * @param int      $attachment_id Attachment ID
 * @return bool True on success
 * @since 8.0.0
 */
function fbird_kill_attachment(mixed $link_or_trans, int $attachment_id): bool {}

/**
 * List attachments blocking a table.
 *
 * @param resource $link_or_trans Connection or transaction
 * @param string   $table_name    Table name
 * @return array<int, array{attachment_id: int, user: string}>|false Blockers or false
 * @since 8.0.0
 */
function fbird_list_table_blockers(mixed $link_or_trans, string $table_name): array|false {}

/**
 * Force drop a table by killing blockers.
 *
 * @param resource $link_or_trans Connection or transaction
 * @param string   $table_name    Table name
 * @return bool True on success
 * @since 8.0.0
 */
function fbird_drop_table_force(mixed $link_or_trans, string $table_name): bool {}

// ============================================================================
// EXTENSION-PROVIDED CLASSES (Firebird namespace)
// ============================================================================
// Note: Classes in the Firebird namespace are defined in a separate file
// to comply with PHP namespace rules.
// See: firebird-classes.php
