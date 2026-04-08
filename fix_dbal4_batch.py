#!/usr/bin/env python3
"""Batch fixes for remaining DBAL4 migration issues."""
import re

def fix_file(path, replacements):
    with open(path, 'r') as f:
        content = f.read()
    for old, new in replacements:
        if old in content:
            content = content.replace(old, new, 1)
            print(f"  Fixed in {path}: {old[:60]!r}")
        else:
            print(f"  WARNING: Pattern not found in {path}: {old[:60]!r}")
    with open(path, 'w') as f:
        f.write(content)

# ──────────────────────────────────────────────
# 1. FirebirdPlatform.php: fix getReservedKeywordsList to use dynamic class
# ──────────────────────────────────────────────
fix_file('src/Platforms/FirebirdPlatform.php', [
    (
        'return new FirebirdKeywords();',
        'return new ($this->getReservedKeywordsClass())();'
    ),
])

# ──────────────────────────────────────────────
# 2. Add getName() to Keywords classes (DBAL4 removed it from AbstractKeywords)
# ──────────────────────────────────────────────
keywords_fixes = [
    ('src/Platforms/Keywords/FirebirdKeywords.php', 'Firebird'),
    ('src/Platforms/Keywords/Firebird3Keywords.php', 'Firebird3'),
    ('src/Platforms/Keywords/Firebird4Keywords.php', 'Firebird4'),
    ('src/Platforms/Keywords/Firebird5Keywords.php', 'Firebird5'),
]

for path, name in keywords_fixes:
    with open(path, 'r') as f:
        content = f.read()
    # Add getName() after the class declaration if not present
    if 'public function getName()' not in content:
        # Insert getName() before getKeywords() or at end of class
        if 'protected function getKeywords()' in content:
            content = content.replace(
                'protected function getKeywords()',
                f'public function getName(): string\n    {{\n        return \'{name}\';\n    }}\n\n    protected function getKeywords()',
                1
            )
        elif 'public function getKeywords()' in content:
            content = content.replace(
                'public function getKeywords()',
                f'public function getName(): string\n    {{\n        return \'{name}\';\n    }}\n\n    public function getKeywords()',
                1
            )
        else:
            # Insert before closing brace
            content = content.rstrip()
            if content.endswith('}'):
                content = content[:-1] + f'\n    public function getName(): string\n    {{\n        return \'{name}\';\n    }}\n}}\n'
        with open(path, 'w') as f:
            f.write(content)
        print(f"  Added getName() to {path}")
    else:
        print(f"  getName() already present in {path}")

# ──────────────────────────────────────────────
# 3. Fix CharsetConnectionMiddleware::quote() - DBAL4 signature is quote(string $value): string
# ──────────────────────────────────────────────
fix_file('src/Driver/Firebird/Middleware/CharsetConnectionMiddleware.php', [
    # If quote() takes mixed $value, change to string|null|int|float and cast internally
    # Check actual signature first via content inspection
])

with open('src/Driver/Firebird/Middleware/CharsetConnectionMiddleware.php', 'r') as f:
    charset_content = f.read()

print(f"\nCharsetConnectionMiddleware quote signature: ", end="")
quote_match = re.search(r'public function quote\([^)]+\)', charset_content)
if quote_match:
    print(quote_match.group(0))
else:
    print("not found")

# ──────────────────────────────────────────────
# 4. Fix SelectSQLBuilderTest: update expectException for NotSupported
# ──────────────────────────────────────────────
fix_file('tests/Test/Unit/Platforms/SelectSQLBuilderTest.php', [
    (
        "expectException(Exception::class);\n",
        "expectException(\\Doctrine\\DBAL\\Platforms\\Exception\\NotSupported::class);\n"
    ),
    (
        "expectException(\\Doctrine\\DBAL\\Exception::class);\n",
        "expectException(\\Doctrine\\DBAL\\Platforms\\Exception\\NotSupported::class);\n"
    ),
])

# ──────────────────────────────────────────────
# 5. Fix FirebirdPlatformCoverageGapTest
# ──────────────────────────────────────────────
with open('tests/Test/Unit/Platforms/FirebirdPlatformCoverageGapTest.php', 'r') as f:
    coverage_content = f.read()

print("\nCoverageGapTest onSchemaAlterTableChangeColumn present:", 'onSchemaAlterTableChangeColumn' in coverage_content)
print("CoverageGapTest getDropTableSQLAcceptsTableObject present:", 'testGetDropTableSQLAcceptsTableObject' in coverage_content)
print("CoverageGapTest ColumnDiff string arg present:", "new ColumnDiff(\n                'bar'" in coverage_content or "ColumnDiff('bar'" in coverage_content)

print("\nAll done. Review output above for any warnings.")
