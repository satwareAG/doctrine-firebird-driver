<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Test\Resource\Entity\Artist;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class TypeTest extends TestCase
{
    public function testBasics(): void
    {
        $type = new Entity\Artist\Type('Foo');
        self::assertNull($type->getId());
        self::assertSame('Foo', $type->getName());
        self::assertInstanceOf(Collection::class, $type->getArtists());
        self::assertCount(0, $type->getArtists());
    }

    public function testAddAndRemoveArtist(): void
    {
        $type    = new Entity\Artist\Type('Foo');
        $artistA = $this
            ->getMockBuilder(Entity\Artist::class)
            ->disableOriginalConstructor()
            ->getMock();
        $artistB = clone $artistA;
        $type->removeArtist($artistA);
        self::assertCount(0, $type->getArtists());
        $type->addArtist($artistA);
        $type->addArtist($artistA);
        self::assertCount(1, $type->getArtists());
        $type->addArtist($artistB);
        self::assertCount(2, $type->getArtists());
        $type->removeArtist($artistA);
        $type->removeArtist($artistA);
        self::assertCount(1, $type->getArtists());
        $type->removeArtist($artistB);
        self::assertCount(0, $type->getArtists());
    }

    public function testSetNameWorks(): void
    {
        $type = new Entity\Artist\Type('Foo');
        $type->setName('Bar');
        self::assertSame('Bar', $type->getName());
    }
}
