<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Test\Resource\Entity;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class ArtistTest extends TestCase
{
    public function testBasics(): void
    {
        $artistType = $this
            ->getMockBuilder(Entity\Artist\Type::class)
            ->disableOriginalConstructor()
            ->getMock();
        $artist     = new Entity\Artist('Foo', $artistType);
        self::assertNull($artist->getId());
        self::assertInstanceOf(Collection::class, $artist->getAlbums());
        self::assertCount(0, $artist->getAlbums());
        self::assertSame('Foo', $artist->getName());
        self::assertSame($artistType, $artist->getType());
    }

    public function testAddAndRemoveAlbumWorks(): void
    {
        $artistType = $this
            ->getMockBuilder(Entity\Artist\Type::class)
            ->disableOriginalConstructor()
            ->getMock();
        $artist     = new Entity\Artist('Foo', $artistType);
        $albumA     = $this
            ->getMockBuilder(Entity\Album::class)
            ->disableOriginalConstructor()
            ->getMock();
        $albumB     = clone $albumA;
        $artist->removeAlbum($albumA);
        self::assertCount(0, $artist->getAlbums());
        $artist->addAlbum($albumA);
        self::assertCount(1, $artist->getAlbums());
        $artist->addAlbum($albumB);
        self::assertCount(2, $artist->getAlbums());
        $artist->addAlbum($albumA);
        $artist->addAlbum($albumB);
        self::assertCount(2, $artist->getAlbums());
        $artist->removeAlbum($albumA);
        self::assertCount(1, $artist->getAlbums());
        $artist->removeAlbum($albumA);
        self::assertCount(1, $artist->getAlbums());
        $artist->removeAlbum($albumB);
        self::assertCount(0, $artist->getAlbums());
    }

    public function testSetNameWorks(): void
    {
        $artistType = $this
            ->getMockBuilder(Entity\Artist\Type::class)
            ->disableOriginalConstructor()
            ->getMock();
        $artist     = new Entity\Artist('Foo', $artistType);
        $artist->setName('Bar');
        self::assertSame('Bar', $artist->getName());
    }

    public function testSetType(): void
    {
        $artistTypeA = $this
            ->getMockBuilder(Entity\Artist\Type::class)
            ->disableOriginalConstructor()
            ->getMock();
        $artistTypeB = clone $artistTypeA;
        $artist      = new Entity\Artist('Foo', $artistTypeA);
        $artist->setType($artistTypeB);
        self::assertNotSame($artistTypeA, $artist->getType());
        self::assertSame($artistTypeB, $artist->getType());
    }
}
