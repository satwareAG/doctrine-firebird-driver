---
description: DBAL connection wrapper prevents native connection resource validation
tags: [dbal, testing, firebird]
last_updated: 2026-03-05
---

# DBAL Connection Wrapper Validation Bug

## Problem
In DBAL 3.x, `getNativeConnection()` on a DBAL `Connection` wrapper does not return the raw extension resource (like the Firebird link identifier). Instead, it returns the driver-specific Connection implementation (e.g. `Satag\DoctrineFirebirdDriver\Driver\Firebird\Connection`).

When a test runner attempts to check `isConnectionValid()` using this wrapper, it may incorrectly report the connection as valid even if the underlying resource has been closed by a database error.

## Implication for Test Suites
This causes cascade failures in test suites:
1. Test A triggers a database error that closes the connection (e.g. "object is in use")
2. Test harness catches exception and attempts teardown
3. Test harness checks `isConnectionValid()` on the DBAL wrapper, which returns `true` because it doesn't correctly interrogate the raw resource.
4. Test harness reuses the dead connection for Test B
5. Test B fails with "Connection is not valid or has been closed"
6. This repeats for all subsequent tests.

## Solution
When simplifying test suites, you cannot rely solely on the DBAL wrapper for connection validity. You must either:
a) Ensure `isConnectionValid()` deep-inspects the native resource type
b) Provide a deep unwrap utility in the test case to get the raw resource before calling `isConnectionValid()`
c) Use a ping query (`SELECT 1 FROM RDB$DATABASE`) to guarantee validity.
