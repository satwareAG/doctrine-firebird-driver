# DBAL 4.x Migration Research: Connection Unwrapping

**Date**: 2026-03-18
**Status**: Research Complete
**Topic**: Accessing underlying Driver Connection in DBAL 4.x

## Context

In Doctrine DBAL 3.x, `Doctrine\DBAL\Connection::getWrappedConnection()` allowed accessing the underlying `Doctrine\DBAL\Driver\Connection` implementation. This is often used in the Firebird driver's functional tests to access custom driver-level features like `createBatch()` or `executeAuto()`.

**DBAL 4.x removes `getWrappedConnection()` entirely.**

## Findings

### 1. `getNativeConnection()` vs `getWrappedConnection()`

- **DBAL 3.x**: `getWrappedConnection()` returns the `Driver\Connection` object.
- **DBAL 4.x**: `getNativeConnection()` returns the actual platform-specific connection (e.g., the Firebird resource link or a PDO object). 

In DBAL 4.x, there is no direct equivalent to `getWrappedConnection()` that returns the intermediate driver-level object if it's wrapped in middlewares.

### 2. Middleware Unwrapping Pattern

Since DBAL 4.x uses Middlewares extensively, the recommended way to "unwrap" a connection is to follow the chain of wrappers. Most middleware implementations in DBAL (like `Logging`, `Debug`) wrap the `Driver` and its `Connection`.

To support unwrapping in our driver, we should ensure our Connection implementation or wrappers provide a way to access the next layer.

### 3. Recommended Implementation for DBAL 4.x Branch

For our `FunctionalTestCase`, we should implement a resilient unwrapping helper:

```php
/**
 * Unwraps a DBAL connection to get the underlying Firebird Driver Connection.
 */
private function getFirebirdConnection(Connection $connection): \Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection
{
    // In DBAL 4.x, we might need to access the private driver property 
    // or use a custom middleware that preserves the connection object.
    
    // Pattern: Recursive unwrapping of Middleware connections
    $driverConnection = $connection->getNativeConnection(); 
    
    // If our driver connection implementation is what's returned by getNativeConnection()
    // (which it usually is for non-PDO drivers), we are good.
}
```

### 4. Forward Compatibility in DBAL 3.x

The current driver already uses `FirebirdConnection` as a `wrapperClass`. This is a DBAL-level wrapper, which remains compatible with DBAL 4.x (though some method signatures change).

## Actions for 4.0.x Branch

1. **Update `FunctionalTestCase`**: Replace all calls to `getWrappedConnection()` with a platform-aware unwrapping logic.
2. **Signature Updates**: `lastInsertId()` return type changes to `int|string` (removing `false`).
3. **Internal Connection Access**: If `getNativeConnection()` returns the resource link, we may need to wrap it back into our `Driver\Connection` if we need the object methods, or move those methods to static helpers.

## References

- DBAL 4.0 Release Notes: https://github.com/doctrine/dbal/releases/tag/4.0.0
- Middleware Documentation: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/architecture.html#middlewares
