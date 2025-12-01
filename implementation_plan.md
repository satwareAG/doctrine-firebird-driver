# Implementation Plan: Fix DROP TABLE Warning in FunctionalTestCase

[Overview]
Fix the PHP warning triggered when dropping a non-existent table in FunctionalTestCase::dropTableIfExists().

The issue occurs because when `tablesExist()` throws an exception (which can happen in certain transaction states), the code falls through to attempt a DROP TABLE which then fails with "Table does not exist" error. Firebird doesn't support `DROP TABLE IF EXISTS` syntax, so we need to properly handle the case where the table existence check fails.

[Types]
No type changes required.

[Files]
Single file modification required:
- Modify `tests/Test/FunctionalTestCase.php` - Improve error handling in dropTableIfExists() method

[Functions]
Modify `dropTableIfExists()` method in FunctionalTestCase class:
- Add suppression of expected "does not exist" errors during DROP TABLE
- Catch DatabaseObjectNotFoundException from the dropTable call
- Also catch generic exceptions that contain "does not exist" message

[Classes]
No new classes required. Modify existing FunctionalTestCase class.

[Dependencies]
No new dependencies required.

[Testing]
Run the BlobTest to verify the warning no longer appears:
```bash
cd /home/mw/PhpstormProjects/doctrine-firebird-driver
./tests/phpunit.sh --filter BlobTest
```

[Implementation Order]
1. Modify dropTableIfExists() method in FunctionalTestCase.php to properly suppress/handle "table does not exist" errors during DROP TABLE operations
2. Run BlobTest to verify the fix works
3. Run full test suite to ensure no regressions
