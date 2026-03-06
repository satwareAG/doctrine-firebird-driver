<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\Firebird3Keywords;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\Firebird4Keywords;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\Firebird5Keywords;

/**
 * Unit tests for Firebird keyword lists.
 */
class KeywordsTest extends TestCase
{
    public function testFirebird4KeywordsGetName(): void
    {
        $keywords = new Firebird4Keywords();
        self::assertSame('Firebird4', $keywords->getName());
    }

    public function testFirebird4KeywordsContainsFirebird4SpecificKeywords(): void
    {
        $keywords = new Firebird4Keywords();
        self::assertTrue($keywords->isKeyword('BINARY'));
        self::assertTrue($keywords->isKeyword('DECFLOAT'));
        self::assertTrue($keywords->isKeyword('INT128'));
        self::assertTrue($keywords->isKeyword('TIMEZONE_HOUR'));
        self::assertTrue($keywords->isKeyword('TIMEZONE_MINUTE'));
        self::assertTrue($keywords->isKeyword('UNBOUNDED'));
        self::assertTrue($keywords->isKeyword('VARBINARY'));
        self::assertTrue($keywords->isKeyword('WINDOW'));
    }

    public function testFirebird4KeywordsExtendsFirebird3(): void
    {
        $keywords = new Firebird4Keywords();
        // Should also include Firebird3 keywords (inherited via parent::getKeywords())
        self::assertTrue($keywords->isKeyword('SELECT'));
        self::assertTrue($keywords->isKeyword('FROM'));
    }

    public function testFirebird5KeywordsGetName(): void
    {
        $keywords = new Firebird5Keywords();
        self::assertSame('Firebird5', $keywords->getName());
    }

    public function testFirebird5KeywordsContainsLateral(): void
    {
        $keywords = new Firebird5Keywords();
        self::assertTrue($keywords->isKeyword('LATERAL'));
    }

    public function testFirebird5KeywordsExtendsFirebird4(): void
    {
        $keywords = new Firebird5Keywords();
        // Should include Firebird4 keywords (inherited)
        self::assertTrue($keywords->isKeyword('BINARY'));
        self::assertTrue($keywords->isKeyword('WINDOW'));
    }

    public function testFirebird3KeywordsGetName(): void
    {
        $keywords = new Firebird3Keywords();
        self::assertSame('Firebird3', $keywords->getName());
    }
}
