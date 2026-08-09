# Feature Specification: FirebirdMetadataProvider (DBAL 4 Introspection API)

**Feature Branch**: `004-firebird-metadata-provider`
**Created**: 2026-08-09
**Issue**: #154
**Status**: Planning

## Context

DBAL 4 introduced `AbstractPlatform::createMetadataProvider()` as the new
schema introspection API, replacing the deprecated `select*()` /
`_getPortable*Definition()` path on `AbstractSchemaManager`.

The default implementation throws `NotSupported::new(__METHOD__)`. All 6
official DBAL platforms (MySQL, PostgreSQL, SQLite, Oracle, SQL Server,
DB2) override it. `FirebirdPlatform` does not, making it the only platform
where the new introspection API fails.

This is a **general-purpose DBAL contract gap**, not an amicron-specific
workaround. The old `list*()` path still works (deprecated) but will be
removed in DBAL 5.0.

## User Scenarios & Testing

### US-1: introspectTableByUnquotedName works (Priority: P0)

**Why this priority**: ORM SchemaTool and Migrations call `introspectSchema()`
which internally calls `listTables()` (old path). But any consumer using the
new `introspectTableByUnquotedName()` directly will fail. This is the most
common introspection call.

**Acceptance Criteria**:
1. **Given** a Firebird table with various column types (integer, varchar,
   decimal, blob, timestamp, identity column on FB3+),
   **When** `introspectTableByUnquotedName($name)` is called,
   **Then** the returned `Table` object has identical columns, indexes,
   primary key, and foreign keys to the old-path `listTableDetails($name)`.

2. **Given** a Firebird table with a `GENERATED ALWAYS AS IDENTITY` column
   (FB3+ only),
   **When** the table is introspected via the new API,
   **Then** the column's `getAutoincrement()` returns `true`.

### US-2: introspectTableNames works (Priority: P1)

**Acceptance Criteria**:
1. **Given** a Firebird database with user tables and system tables,
   **When** `introspectTableNames()` is called,
   **Then** only user tables are returned (system tables excluded via
   `RDB$SYSTEM_FLAG = 0` filter).

### US-3: introspectViews works (Priority: P1)

**Acceptance Criteria**:
1. **Given** a Firebird database with user-defined views,
   **When** `introspectViews()` is called,
   **Then** each view's name and SQL definition (from `RDB$VIEW_SOURCE`)
   are returned.

### US-4: introspectSequences works (Priority: P1)

**Acceptance Criteria**:
1. **Given** a Firebird database with user-defined generators,
   **When** `introspectSequences()` is called,
   **Then** each generator's name and sequence configuration (parsed from
   `RDB$DESCRIPTION` JSON) are returned.

### US-5: NotSupported for databases and schemas (Priority: P2)

**Acceptance Criteria**:
1. `introspectDatabaseNames()` throws `NotSupported` (Firebird databases are
   file paths, not enumerable via SQL).
2. `introspectSchemaNames()` throws `NotSupported` (Firebird has no schemas).

### US-6: FK constraint metadata is accurate (Priority: P1)

**Acceptance Criteria**:
1. **Given** a Firebird FK with `ON UPDATE CASCADE` and `ON DELETE SET NULL`,
   **When** introspected via the new API,
   **Then** `getOnUpdateAction()` returns `ReferentialAction::CASCADE` and
   `getOnDeleteAction()` returns `ReferentialAction::SET_NULL`.

2. **Given** a Firebird FK declared `DEFERRABLE INITIALLY DEFERRED`,
   **When** introspected,
   **Then** `isDeferrable()` returns `true` and `isDeferred()` returns `true`.

### US-7: Parity with old path (Priority: P0)

**Acceptance Criteria**:
1. **Given** a schema with tables, indexes, FKs, views, and sequences,
   **When** introspected via both old (`listTables()`) and new
   (`introspectTables()`) paths,
   **Then** the resulting `Schema` objects are structurally identical (same
   tables, same columns, same types, same indexes, same FKs).

## Design Decisions

### DD-1: No schemas (Oracle/DB2 pattern)

Firebird has no schema concept. Every `*ForTable()` method rejects non-null
`$schemaName` via `UnsupportedName::fromNonNullSchemaName()`. All yielded
row objects set `schemaName: null`.

### DD-2: getAllDatabaseNames / getAllSchemaNames = NotSupported

Firebird databases are file paths, not enumerable via in-band SQL.
Schemas do not exist.

### DD-3: Constructor signature

```php
public function __construct(
    private Connection $connection,
    private FirebirdPlatform $platform,
)
```

No database name stored (Oracle/SQL Server/DB2 pattern). Firebird
connections are scoped to one database file.

### DD-4: SQL queries

SQL is written directly in the MetadataProvider (not delegated to
SchemaManager or Platform). This matches the pattern used by all 6
official drivers. The SQL is adapted from the existing
`FirebirdSchemaManager::select*()` methods, which are battle-tested.

### DD-5: Column mapping via Column::editor()

The new API requires `Column::editor()->create()` (not the deprecated
`new Column()`). The type-detection logic from
`_getPortableTableColumnDefinition()` is ported to the editor API:

- `setTypeName($doctrineType)` instead of `Type::getType($type)`
- `setQuotedName($name)` instead of constructor parameter
- `setLength()`, `setPrecision()`, `setScale()`, `setNotNull()`,
  `setDefaultValue()`, `setAutoincrement()`, `setComment()`, `setFixed()`

### DD-6: Identity columns (FB3+)

The `RDB$IDENTITY_TYPE` column is only available on Firebird 3+. The
MetadataProvider checks `$this->platform instanceof Firebird3Platform`
to include it in the SQL, same as the existing `selectTableColumns()`.

### DD-7: IndexType mapping

Binary: `RDB$UNIQUE_FLAG` true → `IndexType::UNIQUE`, false →
`IndexType::REGULAR`. Firebird has no FULLTEXT or SPATIAL indexes.

`isClustered`: always `false` for regular indexes, `true` for PK constraints
(Oracle pattern — treat PK backing index as the organizing index for diff
parity).

### DD-8: FK metadata

| Field | Source column | Mapping |
|-------|--------------|---------|
| `matchType` | `RDB$REF_CONSTRAINTS.RDB$MATCH_OPTION` | `SIMPLE`/`FULL`/`PARTIAL` → enum; fallback `SIMPLE` |
| `onUpdateAction` | `RDB$REF_CONSTRAINTS.RDB$UPDATE_RULE` | `CASCADE`/`NO ACTION`/`SET NULL`/`SET DEFAULT`/`RESTRICT` → enum |
| `onDeleteAction` | `RDB$REF_CONSTRAINTS.RDB$DELETE_RULE` | Same as above |
| `isDeferrable` | `RDB$RELATION_CONSTRAINTS.RDB$DEFERRABLE` | `YES` → `true` |
| `isDeferred` | `RDB$RELATION_CONSTRAINTS.RDB$INITIALLY_DEFERRED` | `DEFERRED` → `true` |

### DD-9: Sequences (generators)

Firebird calls them generators (`RDB$GENERATORS`). The allocation size and
initial value are not stored in the catalog — they are encoded as JSON in
`RDB$DESCRIPTION` by this driver's `getCreateSequenceSQL()`.

```json
{"allocationSize": 1, "initialValue": 1, "cache": null}
```

`cacheSize`: always `null` (Firebird generators have no cache concept, SQL
Server pattern).

### DD-10: Default value parsing

Firebird stores defaults in `RDB$DEFAULT_SOURCE` as `DEFAULT <expression>`.
The parser:

1. Strips the `DEFAULT` keyword
2. Returns `null` for `NULL` or empty
3. Unwraps single-quoted string literals (with `''` → `'` unescaping)
4. Returns raw expression for numeric/function defaults

## Implementation Plan

### Files

| File | Action | Lines |
|------|--------|-------|
| `src/Platforms/Firebird/FirebirdMetadataProvider.php` | NEW | ~350 |
| `src/Platforms/FirebirdPlatform.php` | ADD `createMetadataProvider()` | +6 |

### Method matrix

| Interface method | Strategy |
|-----------------|----------|
| `getAllDatabaseNames()` | `NotSupported` |
| `getAllSchemaNames()` | `NotSupported` |
| `getAllTableNames()` | SQL: `RDB$RELATIONS` WHERE system_flag=0 AND view_blr IS NULL |
| `getTableColumnsForAllTables()` | SQL: reuse `selectTableColumns()` all-tables variant |
| `getTableColumnsForTable()` | SQL: reuse `selectTableColumns()` single-table variant |
| `getIndexColumnsForAllTables()` | SQL: reuse `selectIndexColumns()` all-tables variant |
| `getIndexColumnsForTable()` | SQL: reuse `selectIndexColumns()` single-table variant |
| `getPrimaryKeyConstraintColumnsForAllTables()` | SQL: `selectIndexColumns` WHERE constraint_type = 'PRIMARY KEY' |
| `getPrimaryKeyConstraintColumnsForTable()` | Same, filtered by table |
| `getForeignKeyConstraintColumnsForAllTables()` | SQL: reuse `selectForeignKeyColumns()` |
| `getForeignKeyConstraintColumnsForTable()` | Same, filtered by table |
| `getTableOptionsForAllTables()` | SQL: `RDB$RELATIONS` (comment only) |
| `getTableOptionsForTable()` | Same, filtered |
| `getAllViews()` | SQL: `RDB$RELATIONS` WHERE relation_type = 1 |
| `getAllSequences()` | SQL: `RDB$GENERATORS` WHERE system_flag != 1 |

### Modernization (parallel baby step)

After MetadataProvider is implemented, migrate the deprecated constructor
calls in `FirebirdSchemaManager.php` and `FirebirdPlatform.php` to the
`::editor()` API:

| Old | New |
|-----|-----|
| `new Column($name, Type::getType($type), $options)` | `Column::editor()->setQuotedName()->setTypeName()->...->create()` |
| `new Table($name, $columns, $indexes, [], $foreignKeys)` | `Table::editor()->setUnquotedName()->setColumns()->...->create()` |
| `new ForeignKeyConstraint(...)` | `ForeignKeyConstraint::editor()->...->create()` |
| `new View($name, $sql)` | `View::editor()->setQuotedName()->setSQL()->create()` |
| `new Sequence(...)` | `Sequence::editor()->...->create()` |

## Testing

### Unit Tests

`tests/Test/Unit/Platforms/Firebird/FirebirdMetadataProviderTest.php`:
- Mock `Connection` + `FirebirdPlatform`
- Verify SQL emitted per method
- Verify row-to-object mapping
- Verify `NotSupported` / `UnsupportedName` exceptions
- Verify `parseDefaultExpression()` edge cases
- Verify `createMatchType()` / `createReferentialAction()` mappings

### Functional Tests

`tests/Test/Functional/Schema/FirebirdMetadataProviderTest.php`:
- **Parity test**: Create table with all column types, introspect via both
  `listTableDetails()` and `introspectTableByUnquotedName()`, assert identical
- **Introspection API**: `introspectTableNames()`, `introspectViews()`,
  `introspectSequences()`, `introspectTableColumnsByUnquotedName()`
- **Identity column** (FB3+ only)
- **FK metadata** (deferrable, match type, referential actions)
- Run against FB3/FB4/FB5 containers

### Quality Gates

- PHPStan 8: 0 errors
- Psalm: 0 errors (baseline unchanged)
- PHPCS: 0 errors
- PHPUnit: all existing tests pass + new tests pass

## References

- Issue: #154
- DBAL interface: `vendor/doctrine/dbal/src/Schema/Metadata/MetadataProvider.php`
- Reference impls: MySQL (686L), PostgreSQL (729L), SQLite (710L), Oracle
  (581L), SQL Server (658L), DB2 (518L)
- Existing SQL: `src/Schema/FirebirdSchemaManager.php` lines 254-420, 502-601, 690-730
- Cross-ref: amicron-platform #218, #223, MR !192
