<?php

/**
 * PHP Firebird PDO Driver Stubs (pdo_fbird)
 *
 * Stub declarations for the pdo_fbird extension — a separate PDO driver
 * using the "fbird:" DSN prefix. This avoids collision with PHP's bundled
 * pdo_firebird driver ("firebird:" DSN).
 *
 * DSN format:
 *   fbird:host=<host>;dbname=<path>[;charset=<charset>][;dialect=<1|3>][;role=<role>]
 *
 * Example:
 *   $pdo = new PDO('fbird:host=localhost;dbname=/var/lib/firebird/data/mydb.fdb',
 *                  'SYSDBA', 'masterkey');
 *
 * @package   php-firebird-stubs
 * @version   10.3.6
 * @author    satware AG <info@satware.com>
 * @copyright 2025-2026 satware AG
 * @license   PHP-3.01 https://www.php.net/license/3_01.txt
 * @link      https://github.com/satwareAG/php-firebird
 *
 * @since 8.0.0
 */

declare(strict_types=1);

if (extension_loaded('pdo_fbird')) {
    return;
}

/**
 * PDO driver constants for the fbird: DSN prefix.
 *
 * These constants are registered on the PDO class when pdo_fbird is loaded.
 * The numeric values match the #define values in php_pdo_fbird.h.
 */
final class PDO_FBIRD_Constants
{
    // ── Connection / dialect attributes ──────────────────────────────────

    /** @since 8.0.0 SQL dialect (1 or 3). */
    public const FBIRD_ATTR_DIALECT      = 1001;

    /** @since 8.0.0 Character set (e.g. 'UTF8'). */
    public const FBIRD_ATTR_CHARSET      = 1002;

    /** @since 8.0.0 Connection role. */
    public const FBIRD_ATTR_ROLE         = 1003;

    /** @since 8.0.0 Page buffer count. */
    public const FBIRD_ATTR_PAGE_BUFFERS = 1004;

    // ── Transaction attributes ───────────────────────────────────────────

    /** @since 8.1.0 Transaction isolation level (use FBIRD_TXN_* values). */
    public const FBIRD_ATTR_TRANSACTION_ISOLATION_LEVEL = 1005;

    /** @since 8.1.0 Writable transaction flag (bool). */
    public const FBIRD_ATTR_WRITABLE_TRANSACTION        = 1006;

    /** @since 8.1.0 Isolation: READ COMMITTED. */
    public const FBIRD_TXN_READ_COMMITTED  = 1;

    /** @since 8.1.0 Isolation: REPEATABLE READ (CONCURRENCY). */
    public const FBIRD_TXN_REPEATABLE_READ = 2;

    /** @since 8.1.0 Isolation: SERIALIZABLE (CONSISTENCY). */
    public const FBIRD_TXN_SERIALIZABLE    = 3;

    // ── Date/time format attributes ──────────────────────────────────────

    /** @since 8.1.0 strftime format for DATE columns. */
    public const FBIRD_ATTR_DATE_FORMAT      = 1007;

    /** @since 8.1.0 strftime format for TIME columns. */
    public const FBIRD_ATTR_TIME_FORMAT      = 1008;

    /** @since 8.1.0 strftime format for TIMESTAMP columns. */
    public const FBIRD_ATTR_TIMESTAMP_FORMAT = 1009;

    // ── Fetch / column attributes ────────────────────────────────────────

    /** @since 8.1.0 Prepend table name to column names (bool). */
    public const FBIRD_ATTR_FETCH_TABLE_NAMES = 1010;

    // ── FB4+ type coercion ───────────────────────────────────────────────

    /** @since 8.3.0 Execute SET BIND OF <rule> for FB4+ type coercion. */
    public const FBIRD_ATTR_SET_BIND = 1011;

    // ── Service API attributes ───────────────────────────────────────────

    /** @since 8.3.0 Attach to service manager (setAttribute). */
    public const FBIRD_ATTR_SERVICE_ATTACH         = 1012;

    /** @since 8.3.0 Detach from service manager (setAttribute). */
    public const FBIRD_ATTR_SERVICE_DETACH         = 1013;

    /** @since 8.3.0 Backup database (setAttribute with path). */
    public const FBIRD_ATTR_SERVICE_BACKUP         = 1014;

    /** @since 8.3.0 Restore database (setAttribute with path). */
    public const FBIRD_ATTR_SERVICE_RESTORE        = 1015;

    /** @since 8.3.0 Get server version string (getAttribute). */
    public const FBIRD_ATTR_SERVICE_SERVER_VERSION = 1016;

    /** @since 8.3.0 Get server info string (getAttribute). */
    public const FBIRD_ATTR_SERVICE_SERVER_INFO    = 1017;

    /** @since 8.3.0 Get database statistics (getAttribute). */
    public const FBIRD_ATTR_SERVICE_DB_STATS       = 1018;

    /** @since 8.3.0 Add user (setAttribute with "user:pass"). */
    public const FBIRD_ATTR_SERVICE_ADD_USER       = 1019;

    /** @since 8.3.0 Modify user (setAttribute with "user:pass"). */
    public const FBIRD_ATTR_SERVICE_MODIFY_USER    = 1020;

    /** @since 8.3.0 Delete user (setAttribute with username). */
    public const FBIRD_ATTR_SERVICE_DELETE_USER    = 1021;

    // ── Event API attributes ─────────────────────────────────────────────

    /** @since 8.3.0 Register event names (setAttribute with array). */
    public const FBIRD_ATTR_EVENT_NAMES  = 1022;

    /** @since 8.3.0 Wait for events synchronously (setAttribute). */
    public const FBIRD_ATTR_EVENT_WAIT   = 1023;

    /** @since 8.3.0 Cancel event registration (setAttribute). */
    public const FBIRD_ATTR_EVENT_CANCEL = 1024;

    /** @since 8.3.0 Get event counts (getAttribute returns assoc array). */
    public const FBIRD_ATTR_EVENT_COUNT  = 1025;

    private function __construct() {}
}

/*
 * The pdo_fbird driver registers the above PDO::FBIRD_ATTR_* and
 * PDO::FBIRD_TXN_* constants directly on the PDO class at extension
 * load time. They are listed here for IDE/static-analysis awareness.
 *
 * Usage example:
 *
 *   $pdo = new PDO('fbird:host=localhost;dbname=/data/mydb.fdb', 'SYSDBA', 'masterkey', [
 *       PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
 *       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
 *       PDO::FBIRD_ATTR_DIALECT      => 3,
 *       PDO::FBIRD_ATTR_CHARSET      => 'UTF8',
 *   ]);
 *
 *   // Transactions with isolation level
 *   $pdo->setAttribute(PDO::FBIRD_ATTR_TRANSACTION_ISOLATION_LEVEL,
 *                      PDO::FBIRD_TXN_READ_COMMITTED);
 *   $pdo->beginTransaction();
 *   $pdo->exec("INSERT INTO t (col) VALUES (1)");
 *   $pdo->commit();
 *
 *   // Named parameters (converted to ? internally)
 *   $stmt = $pdo->prepare("INSERT INTO t (name, val) VALUES (:name, :val)");
 *   $stmt->execute([':name' => 'foo', ':val' => 123]);
 *
 *   // lastInsertId with sequence name
 *   $pdo->exec("INSERT INTO t (id, name) VALUES (NEXT VALUE FOR gen_t_id, 'bar')");
 *   $id = $pdo->lastInsertId('gen_t_id');
 *
 *   // Service API
 *   $version = $pdo->getAttribute(PDO::FBIRD_ATTR_SERVICE_SERVER_VERSION);
 *
 *   // Events
 *   $pdo->setAttribute(PDO::FBIRD_ATTR_EVENT_NAMES, ['my_event']);
 *   $pdo->setAttribute(PDO::FBIRD_ATTR_EVENT_WAIT, true);
 *   $counts = $pdo->getAttribute(PDO::FBIRD_ATTR_EVENT_COUNT);
 *
 * Supported PDO features:
 *   - PDO::ATTR_SERVER_VERSION, PDO::ATTR_AUTOCOMMIT, PDO::ATTR_ERRMODE
 *   - PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_NULL, PDO::PARAM_LOB
 *   - PDO::FETCH_ASSOC, PDO::FETCH_NUM, PDO::FETCH_BOTH, PDO::FETCH_OBJ
 *   - Positional (?) and named (:name) parameter binding
 *   - BLOB/LOB support via PDO::PARAM_LOB streams
 *   - GDS error code → SQLSTATE mapping
 *   - PDO::lastInsertId($sequenceName) via GEN_ID()
 *   - Transaction isolation levels (READ_COMMITTED, REPEATABLE_READ, SERIALIZABLE)
 *   - Custom date/time/timestamp formatting via strftime patterns
 *   - FETCH_TABLE_NAMES (prepend table name to column names)
 *   - Service API (backup, restore, user management, server info)
 *   - Event polling (register, wait, cancel, count)
 *   - FB4+ SET BIND type coercion
 *   - Scrollable cursors (requires Firebird 5.0+ client AND server)
 *   - Array field reading (SQL_ARRAY → PHP array)
 *   - Connection liveness check via real server ping
 *
 * Not yet implemented:
 *   - Multiple active result sets on a single connection
 */
