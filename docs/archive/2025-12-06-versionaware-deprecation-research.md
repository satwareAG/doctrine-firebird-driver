# VersionAwarePlatformDriver Deprecation Research

## Date: 2025-12-06

## Problem Statement

The `VersionAwarePlatformDriver` interface is deprecated in DBAL 3.x and will be removed in DBAL 4.x. Our Firebird driver currently uses this interface to:

1. Support version-specific platforms (Firebird 3, 4, 5)
2. **Pass custom configuration options** (like `like_cast_length`) from connection params to the platform

The second use case is the challenging one - without `VersionAwarePlatformDriver`, there's no built-in DBAL mechanism to pass custom connection parameters to the platform.

## Research Findings

### DBAL 4.x Changes (from UPGRADE.md)

```
## BC BREAK: Removed driver-level APIs that don't take the server version into account.

The `ServerInfoAwareConnection` interface has been removed. The `getServerVersion()` method has been made
part of the driver-level `Connection` interface.

The `VersionAwarePlatformDriver` interface has been removed. The `Driver::getDatabasePlatform()` method now accepts
a `ServerVersionProvider` argument that will provide the server version, if the driver relies on the server version
to instantiate a database platform.
```

### New Driver Interface (DBAL 4.x)

```php
interface Driver
{
    public function connect(array $params): DriverConnection;
    
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform;
    
    public function getExceptionConverter(): ExceptionConverter;
}
```

### How Wrapper Connection Uses Driver (DBAL 4.x)

```php
// From Doctrine\DBAL\Connection
public function getDatabasePlatform(): AbstractPlatform
{
    if ($this->platform === null) {
        $versionProvider = $this;  // Connection implements ServerVersionProvider

        if (isset($this->params['serverVersion'])) {
            $versionProvider = new StaticServerVersionProvider($this->params['serverVersion']);
        } elseif (isset($this->params['primary']['serverVersion'])) {
            $versionProvider = new StaticServerVersionProvider($this->params['primary']['serverVersion']);
        }

        $this->platform = $this->driver->getDatabasePlatform($versionProvider);
    }

    return $this->platform;
}
```

**Key Insight**: The driver's `getDatabasePlatform()` only receives a `ServerVersionProvider`. Connection params (including `firebird` options) are NOT passed.

### The Core Issue

Our current flow:
1. `Driver::connect($params)` stores `$params['firebird']` in driver
2. `Driver::createDatabasePlatformForVersion()` uses stored options to configure platform

In DBAL 4.x:
1. `Driver::connect($params)` is called
2. `Driver::getDatabasePlatform($versionProvider)` is called - no access to params!
3. If `serverVersion` param is set, `getDatabasePlatform()` may be called BEFORE `connect()`

## Solution Options

### Option 1: Custom Wrapper Connection (RECOMMENDED)

Create a `FirebirdConnection` class that extends DBAL's `Connection`:

```php
namespace Satag\DoctrineFirebirdDriver\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatform;
use Satag\DoctrineFirebirdDriver\Platforms\FirebirdPlatformConfiguration;

class FirebirdConnection extends Connection
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        $platform = parent::getDatabasePlatform();
        
        // Configure platform with firebird options from connection params
        if ($platform instanceof FirebirdPlatform) {
            $params = $this->getParams();
            $firebirdOptions = $params['firebird'] ?? [];
            $platform->setConfiguration(new FirebirdPlatformConfiguration($firebirdOptions));
        }
        
        return $platform;
    }
}
```

**Usage:**
```php
$connection = DriverManager::getConnection([
    'driver' => 'firebird',
    'host' => 'localhost',
    'dbname' => '/path/to/database.fdb',
    'wrapperClass' => FirebirdConnection::class,
    'firebird' => [
        'like_cast_length' => 4000,
    ],
]);
```

**Pros:**
- Clean separation of concerns
- Works with both DBAL 3.x and 4.x
- Doesn't require deprecated interfaces
- User explicitly opts into Firebird-specific features

**Cons:**
- Requires user to specify `wrapperClass`
- Slight API change from current approach

### Option 2: Remove VersionAwarePlatformDriver, Accept Limitation

Remove the deprecated interface and accept that:
- Platform configuration must be done via the `wrapperClass` approach
- Keep driver simple and forward-compatible

### Option 3: Lazy Configuration via Static Registry

```php
class FirebirdPlatformConfiguration
{
    private static array $configurations = [];
    
    public static function register(string $connectionId, array $options): void
    {
        self::$configurations[$connectionId] = $options;
    }
    
    public static function get(string $connectionId): array
    {
        return self::$configurations[$connectionId] ?? [];
    }
}
```

The driver's `connect()` would register configuration, and the platform would look it up.

**Cons:**
- Global state
- Cleanup required
- Connection identity is complex to determine

### Option 4: Keep VersionAwarePlatformDriver Until DBAL 4.x Required

Continue using `VersionAwarePlatformDriver` in DBAL 3.x with `@psalm-suppress` and `@phpstan-ignore` annotations. Provide migration guide for DBAL 4.x when the time comes.

## Recommendation

**Implement Option 1 (Custom Wrapper Connection)** as the DBAL 4.x-compatible solution, while keeping the current implementation for backwards compatibility.

### Implementation Steps

1. **Create `FirebirdConnection` class** that extends `Doctrine\DBAL\Connection`
2. **Override `getDatabasePlatform()`** to configure the platform with firebird options
3. **Update documentation** to recommend using `wrapperClass`
4. **Keep `VersionAwarePlatformDriver`** for now (DBAL 3.x compatibility) with deprecation suppression
5. **Add deprecation notice** in documentation for direct driver usage without wrapperClass
6. **When DBAL 4.x support added**: Remove `VersionAwarePlatformDriver`, require `wrapperClass`

### Test Compatibility

The existing test `ConfigurableLikeCastLengthTest` should be updated to use `wrapperClass`:

```php
protected function createConnection(): Connection
{
    return DriverManager::getConnection([
        'driver' => 'firebird',
        // ... other params
        'wrapperClass' => FirebirdConnection::class,
        'firebird' => [
            'like_cast_length' => 4000,
        ],
    ]);
}
```

## References

- [DBAL UPGRADE.md](https://github.com/doctrine/dbal/blob/4.x/UPGRADE.md) - DBAL 4.x breaking changes
- [DBAL Driver interface](https://github.com/doctrine/dbal/blob/4.x/src/Driver.php) - New interface
- [DBAL Connection class](https://github.com/doctrine/dbal/blob/4.x/src/Connection.php) - How platform is created
- [AbstractMySQLDriver](https://github.com/doctrine/dbal/blob/4.x/src/Driver/AbstractMySQLDriver.php) - Reference implementation

## Implementation Status (2025-12-06)

**STATUS: ✅ IMPLEMENTED**

The recommended solution (Option 1: Custom Wrapper Connection) has been implemented and tested:

### Implemented Files

| File | Description |
|------|-------------|
| `src/DBAL/FirebirdConnection.php` | Wrapper class extending `Doctrine\DBAL\Connection` |
| `tests/Test/Functional/FirebirdConnectionTest.php` | 4 tests covering all scenarios |

### Documentation Updated

- **README.md**: Added "Recommended: Using FirebirdConnection Wrapper" section
- **README.md**: Marked legacy configuration as deprecated
- `ConfigurableLikeCastLengthTest.php`: Marked as testing legacy approach with `@deprecated`

### Test Results

```
FirebirdConnectionTest
 ✓ testFirebirdConnectionConfiguresPlatformWithCustomLikeCastLength
 ✓ testFirebirdConnectionWithDefaultConfiguration
 ✓ testFirebirdConnectionIsInstanceOfDbalConnection
 ✓ testGetDatabasePlatformCalledMultipleTimesReturnsSamePlatform

4 tests, 4 assertions, 0 failures
```

### Migration Path

For users migrating from legacy to recommended approach:

**Before (Legacy - DEPRECATED):**
```php
$connection = DriverManager::getConnection([
    'driver_class' => Driver::class,
    'firebird' => ['like_cast_length' => 500],
]);
```

**After (Recommended - DBAL 4.x Compatible):**
```php
$connection = DriverManager::getConnection([
    'driver_class' => Driver::class,
    'wrapperClass' => FirebirdConnection::class,  // ADD THIS
    'firebird' => ['like_cast_length' => 500],
]);
```

## Summary

The `VersionAwarePlatformDriver` deprecation affects our ability to pass custom configuration from connection params to the platform. The recommended solution is to create a custom `FirebirdConnection` wrapper class that configures the platform after it's created by the driver. This approach is forward-compatible with DBAL 4.x and doesn't rely on deprecated interfaces.
