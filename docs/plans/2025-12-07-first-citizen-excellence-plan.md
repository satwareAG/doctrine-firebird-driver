# First Citizen Excellence Plan: Doctrine Firebird Driver

**Date**: 2025-12-07  
**Status**: Active Development Plan  
**Primary Goal**: Perfect compatibility with Firebird 3 + PHP 8.1  
**Strategic Vision**: "First Citizen" status in Doctrine ecosystem

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Research Findings](#research-findings)
3. [Version Compatibility Matrix](#version-compatibility-matrix)
4. [Polyfill Strategy](#polyfill-strategy)
5. [DBAL 3.10.x Feature Utilization](#dbal-310x-feature-utilization)
6. [ORM Integration Requirements](#orm-integration-requirements)
7. [Platform Optimization Strategy](#platform-optimization-strategy)
8. [PHP Firebird Extension Improvements](#php-firebird-extension-improvements)
9. [Implementation Roadmap](#implementation-roadmap)
10. [Success Metrics](#success-metrics)

---

## Executive Summary

### Mission Statement

Transform the Doctrine Firebird Driver from a "mature third-party driver" to a "first citizen" driver that:

1. **Exceeds code coverage** of all DBAL core drivers
2. **Maximizes PHP 8.1 features** while enabling forward compatibility via polyfills
3. **Fully exploits DBAL 3.10.x** capabilities
4. **Supports advanced ORM features** in latest compatible branches
5. **Optimizes for cross-platform** (Linux primary, Windows secondary, macOS M-series)

### Current State Assessment

| Metric | Current | Target | Gap |
|--------|---------|--------|-----|
| Tests | **1415+** | 1500+ | +85 remaining |
| Coverage | **~88%** | 95%+ | +7% |
| PHPStan Level | 8 | 8 (strict-rules) | ✅ |
| PHP Version | 8.1 baseline | 8.1 with polyfills | Needs polyfills |
| DBAL Version | ^3.10 | 3.10.x full features | Audit needed |
| ORM Version | ^3.5 | Compatible with ORM 2.x/3.x | ✅ |

### Phase 2 Test Improvements (2025-12-08) - COMPLETE ✅

| Class | Tests Added | Coverage Impact |
|-------|-------------|-----------------|
| Statement | +66 tests | 62.50% → ~85%+ |
| Connection | +44 tests | 63.89% → ~75%+ |
| ExceptionConverter | +36 tests | 66.67% → ~100% |
| Firebird4Platform | +9 tests | 0% → ~100% |
| Firebird5Platform | +7 tests | 0% → ~100% |
| **Total** | **+162 tests** | **Phase 2 Complete** |

---

## Research Findings

### PHP Polyfills Analysis (Symfony/Polyfill)

**PHP 8.2 Features Available via Polyfill:**
- `AllowDynamicProperties` attribute
- `SensitiveParameter` attribute
- `odbc_connection_string_quote()` function
- `ini_parse_quantity()` function

**PHP 8.3 Features Available via Polyfill:**
- `json_validate()` - validate JSON without decoding (performance)
- `#[\Override]` attribute - explicitly mark method overrides
- `mb_str_pad()` - multibyte-safe string padding
- `str_increment()` / `str_decrement()` - alphanumeric string operations
- `ldap_exop_sync()`, `stream_context_set_options()`

**PHP 8.4 Features Available via Polyfill:**
- `array_find()` / `array_find_key()` - find elements matching predicate
- `array_any()` / `array_all()` - check if any/all elements match condition
- `Deprecated` attribute - formal deprecation marking
- `grapheme_str_split()` - proper grapheme cluster splitting
- `bcdivmod()` - BC math division with remainder

**PHP 8.5 Features Available via Polyfill:**
- `get_error_handler()` / `get_exception_handler()` - retrieve handlers
- `array_first()` / `array_last()` - get first/last array elements
- `NoDiscard` attribute - mark functions whose return shouldn't be ignored

**Features NOT Polyfillable (Require Native PHP):**
- Union types (`int|string`)
- Match expressions
- Readonly properties/classes
- Constructor property promotion
- Named arguments
- Enums (syntax, not `enum_exists()`)
- Fibers/coroutines
- Weak maps

### DBAL 3.10.x Key Interfaces

**Mandatory Interface Implementation:**

| Interface | Purpose | Firebird Status |
|-----------|---------|-----------------|
| `Driver\Connection` | Core connection | ✅ Implemented |
| `ServerVersionProvider` | Provides `getServerVersion()` | ✅ Implemented |
| `Result` | Query results (replaces `ResultStatement`) | ✅ Implemented |
| `Statement` | Prepared statements | ✅ Implemented |
| `Driver\Exception` | Driver-level exceptions | ✅ Implemented |

**Removed Interfaces (Must NOT Use):**
- `ServerInfoAwareConnection` → Merged into `Connection`
- `VersionAwarePlatformDriver` → Use `ServerVersionProvider` argument in `getDatabasePlatform()`
- `ResultStatement` → Renamed to `Result`

**DBAL 3.10.x Changes:**
- `doctrine/cache` is now OPTIONAL dependency
- PDO subclasses supported on PHP 8.4
- `Configuration::setResultCache()` replaces `setResultCacheImpl()`

### ORM Compatibility Matrix

**Doctrine ORM 2.x/3.x Requirements for Drivers:**

| Feature | DBAL Requirement | Firebird Support |
|---------|------------------|------------------|
| Identity Generators | `lastInsertId()` or RETURNING | ✅ RETURNING clause |
| Sequence Support | `supportsSequences()` | ✅ Via generators |
| Second-Level Cache | PSR-6 cache | ✅ symfony/cache |
| Lazy Loading | Efficient fetch | ✅ Standard |
| Eager Loading | JOIN support | ✅ Full SQL support |
| Proxy Objects | Result hydration | ✅ Standard |

**Platform Methods ORM Calls:**
- `supportsSequences()` - ✅ Firebird has generators
- `supportsIdentityColumns()` - ✅ Via IDENTITY (FB3+)
- `supportsUnsignedInteger()` - ❌ Firebird doesn't support
- `supportsSchemas()` - ❌ Firebird has different model

---

## Version Compatibility Matrix

### PHP Version Support

| PHP Version | Status | Polyfill Required | Notes |
|-------------|--------|-------------------|-------|
| 8.1 | ✅ Primary baseline | None | EOL Nov 2025, plan migration |
| 8.2 | ✅ Supported | `symfony/polyfill-php82` | For attributes |
| 8.3 | ✅ Supported | `symfony/polyfill-php83` | `#[\Override]`, `json_validate()` |
| 8.4 | ✅ Supported | `symfony/polyfill-php84` | Array functions |
| 8.5 | 🔜 Future | `symfony/polyfill-php85` | When stable |

### Firebird Version Support

| Firebird Version | PHP Extension | Status | Key Features |
|------------------|---------------|--------|--------------|
| 2.5 | ext-interbase | ⚠️ Legacy | Classic only |
| 3.x | ext-interbase | ✅ Primary Target | IDENTITY, BOOLEAN native |
| 4.x | ext-interbase | ✅ Supported | INT128, Time zones |
| 5.x | ext-interbase | ✅ Supported | TIMESTAMP WITH TIME ZONE |
| 6.x | ext-interbase | 🔜 Future | Parallel queries |

### DBAL/ORM Version Support

| Package | Version | PHP Requirement | Status |
|---------|---------|-----------------|--------|
| doctrine/dbal | ^3.10 | ^8.1 | ✅ Current |
| doctrine/dbal | ^4.0 | ^8.2 | 🔜 Future consideration |
| doctrine/orm | ^3.5 | ^8.1 | ✅ Current |
| doctrine/orm | ^3.x | ^8.1 | ✅ Compatible |

---

## Polyfill Strategy

### Recommended composer.json Additions

```json
{
    "require": {
        "php": "^8.1",
        "symfony/polyfill-php82": "^1.31",
        "symfony/polyfill-php83": "^1.31",
        "symfony/polyfill-php84": "^1.31"
    },
    "suggest": {
        "symfony/polyfill-php85": "For PHP 8.5 features on PHP 8.1-8.4"
    }
}
```

### Phased Polyfill Adoption

**Phase 1 (Immediate): PHP 8.3 Features**

```php
// Add #[\Override] to all interface implementations
#[\Override]
public function prepare(string $sql): Statement
{
    // Polyfill provides attribute on PHP 8.1/8.2
}

// Use json_validate() for JSON column validation
if (function_exists('json_validate') && !json_validate($data)) {
    throw new InvalidArgumentException('Invalid JSON');
}
```

**Phase 2 (Short-term): PHP 8.4 Array Functions**

```php
// Replace manual loops with array_find()
$match = array_find($columns, fn($col) => $col->getName() === $name);

// Replace foreach + break with array_any()
$hasAutoIncrement = array_any($columns, fn($col) => $col->getAutoincrement());
```

**Phase 3 (Future): PHP 8.5 Features**

```php
// Use array_first/array_last for cleaner code
$firstColumn = array_first($columns);
$lastColumn = array_last($columns);
```

### Compatibility Layer Pattern

```php
namespace Satag\DoctrineFirebirdDriver\Compat;

/**
 * Compatibility utilities for cross-version PHP support
 */
final class Compat
{
    /**
     * Polyfill-aware JSON validation
     */
    public static function jsonValidate(string $json): bool
    {
        if (function_exists('json_validate')) {
            return json_validate($json);
        }
        
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Array find with PHP 8.1 fallback
     */
    public static function arrayFind(array $array, callable $callback): mixed
    {
        if (function_exists('array_find')) {
            return array_find($array, $callback);
        }
        
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }
}
```

---

## DBAL 3.10.x Feature Utilization

### Currently Utilized Features

| Feature | Implementation | Status |
|---------|---------------|--------|
| Result interface | `Firebird\Result` | ✅ |
| Statement interface | `Firebird\Statement` | ✅ |
| Connection interface | `Firebird\Connection` | ✅ |
| ServerVersionProvider | Via `getServerVersion()` | ✅ |
| VersionAwarePlatformDriver | `FirebirdDriver` | ✅ |
| ExceptionConverter | `ExceptionConverter` | ✅ Comprehensive |

### Features to Implement/Improve

| Feature | Current | Target | Priority |
|---------|---------|--------|----------|
| Result caching | Not used | PSR-6 integration | HIGH |
| Batch operations | Single statements | EXECUTE BLOCK | HIGH |
| Statement caching | None | Hash-based cache | MEDIUM |
| Async support | None | Research Firebird 5 | LOW |

### Result Cache Integration

```php
// Enable result caching with PSR-6
use Symfony\Component\Cache\Adapter\ArrayAdapter;

$cache = new ArrayAdapter();
$config = new Configuration();
$config->setResultCache($cache);

// Queries with TTL
$result = $connection->executeQuery(
    'SELECT * FROM users WHERE active = 1',
    [],
    [],
    new QueryCacheProfile(3600, 'active_users')
);
```

---

## ORM Integration Requirements

### Identity Generation Strategy

**Current Implementation:**

```php
// FirebirdPlatform.php
public function getIdentityColumnDefinition(): string
{
    return 'GENERATED BY DEFAULT AS IDENTITY';
}

// For Firebird 3+
public function supportsIdentityColumns(): bool
{
    return true;
}
```

**ORM Entity Configuration:**

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
}
```

### Sequence/Generator Support

**Firebird uses GENERATORS (sequences):**

```php
// Platform support
public function supportsSequences(): bool
{
    return true;
}

public function getSequenceNextValSQL(string $sequence): string
{
    return 'SELECT GEN_ID(' . $sequence . ', 1) FROM RDB$DATABASE';
}
```

### Second-Level Cache Configuration

```php
// ORM configuration for second-level cache
$cacheConfig = new CacheConfiguration();
$cacheConfig->setCacheFactory(
    new DefaultCacheFactory(
        new RegionsConfiguration(),
        $cache // PSR-6 cache
    )
);

$cacheConfig->setCacheLogger(new CacheLoggerChain());

$config->setSecondLevelCacheEnabled(true);
$config->setSecondLevelCacheConfiguration($cacheConfig);
```

---

## Platform Optimization Strategy

### Linux (Primary Target)

**Optimizations:**
- Native `ext-interbase` compilation with optimal flags
- Connection pooling via external tools (PgBouncer-style)
- Shared memory for Firebird embedded mode
- Unix sockets for local connections

**CI/CD Testing:**
```yaml
test:linux:
  image: php:8.1-cli
  services:
    - firebird:3
  script:
    - phpunit
```

### Windows (Secondary Target)

**Considerations:**
- Pre-compiled `php_interbase.dll` availability
- Named pipes support for Firebird connections
- Path separator handling in connection strings
- Different lock file behavior

**Testing Strategy:**
```yaml
test:windows:
  os: windows
  script:
    - composer install
    - vendor/bin/phpunit
```

### macOS (M-series ARM64)

**Challenges:**
- Homebrew Firebird availability
- ARM64 native PHP builds
- Rosetta 2 compatibility layer overhead
- Extension compilation on M-series

**Optimization:**
```php
// Connection string for macOS
$params = [
    'host' => 'localhost',
    'port' => 3050,
    'dbname' => '/usr/local/firebird/data/test.fdb',
    'charset' => 'UTF8',
];
```

### Cross-Platform Path Handling

```php
namespace Satag\DoctrineFirebirdDriver\Util;

final class PathNormalizer
{
    public static function normalize(string $path): string
    {
        // Handle Windows paths on Unix
        if (DIRECTORY_SEPARATOR === '/' && str_contains($path, '\\')) {
            $path = str_replace('\\', '/', $path);
        }
        
        return $path;
    }
    
    public static function isRemote(string $path): bool
    {
        // Check for host:path or //host/path patterns
        return preg_match('/^[\w.-]+:(?![\\/])/', $path) ||
               str_starts_with($path, '//');
    }
}
```

---

## PHP Firebird Extension Improvements

### Proposed Changes to satwareAG/php-firebird

Based on the extension improvement analysis, these changes would significantly help:

**Priority 1: Standardized Function Signatures**

```c
// Current: polymorphic arguments
fbird_prepare($link_or_query, $query_or_trans = null, $trans = null)

// Proposed: explicit signatures
fbird_prepare_ex(resource $link, string $query, ?resource $trans = null)
```

**Priority 2: Clean Error Handling**

```c
// Current: E_WARNING on invalid cursor
// Proposed: Return false with error code available via fbird_errcode()

// New function
PHP_FUNCTION(fbird_result_status)
{
    // Returns: FBIRD_RESULT_OK, FBIRD_RESULT_EOF, FBIRD_RESULT_CLOSED
}
```

**Priority 3: Stream Support for BLOBs**

```c
// Enable passing PHP streams directly
fbird_execute($stmt, [$stream1, $stream2]);
// Extension handles chunked writing internally
```

**Priority 4: Exception Mode**

```php
// Enable exceptions instead of warnings
fbird_set_exception_mode(FBIRD_EXCEPTION_MODE_THROW);

try {
    $result = fbird_query($conn, 'INVALID SQL');
} catch (FirebirdException $e) {
    // Clean error handling
}
```

### Extension Enhancement Roadmap

| Enhancement | Effort | Impact | Priority |
|-------------|--------|--------|----------|
| Exception mode | Medium | High | P1 |
| Clean EOF handling | Low | High | P1 |
| Stream BLOB support | High | Medium | P2 |
| Standardized signatures | Medium | Medium | P2 |
| Result status function | Low | Medium | P3 |

---

## Implementation Roadmap

### Phase 1: Foundation Excellence (Week 1-2)

**Goal**: Perfect Firebird 3 + PHP 8.1 compatibility

| Task | Effort | Priority | Status |
|------|--------|----------|--------|
| Add polyfill dependencies | 1h | HIGH | 🔜 |
| Add `#[\Override]` attributes | 2h | HIGH | 🔜 |
| Typed class constants (PHP 8.3) | 2h | MEDIUM | 🔜 |
| Simplify Connection.php | 1d | HIGH | 🔜 |
| Add Compat utility class | 4h | MEDIUM | 🔜 |
| Update PHPStan baseline | 2h | MEDIUM | 🔜 |
| CI matrix: PHP 8.1-8.4 | 4h | HIGH | 🔜 |

### Phase 2: Performance Parity (Week 3-4)

**Goal**: Match/exceed core driver performance features

| Task | Effort | Priority | Status |
|------|--------|----------|--------|
| Statement caching layer | 1d | HIGH | 🔜 |
| EXECUTE BLOCK batch inserts | 2d | HIGH | 🔜 |
| Connection pooling docs | 4h | MEDIUM | 🔜 |
| Performance benchmarks | 1d | MEDIUM | 🔜 |
| Memory profiling tests | 4h | MEDIUM | 🔜 |

### Phase 3: Feature Excellence (Week 5-6)

**Goal**: Comprehensive feature coverage

| Task | Effort | Priority | Status |
|------|--------|----------|--------|
| Result cache integration | 1d | HIGH | 🔜 |
| Streaming result sets | 2d | MEDIUM | 🔜 |
| Concurrency tests | 1d | MEDIUM | 🔜 |
| GDS code error mapping | 1d | MEDIUM | 🔜 |
| StatementType enum | 4h | LOW | 🔜 |

### Phase 4: Ecosystem Integration (Week 7-8)

**Goal**: Community recognition and adoption

| Task | Effort | Priority | Status |
|------|--------|----------|--------|
| Benchmark documentation | 1d | MEDIUM | 🔜 |
| Migration guides | 2d | MEDIUM | 🔜 |
| Symfony bundle | 3d | LOW | 🔜 |
| Laravel driver package | 3d | LOW | 🔜 |
| Doctrine recognition PR | 2d | HIGH | 🔜 |

---

## Success Metrics

### Code Quality Targets

| Metric | Current | Target | Measurement |
|--------|---------|--------|-------------|
| Test count | 1255 | 1500+ | PHPUnit |
| Code coverage | ~85% | 95%+ | phpunit --coverage |
| PHPStan level | 8 | 8 (no baseline) | PHPStan |
| Psalm level | ? | 1 | Psalm |
| Cyclomatic complexity | High | <10 avg | phpmd |

### Performance Targets

| Metric | Current | Target | Measurement |
|--------|---------|--------|-------------|
| Insert 1000 rows | ? | <500ms | Benchmark |
| Select 10000 rows | ? | <200ms | Benchmark |
| Memory per connection | ? | <1MB | memory_get_usage |
| Statement cache hit rate | N/A | >80% | Custom metric |

### Compatibility Targets

| Platform | Current | Target | Testing |
|----------|---------|--------|---------|
| Linux x64 | ✅ | ✅ | CI primary |
| Windows x64 | ⚠️ | ✅ | AppVeyor |
| macOS x64 | ❓ | ✅ | GitHub Actions |
| macOS ARM64 | ❓ | ✅ | GitHub Actions |

### Community Targets

| Metric | Current | Target | Timeline |
|--------|---------|--------|----------|
| GitHub stars | ? | 500+ | 2026 |
| Packagist downloads | ? | 50k/month | 2026 |
| Contributors | 3 | 10+ | 2026 |
| Doctrine recognition | No | Yes | 2026 Q2 |

---

## Appendices

### A. Polyfill Package Quick Reference

```bash
# Install all recommended polyfills
composer require symfony/polyfill-php82 \
                 symfony/polyfill-php83 \
                 symfony/polyfill-php84
```

### B. PHPStan Configuration for Polyfills

```neon
# phpstan.neon.dist
parameters:
    phpVersion: 80100  # PHP 8.1 baseline
    
includes:
    - vendor/phpstan/phpstan-strict-rules/rules.neon
```

### C. CI Matrix Configuration

```yaml
# .github/workflows/ci.yml
jobs:
  test:
    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3', '8.4']
        firebird: ['3', '4', '5']
        os: [ubuntu-latest, windows-latest, macos-latest]
        exclude:
          - os: macos-latest
            firebird: '5'  # Not available on Homebrew
```

### D. References

- [Symfony Polyfills](https://github.com/symfony/polyfill)
- [PHP 8.3 Release Notes](https://www.php.net/releases/8.3/en.php)
- [PHP 8.4 Release Notes](https://www.php.net/releases/8.4/en.php)
- [Doctrine DBAL Documentation](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/)
- [Firebird SQL Reference](https://firebirdsql.org/file/documentation/html/en/refdocs/fblangref50/)
- [satwareAG/php-firebird Extension](https://github.com/satwareAG/php-firebird)

---

*Plan created by Jane Alesi - satware® AI Platform*  
*Last updated: 2025-12-07*
