# Plan: Charset Middleware Round-Trip Test Coverage (v3.12.0-RC.3)

**Date:** 2026-03-10
**Branch:** `3.10.x-dev`
**Tag:** `v3.12.0-RC.3`
**Status:** Implemented

## Background

The `CharsetMiddleware` stack was introduced in v3.11.0 and improved in v3.11.0-RC.2 with
BLOB stream support. While source coverage reached 100%, the existing unit tests only verified
encoding with UTF-8 input strings - they did not test with actual Windows-1252 encoded bytes
as Firebird would return them on the wire.

The `satag-amicron-entity-bundle` (spec-004) requires:

- Connection with `charset: ISO8859_1` to Amicron Firebird databases
- Data arriving from DB in Windows-1252 (WIN1252) wire encoding
- Transparent decode for VARCHAR, TEXT, and BLOB TEXT columns
- Transparent encode for WHERE/LIKE search parameters

## Gap Identified

Existing tests verified:
- `CharsetMiddleware` wraps driver correctly
- `CharsetConnectionMiddleware` encodes `quote()` with UTF-8 strings
- `CharsetStatementMiddleware` encodes `bindValue()` with UTF-8 strings
- `CharsetResultMiddleware` decodes UTF-8→UTF-8 (identity, no real transcoding)

Missing:
- Fetch methods tested with real WIN1252 bytes (0x84, 0x94, 0x9C, 0xE4, etc.)
- BLOB TEXT stream decode with WIN1252 content
- Encode/decode round-trip continuity verification
- LIKE/search param encoding with all 7 Amicron special-char strings

## Implementation Plan

### Phase 1 - Audit (complete)

- Read all 4 middleware classes
- Read existing 33 unit tests in `CharsetMiddlewareTest.php`
- Cross-check against amicron spec-004 success criteria

### Phase 2 - Round-trip tests (complete)

Create `tests/Test/Unit/Driver/Middleware/CharsetEncodingRoundTripTest.php` with:

1. `testFetch*WithRealWin1252Bytes` - 6 tests, one per fetch method, with actual `mb_convert_encoding` WIN1252 bytes as mock return values
2. `testFetch*StreamWithWin1252Bytes` - 6 tests, one per fetch method, with `php://temp` streams containing WIN1252 bytes
3. `testRoundTrip*` - 7 tests, one per Amicron special-char string, verifying byte identity after WIN1252↔UTF-8 conversion
4. `testBindValue*Encodes` - 7 tests, one per Amicron string, verifying `bindValue()` encoding
5. `testExecuteParams*Encodes` - 7 tests, verifying inline `execute()` param encoding
6. `testQuoteEncodesSpecialChars` - 7 tests, one per Amicron string, verifying `quote()` encoding

### Phase 3 - Spec and release docs (complete)

- Create `specs/001-charset-transparency-middleware/spec.md`
- Add `[3.12.0-RC.3]` entry to `CHANGELOG.md`
- Fix `README.md` orphaned artifacts
- Update `NEXT_STEPS.md`
- Tag `v3.12.0-RC.3`, push to GitHub
- Update `satag-amicron-entity-bundle/composer.json` to `3.12.0-RC.3`

## Verification

| Check | Result |
|-------|--------|
| `vendor/bin/phpunit tests/Test/Unit/Driver/Middleware/` | 103/103 PASS, 339 assertions |
| PHPStan Level 8 on src/Driver/Firebird/Middleware/ + tests | 0 errors |
| PHPCS on src/Driver/Firebird/Middleware/ | 4/4 PASS |
| No PHPUnit deprecations | 0 deprecations |

## Amicron Bundle Impact

The `satag-amicron-entity-bundle` registers `CharsetMiddleware` from this driver via:

```xml
<!-- src/Resources/config/service.xml -->
<service id="Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware">
    <tag name="doctrine.middleware"/>
</service>
```

With `v3.12.0-RC.3` installed, all connections declared with `charset: ISO8859_1` will have
full WIN1252 transparency — reads, writes, and LIKE searches — verified by 103 unit tests.
