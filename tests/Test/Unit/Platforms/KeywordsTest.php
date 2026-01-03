<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Platforms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\Firebird3Keywords;
use Satag\DoctrineFirebirdDriver\Platforms\Keywords\FirebirdKeywords;

/**
 * Unit tests for Firebird Keyword classes.
 */
#[CoversClass(FirebirdKeywords::class)]
#[CoversClass(Firebird3Keywords::class)]
class KeywordsTest extends TestCase
{
    public function testFirebirdKeywordsGetName(): void
    {
        $keywords = new FirebirdKeywords();
        self::assertSame('Firebird', $keywords->getName());
    }

    public function testFirebird3KeywordsGetName(): void
    {
        $keywords = new Firebird3Keywords();
        self::assertSame('Firebird3', $keywords->getName());
    }

    public function testFirebirdKeywordsContainsExpectedKeywords(): void
    {
        $keywords = new FirebirdKeywords();

        // Test some common keywords are recognized
        self::assertTrue($keywords->isKeyword('SELECT'));
        self::assertTrue($keywords->isKeyword('INSERT'));
        self::assertTrue($keywords->isKeyword('UPDATE'));
        self::assertTrue($keywords->isKeyword('DELETE'));
        self::assertTrue($keywords->isKeyword('FROM'));
        self::assertTrue($keywords->isKeyword('WHERE'));
        self::assertTrue($keywords->isKeyword('TABLE'));
        self::assertTrue($keywords->isKeyword('CREATE'));
        self::assertTrue($keywords->isKeyword('DROP'));
        self::assertTrue($keywords->isKeyword('ALTER'));
    }

    public function testFirebird3KeywordsContainsAdditionalKeywords(): void
    {
        $keywords = new Firebird3Keywords();

        // Firebird 3 specific keywords (added in FB3 beyond base FB2.5)
        self::assertTrue($keywords->isKeyword('BOOLEAN'));
        self::assertTrue($keywords->isKeyword('OFFSET'));
        self::assertTrue($keywords->isKeyword('LOCALTIME'));
        self::assertTrue($keywords->isKeyword('LOCALTIMESTAMP'));
    }

    public function testFirebirdKeywordsCaseInsensitive(): void
    {
        $keywords = new FirebirdKeywords();

        self::assertTrue($keywords->isKeyword('select'));
        self::assertTrue($keywords->isKeyword('SELECT'));
        self::assertTrue($keywords->isKeyword('Select'));
    }

    public function testNonKeywordNotRecognized(): void
    {
        $keywords = new FirebirdKeywords();

        self::assertFalse($keywords->isKeyword('foobar'));
        self::assertFalse($keywords->isKeyword('mycolumn'));
        self::assertFalse($keywords->isKeyword('customtable'));
    }
}
