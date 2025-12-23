# TIME Type Conversion Failure Research

**Date**: 2025-12-22
**Issue**: `TypeConversionTest::testIdempotentConversionToDateTime` with "time" data set fails
**Environment**: php-firebird v7.0.0-rc.2, PHP 8.1.33, Firebird 3.0 server

## Observed Behavior

- **Expected**: `1970-01-01T10:10:10.000000+0000`
- **Actual**: `1970-01-01T22:56:29.000000+0000`
- **Difference**: 12 hours, 46 minutes, 19 seconds (45,979 seconds)

## ROOT CAUSE IDENTIFIED

### Critical Discovery: CI vs Local Environment Difference

**GitHub CI (all green):**
- Uses **php-firebird v6.2.0** (`--with-interbase`)
- Extension: `interbase.so`
- API: Legacy Firebird C API (`isc_encode_sql_time()`, `isc_decode_sql_time()`)

**Local Docker (1 failure):**
- Uses **php-firebird v7.0.0-rc.2** (`--with-firebird`)
- Extension: `firebird.so`
- API: Firebird 3.0+ OO API (`IUtil::encodeTime()`, `IUtil::decodeTime()`)

### Evidence

From commit cdcd78805c7652dfcd968e311d1114876c13388b (GitHub CI workflow):
```bash
git clone --depth 1 --branch v6.2.0 https://github.com/satwareAG/php-firebird.git
./configure --with-interbase=/opt/firebird
echo "extension=interbase.so" > php.ini
```

From `tests/app/Dockerfile` (local Docker):
```bash
git clone --branch v7.0.0-rc.2 --depth 1 https://github.com/satwareAG/php-firebird.git
docker-php-ext-install firebird
# Produces firebird.so (fbird_* functions)
```

## Code Comparison: Legacy vs OO API

### TIME Encoding

**v6.2.0 (WORKING - Legacy API):**
```c
// ibase_query_exec.c
case SQL_TYPE_TIME:
    strptime(Z_STRVAL_P(b_var), format, &t);  // Parse string to struct tm
    isc_encode_sql_time(&t, &buf[i].val.tmval);  // Legacy C API
```

**v7.0.0-rc.2 (BROKEN - OO API):**
```c
// fbird_query_bind.c
case SQL_TYPE_TIME:
    fbird_parse_time(Z_STRVAL_P(b_var), &dt);  // Parse string to fbird_datetime_components
    buf[i].val.tmval = fbu_encode_time(IBG(master_instance),
        dt.hours, dt.minutes, dt.seconds, dt.fractions);  // OO API wrapper
```

### TIME Decoding

**v6.2.0 (WORKING - Legacy API):**
```c
// ibase_result.c
case SQL_TYPE_TIME:
    isc_decode_sql_time((ISC_TIME *) data, &t);  // Fills struct tm directly
```

**v7.0.0-rc.2 (BROKEN - OO API):**
```c
// fbird_result.c
case SQL_TYPE_TIME:
    fbu_decode_time(IBG(master_instance), *(ISC_TIME *) data, 
        &t_hours, &t_minutes, &t_seconds, &t_fractions);  // OO API wrapper
    t.tm_hour = (int)t_hours;
    t.tm_min = (int)t_minutes;
    t.tm_sec = (int)t_seconds;
```

### OO API Wrapper Implementation (firebird_utils.cpp)

```cpp
extern "C" ISC_TIME fbu_encode_time(void *master_ptr, unsigned hours, 
    unsigned minutes, unsigned seconds, unsigned fractions)
{
    TimeComponents components{hours, minutes, seconds, fractions};
    auto result = encode_time_impl(master_ptr, components);
    return result.value_or(0);  // Returns 0 on failure!
}

std::optional<ISC_TIME> encode_time_impl(void* master_ptr, 
    const TimeComponents& components) noexcept 
{
    FirebirdMasterWrapper master(master_ptr);
    auto* util = master.getUtil();
    return util->encodeTime(components.hours, components.minutes,
                          components.seconds, components.fractions);
}
```

## Bug Location Hypothesis

The bug is in php-firebird v7.0.0-rc.2's OO API TIME handling. Possible issues:

### Hypothesis A: `IBG(master_instance)` is NULL
- If master instance isn't properly initialized, `fbu_encode_time()` returns 0
- This would result in 00:00:00, not 22:56:29

### Hypothesis B: `IUtil::encodeTime()` Behavior Change
- Firebird 4.0 headers may have different `IUtil` behavior than Firebird 3.0
- Extension compiled against FB3 headers but may behave differently

### Hypothesis C: Parsing Difference (`strptime` vs `fbird_parse_time`)
- `strptime()` is well-tested C standard function
- `fbird_parse_time()` is new custom implementation
- Parsing "10:10:10" might produce wrong `fbird_datetime_components`

### Hypothesis D: Value Storage/Transmission Issue
- The OO API uses different message buffer format than legacy XSQLDA
- `_php_fbird_xsqlda_to_msg_buffer()` might have a bug for TIME type

## Time Difference Analysis

```
10:10:10 = 36,610 seconds from midnight
22:56:29 = 82,589 seconds from midnight
Difference = 45,979 seconds = 12h 46m 19s

ISC_TIME units = deci-milliseconds (10,000 per second)
10:10:10 ISC_TIME = 366,100,000
22:56:29 ISC_TIME = 825,890,000

Ratio: 825,890,000 / 366,100,000 ≈ 2.256
```

**NOT a timezone offset** (no timezone is 12h 46m 19s).
The specific offset suggests a calculation/conversion bug, not a format issue.

## Recommended Next Steps

### 1. File Issue on php-firebird Repository
Create issue at https://github.com/satwareAG/php-firebird/issues with:
- Reproduction steps using Doctrine test
- Code comparison (v6.2 vs v7.0)
- Analysis of the OO API TIME handling

### 2. Create Minimal Reproduction Test
```php
<?php
// Test TIME encoding/decoding without Doctrine
$db = fbird_connect('localhost:test.fdb', 'SYSDBA', 'masterkey');
fbird_query($db, 'CREATE TABLE time_test (t TIME)');
$stmt = fbird_prepare($db, 'INSERT INTO time_test VALUES (?)');
fbird_execute($stmt, ['10:10:10']);
$result = fbird_query($db, 'SELECT * FROM time_test');
$row = fbird_fetch_object($result);
echo "Stored: 10:10:10, Retrieved: {$row->T}\n";
```

### 3. Debug php-firebird Extension
Add debug logging to:
- `fbird_parse_time()` - verify parsed components
- `fbu_encode_time()` - verify ISC_TIME value
- `fbu_decode_time()` - verify decoded components

### 4. Compare with php-firebird Extension Tests
The php-firebird repo has TIME tests (`tests/time_003.phpt`) that pass.
Identify difference between their test and Doctrine's test.

### 5. Temporary Workaround
Until fixed in php-firebird, use v6.2.0 in Docker:
```dockerfile
RUN git clone --depth 1 --branch v6.2.0 \
        https://github.com/satwareAG/php-firebird.git \
        /usr/src/php/ext/interbase \
    && cd /usr/src/php/ext/interbase \
    && phpize \
    && ./configure --with-interbase \
    && make -j"$(nproc)" \
    && make install
```

## Reference Links

- GitHub Actions Run (all green, v6.2.0): https://github.com/satwareAG/doctrine-firebird-driver/actions/runs/20042684117
- Working commit: cdcd78805c7652dfcd968e311d1114876c13388b
- php-firebird repository: https://github.com/satwareAG/php-firebird
- php-firebird v6.2.0: https://github.com/satwareAG/php-firebird/tree/v6.2.0
- php-firebird v7.0.0-rc.2: https://github.com/satwareAG/php-firebird/tree/v7.0.0-rc.2

## Files Referenced

### doctrine-firebird-driver
- `tests/Test/Functional/TypeConversionTest.php` (line 100)
- `tests/docker-compose.yml`
- `tests/app/Dockerfile`

### php-firebird (v6.2.0 - WORKING)
- `ibase_query_exec.c` - Legacy TIME encoding with `isc_encode_sql_time()`
- `ibase_result.c` - Legacy TIME decoding with `isc_decode_sql_time()`

### php-firebird (v7.0.0-rc.2 - BROKEN)
- `fbird_query_bind.c` - OO API TIME encoding with `fbu_encode_time()`
- `fbird_result.c` - OO API TIME decoding with `fbu_decode_time()`
- `firebird_utils.cpp` - OO API wrapper functions

## Conclusion

**The bug is in php-firebird v7.0.0-rc.2**, NOT in doctrine-firebird-driver or Firebird itself.

The refactoring from legacy C API (`isc_*` functions) to the new OO API (`IUtil::*` functions)
introduced a regression in TIME type handling. The issue needs to be fixed in the php-firebird
extension before v7.0.0 final can be used with this driver.

**Immediate action**: ~~File issue on php-firebird repository with this analysis.~~

✅ **Issue filed**: https://github.com/satwareAG/php-firebird/issues/21
