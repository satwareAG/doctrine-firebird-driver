<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional\SQL;

use Doctrine\DBAL\SQL\Parser;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Driver\ConvertParameters;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

/**
 * Functional SQL parser tests — verifies tokenization correctness on Firebird DDL/DML.
 *
 * Gap analysis §1.3 — mirrors DBAL 3.10.x Functional/SQL/ParserTest.php.
 * Closes #75
 */
class ParserTest extends FunctionalTestCase
{
    /**
     * Basic SELECT is parsed without error and executes against Firebird.
     */
    public function testParseSimpleSelect(): void
    {
        $sql    = 'SELECT 1 FROM RDB$DATABASE';
        $parser = new Parser(false);
        $visitor = new ConvertParameters();

        $parser->parse($sql, $visitor);

        self::assertSame($sql, $visitor->getSQL());
        self::assertSame([], $visitor->getParameterMap());

        // Verify it actually executes on the live connection
        $result = $this->connection->executeQuery($visitor->getSQL());
        self::assertNotFalse($result->fetchOne());
    }

    /**
     * Named parameters `:name` are converted to positional `?` placeholders.
     */
    public function testParseNamedParameters(): void
    {
        $inputSQL    = 'SELECT 1 FROM RDB$DATABASE WHERE 1 = :param1 AND 2 = :param2';
        $expectedSQL = 'SELECT 1 FROM RDB$DATABASE WHERE 1 = ? AND 2 = ?';

        $parser  = new Parser(false);
        $visitor = new ConvertParameters();

        $parser->parse($inputSQL, $visitor);

        self::assertSame($expectedSQL, $visitor->getSQL());
        self::assertSame([1 => ':param1', 2 => ':param2'], $visitor->getParameterMap());
    }

    /**
     * Named parameters inside string literals are NOT converted (they are literal text).
     */
    public function testParseNamedParametersIgnoresStringLiterals(): void
    {
        $inputSQL    = "SELECT ':not_a_param' FROM RDB\$DATABASE WHERE id = :real_param";
        $expectedSQL = "SELECT ':not_a_param' FROM RDB\$DATABASE WHERE id = ?";

        $parser  = new Parser(false);
        $visitor = new ConvertParameters();

        $parser->parse($inputSQL, $visitor);

        self::assertSame($expectedSQL, $visitor->getSQL());
        self::assertSame([1 => ':real_param'], $visitor->getParameterMap());
    }

    /**
     * Firebird-specific DDL: CREATE SEQUENCE (GENERATOR) parses without error.
     * EXECUTE BLOCK syntax parses without error.
     */
    public function testParseFirebirdSpecificDDL(): void
    {
        // CREATE SEQUENCE is Firebird DDL — parser must not choke on it
        $createSeq = 'CREATE SEQUENCE test_seq_parser_75';
        $parser    = new Parser(false);
        $visitor   = new ConvertParameters();

        $parser->parse($createSeq, $visitor);
        self::assertSame($createSeq, $visitor->getSQL());

        // EXECUTE BLOCK is Firebird-specific procedural SQL
        $execBlock = 'EXECUTE BLOCK AS BEGIN SUSPEND; END';
        $parser2   = new Parser(false);
        $visitor2  = new ConvertParameters();

        $parser2->parse($execBlock, $visitor2);
        self::assertSame($execBlock, $visitor2->getSQL());
    }

    /**
     * INSERT with named parameters converts correctly.
     */
    public function testParseInsertWithNamedParameters(): void
    {
        $inputSQL    = 'INSERT INTO some_table (col1, col2) VALUES (:val1, :val2)';
        $expectedSQL = 'INSERT INTO some_table (col1, col2) VALUES (?, ?)';

        $parser  = new Parser(false);
        $visitor = new ConvertParameters();

        $parser->parse($inputSQL, $visitor);

        self::assertSame($expectedSQL, $visitor->getSQL());
        self::assertSame([1 => ':val1', 2 => ':val2'], $visitor->getParameterMap());
    }
}
