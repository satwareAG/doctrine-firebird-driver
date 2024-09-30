<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\DBAL\Database\Table;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use Satag\DoctrineFirebirdDriver\Platforms\Firebird3Platform;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;

use function md5;
use function preg_match;
use function strtoupper;
use function substr;

class CreateTest extends AbstractIntegrationTestCase
{
    public function testCreateTable(): void
    {
        $connection = $this->_entityManager->getConnection();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $table      = new Table($tableName);
        $table->addColumn('foo', 'string', ['notnull' => false, 'length' => 255]);
        $statements = $this->_platform->getCreateTableSQL($table);
        self::assertCount(1, $statements);
        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        $sql    = "SELECT 1 FROM RDB\$RELATIONS WHERE RDB\$RELATION_NAME = '{$tableName}'";
        $result = $connection->query($sql);
        self::assertInstanceOf(Result::class, $result);
        self::assertSame(1, $result->fetchOne(), 'Table creation failure. SQL: ' . self::statementArrayToText($statements));
    }

    public function testCreateTableWithPrimaryKey(): void
    {
        $connection = $this->_entityManager->getConnection();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $table      = new Table($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $statements = $this->_platform->getCreateTableSQL($table);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertCount(1, $statements);
        } else {
            self::assertCount(3, $statements);
        }

        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        $sql    = (
            "SELECT 1
            FROM RDB\$INDICES IX
            LEFT JOIN RDB\$INDEX_SEGMENTS SG ON IX.RDB\$INDEX_NAME = SG.RDB\$INDEX_NAME
            LEFT JOIN RDB\$RELATION_CONSTRAINTS RC ON RC.RDB\$INDEX_NAME = IX.RDB\$INDEX_NAME
            WHERE RC.RDB\$CONSTRAINT_TYPE = 'PRIMARY KEY'
            AND RC.RDB\$RELATION_NAME = '{$tableName}'"
        );
        $result = $connection->query($sql);
        self::assertInstanceOf(Result::class, $result);
        self::assertSame(1, $result->fetchOne(), 'Primary key "id" not found');
    }

    public function testCreateTableWithPrimaryKeyAndAutoIncrement(): void
    {
        $connection = $this->_entityManager->getConnection();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $table      = new Table($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $statements = $this->_platform->getCreateTableSQL($table);
        if ($this->_platform instanceof Firebird3Platform) {
            self::assertCount(1, $statements);
        } else {
            self::assertCount(3, $statements);
        }

        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        if ($this->_platform instanceof Firebird3Platform) {
            foreach ([1, 2] as $id) {
                $sql    = 'INSERT INTO ' . $tableName . ' DEFAULT VALUES';
                $result = $connection->executeQuery($sql);

                self::assertInstanceOf(Result::class, $result);
                self::assertSame($id, $connection->lastInsertId(), 'Incorrect autoincrement value');
            }
        } else {
            $triggerName = "{$tableName}_D2IT";
            $sql         = "SELECT 1 FROM RDB\$TRIGGERS WHERE RDB\$TRIGGER_NAME = '{$triggerName}'";
            $result      = $connection->query($sql);
            self::assertInstanceOf(Result::class, $result);
            self::assertSame(1, $result->fetchOne(), 'Trigger creation failure. SQL: ' . self::statementArrayToText($statements));

            $sequenceName = "{$tableName}_D2IS";
            foreach ([1, 2] as $id) {
                $sql    = "SELECT NEXT VALUE FOR {$sequenceName} FROM RDB\$DATABASE;";
                $result = $connection->query($sql);
                self::assertInstanceOf(Result::class, $result);
                self::assertSame($id, $result->fetchOne(), 'Incorrect autoincrement value');
            }
        }
    }

    public function testCreateTableWithIndex(): void
    {
        $connection = $this->_entityManager->getConnection();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $table      = new Table($tableName);
        $table->addColumn('foo', 'integer');
        $table->addIndex(['foo']);
        $statements = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $statements);
        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        preg_match('/^CREATE INDEX (IDX_.+?) /', (string) $statements[1], $match);
        self::assertNotEmpty($match, "Invalid match against \$statements[1]: {$statements[1]}");
        $indexName = $match[1];

        /**
         * Firebird 2.5
         */
        if ($connection->getDatabasePlatform()->getName() === 'Firebird') {
            $sql = (
            "SELECT 1
            FROM RDB\$INDICES IX
            LEFT JOIN RDB\$INDEX_SEGMENTS SG ON IX.RDB\$INDEX_NAME = SG.RDB\$INDEX_NAME
            LEFT JOIN RDB\$RELATION_CONSTRAINTS RC ON RC.RDB\$INDEX_NAME = IX.RDB\$INDEX_NAME
            WHERE IX.RDB\$UNIQUE_FLAG IS NULL
            AND IX.RDB\$INDEX_NAME = '{$indexName}'
            AND IX.RDB\$RELATION_NAME STARTING WITH '{$tableName}'
            AND SG.RDB\$FIELD_NAME = 'FOO'"
            );
        } else {
            /**
             * Firebird 3.0+
             */
            $sql = (
            "SELECT 1
            FROM RDB\$INDICES IX
            LEFT JOIN RDB\$INDEX_SEGMENTS SG ON IX.RDB\$INDEX_NAME = SG.RDB\$INDEX_NAME
            LEFT JOIN RDB\$RELATION_CONSTRAINTS RC ON RC.RDB\$INDEX_NAME = IX.RDB\$INDEX_NAME
            WHERE IX.RDB\$UNIQUE_FLAG = 0
            AND IX.RDB\$INDEX_NAME = '{$indexName}'
            AND IX.RDB\$RELATION_NAME STARTING WITH '{$tableName}'
            AND SG.RDB\$FIELD_NAME = 'FOO'"
            );
        }

        $result = $connection->query($sql);
        self::assertInstanceOf(Result::class, $result);
        self::assertSame(1, $result->fetchOne(), 'Index creation failure. SQL: ' . self::statementArrayToText($statements));
    }

    public function testCreateTableWithUniqueIndex(): void
    {
        $connection = $this->_entityManager->getConnection();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $table      = new Table($tableName);
        $table->addColumn('foo', 'integer');
        $table->addUniqueIndex(['foo']);
        $statements = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $statements);
        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        preg_match('/^CREATE UNIQUE INDEX (UNIQ_.+?) /', (string) $statements[1], $match);
        self::assertNotEmpty($match, "Invalid match against \$statements[1]: {$statements[1]}");
        $indexName = $match[1];

        $sql    = (
            "SELECT 1
            FROM RDB\$INDICES IX
            LEFT JOIN RDB\$INDEX_SEGMENTS SG ON IX.RDB\$INDEX_NAME = SG.RDB\$INDEX_NAME
            LEFT JOIN RDB\$RELATION_CONSTRAINTS RC ON RC.RDB\$INDEX_NAME = IX.RDB\$INDEX_NAME
            WHERE IX.RDB\$UNIQUE_FLAG = 1
            AND IX.RDB\$INDEX_NAME = '{$indexName}'
            AND IX.RDB\$RELATION_NAME STARTING WITH '{$tableName}'
            AND SG.RDB\$FIELD_NAME = 'FOO'"
        );
        $result = $connection->query($sql);
        self::assertInstanceOf(Result::class, $result);
        self::assertSame(1, $result->fetchOne(), 'Unique index creation failure. SQL: ' . self::statementArrayToText($statements));
    }

    public function testCreateTableWithCommentedColumn(): void
    {
        $connection = $this->_entityManager->getConnection();
        $tableName  = strtoupper('TABLE_' . substr(md5(self::class . ':' . __FUNCTION__), 0, 12));
        $table      = new Table($tableName);
        $comment    = 'Lorem ipsum';
        $table->addColumn('foo', 'integer', ['comment' => $comment]);
        $statements = $this->_platform->getCreateTableSQL($table);
        self::assertCount(2, $statements);
        foreach ($statements as $statement) {
            $connection->exec($statement);
        }

        $sql    = (
            "SELECT 1
            FROM RDB\$FIELDS F
            JOIN RDB\$RELATION_FIELDS RF ON RF.RDB\$FIELD_SOURCE = F.RDB\$FIELD_NAME
            WHERE RF.RDB\$RELATION_NAME = '{$tableName}'
            AND RF.RDB\$FIELD_NAME = 'FOO'
            AND RF.RDB\$DESCRIPTION = '{$comment}'"
        );
        $result = $connection->query($sql);
        self::assertInstanceOf(Result::class, $result);
        self::assertSame(1, $result->fetchOne(), 'Comment creation failure. SQL: ' . self::statementArrayToText($statements));
    }
}
