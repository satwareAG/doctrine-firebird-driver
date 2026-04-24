#!/usr/bin/env python3
"""Fix DBAL4 compatibility issues in FirebirdPlatformSQLTest.php"""
import re

with open('tests/Test/Unit/Platforms/FirebirdPlatformSQLTest.php', 'r') as f:
    c = f.read()

# 1. ForeignKeyConstraint null name → ''
c = c.replace(
    "            ['foo'],\n            'foreign_table',\n            ['bar'],\n            null,\n            $options,",
    "            ['foo'],\n            'foreign_table',\n            ['bar'],\n            '',\n            $options,"
)

# 2. getIndexDeclarationSQL - remove first string arg (line 463)
c = c.replace(
    "        $actuals[] = $this->_platform->getIndexDeclarationSQL('name', $indexDef);",
    "        $actuals[] = $this->_platform->getIndexDeclarationSQL($indexDef);"
)

# 3. getUniqueConstraintDeclarationSQL - remove first string arg (line 464)
c = c.replace(
    "        $actuals[] = $this->_platform->getUniqueConstraintDeclarationSQL('name', $uniqueIndex);",
    "        $actuals[] = $this->_platform->getUniqueConstraintDeclarationSQL($uniqueIndex);"
)

# 4. testQuotesReservedKeywordInUniqueConstraintDeclarationSQL (line 713)
c = c.replace(
    "        $found = $this->_platform->getUniqueConstraintDeclarationSQL('select', $index);\n        self::assertSame('CONSTRAINT \"select\" UNIQUE (foo)', $found);",
    "        $found = $this->_platform->getUniqueConstraintDeclarationSQL($index);\n        self::assertSame('CONSTRAINT \"select\" UNIQUE (foo)', $found);"
)

# 5. testQuotesReservedKeywordInIndexDeclarationSQL (line 721)
c = c.replace(
    "        $found = $this->_platform->getIndexDeclarationSQL('select', $index);\n        self::assertSame('INDEX \"select\" (foo)', $found);",
    "        $found = $this->_platform->getIndexDeclarationSQL($index);\n        self::assertSame('INDEX \"select\" (foo)', $found);"
)

# 6. addForeignKeyConstraint with Table objects -> use getName() (lines 666-696 area)
# Three $foreignTable Table objects passed - replace each addForeignKeyConstraint call
c = c.replace(
    "        $table->addForeignKeyConstraint(\n            $foreignTable,\n            ['create', 'foo', '`bar`'],\n            ['create', 'bar', '`foo-bar`'],\n            [],\n            'FK_WITH_RESERVED_KEYWORD',\n        );",
    "        $table->addForeignKeyConstraint(\n            'foreign',\n            ['create', 'foo', '`bar`'],\n            ['create', 'bar', '`foo-bar`'],\n            [],\n            'FK_WITH_RESERVED_KEYWORD',\n        );"
)
c = c.replace(
    "        $table->addForeignKeyConstraint(\n            $foreignTable,\n            ['create', 'foo', '`bar`'],\n            ['create', 'bar', '`foo-bar`'],\n            [],\n            'FK_WITH_NON_RESERVED_KEYWORD',\n        );",
    "        $table->addForeignKeyConstraint(\n            'foo',\n            ['create', 'foo', '`bar`'],\n            ['create', 'bar', '`foo-bar`'],\n            [],\n            'FK_WITH_NON_RESERVED_KEYWORD',\n        );"
)
c = c.replace(
    "        $table->addForeignKeyConstraint(\n            $foreignTable,\n            ['create', 'foo', '`bar`'],\n            ['create', 'bar', '`foo-bar`'],\n            [],\n            'FK_WITH_INTENDED_QUOTATION',\n        );",
    "        $table->addForeignKeyConstraint(\n            '`foo-bar`',\n            ['create', 'foo', '`bar`'],\n            ['create', 'bar', '`foo-bar`'],\n            [],\n            'FK_WITH_INTENDED_QUOTATION',\n        );"
)

# 7. testGeneratesAlterTableRenameIndexUsedByForeignKeySQL - Table object to string
c = c.replace(
    "        $primaryTable->addForeignKeyConstraint($foreignTable, ['foo'], ['id'], [], 'fk_foo');",
    "        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['foo'], ['id'], [], 'fk_foo');"
)
c = c.replace(
    "        $primaryTable->addForeignKeyConstraint($foreignTable, ['bar'], ['id'], [], 'fk_bar');",
    "        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['bar'], ['id'], [], 'fk_bar');"
)

# 8. testGeneratesConstraintCreationSql - getCreatePrimaryKeySQL doesn't include name in DBAL4
c = c.replace(
    "        $pk    = new Index('constraint_name', ['test'], true, true);\n        $found = $this->_platform->getCreatePrimaryKeySQL($pk, 'test');\n        self::assertStringContainsString('constraint_name', $found);",
    "        $pk    = new Index('constraint_name', ['test'], true, true);\n        $found = $this->_platform->getCreatePrimaryKeySQL($pk, 'test');\n        self::assertStringContainsString('PRIMARY KEY', $found);"
)

# 9. testGeneratesTableAlterationSqlThrowsException - Exception::class → \RuntimeException::class
c = c.replace(
    "        $this->expectException(Exception::class);\n        \n        // FirebirdPlatform explicitly overrides getAlterTableSQL and currently ignores newName,\n        // but getRenameTableSQL explicitly throws the exception we want to verify.\n        // Verifying the platform capability directly.\n        $this->_platform->getRenameTableSQL('old', 'new');",
    "        $this->expectException(\\RuntimeException::class);\n        \n        // FirebirdPlatform explicitly overrides getAlterTableSQL and currently ignores newName,\n        // but getRenameTableSQL explicitly throws the exception we want to verify.\n        // Verifying the platform capability directly.\n        $this->_platform->getRenameTableSQL('old', 'new');"
)

# 10. testAlterTableNotNULL - count 7 -> 8, add missing assertions
c = c.replace(
    "        $found = $this->_platform->getAlterTableSQL($tableDiff);\n        self::assertCount(7, $found);",
    "        $found = $this->_platform->getAlterTableSQL($tableDiff);\n        self::assertCount(8, $found);"
)

# Also update the assertions for [2] and [7] which are new
old_block = """        self::assertArrayHasKey(4, $found);
        self::assertArrayHasKey(6, $found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('ALTER TABLE mytable ALTER bar SET NOT NULL', $found[4]);
            self::assertSame('ALTER TABLE mytable ALTER metar DROP NOT NULL', $found[6]);
        } else {
            self::assertSame("UPDATE RDB\\$RELATION_FIELDS SET RDB\\$NULL_FLAG = 1 WHERE UPPER(RDB\\$FIELD_NAME) = UPPER('bar') AND UPPER(RDB\\$RELATION_NAME) = UPPER('mytable')", $found[4]);
            self::assertSame("UPDATE RDB\\$RELATION_FIELDS SET RDB\\$NULL_FLAG = NULL WHERE UPPER(RDB\\$FIELD_NAME) = UPPER('metar') AND UPPER(RDB\\$RELATION_NAME) = UPPER('mytable')", $found[6]);
        }"""
new_block = """        self::assertArrayHasKey(2, $found);
        self::assertArrayHasKey(4, $found);
        self::assertArrayHasKey(6, $found);
        self::assertArrayHasKey(7, $found);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertSame('ALTER TABLE mytable ALTER foo SET NOT NULL', $found[2]);
            self::assertSame('ALTER TABLE mytable ALTER bar SET NOT NULL', $found[4]);
            self::assertSame('ALTER TABLE mytable ALTER metar DROP NOT NULL', $found[6]);
        } else {
            self::assertSame("UPDATE RDB\\$RELATION_FIELDS SET RDB\\$NULL_FLAG = 1 WHERE UPPER(RDB\\$FIELD_NAME) = UPPER('foo') AND UPPER(RDB\\$RELATION_NAME) = UPPER('mytable')", $found[2]);
            self::assertSame("UPDATE RDB\\$RELATION_FIELDS SET RDB\\$NULL_FLAG = 1 WHERE UPPER(RDB\\$FIELD_NAME) = UPPER('bar') AND UPPER(RDB\\$RELATION_NAME) = UPPER('mytable')", $found[4]);
            self::assertSame("UPDATE RDB\\$RELATION_FIELDS SET RDB\\$NULL_FLAG = NULL WHERE UPPER(RDB\\$FIELD_NAME) = UPPER('metar') AND UPPER(RDB\\$RELATION_NAME) = UPPER('mytable')", $found[6]);
        }
        self::assertSame('ALTER TABLE mytable ALTER metar TYPE VARCHAR(255)', $found[7]);"""
c = c.replace(old_block, new_block)

# 11. testQuotesTableIdentifiersInAlterTableSQL - FK order swapped in DBAL4
c = c.replace(
    "        self::assertSame('ALTER TABLE \"foo\" DROP CONSTRAINT fk1', $found[0]);\n        self::assertArrayHasKey(1, $found);\n        self::assertSame('ALTER TABLE \"foo\" DROP CONSTRAINT fk2', $found[1]);",
    "        self::assertSame('ALTER TABLE \"foo\" DROP CONSTRAINT fk2', $found[0]);\n        self::assertArrayHasKey(1, $found);\n        self::assertSame('ALTER TABLE \"foo\" DROP CONSTRAINT fk1', $found[1]);"
)

with open('tests/Test/Unit/Platforms/FirebirdPlatformSQLTest.php', 'w') as f:
    f.write(c)

print("All test fixes applied")
