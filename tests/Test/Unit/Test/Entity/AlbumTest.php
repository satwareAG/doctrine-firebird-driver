<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Test\Resource\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class AlbumTest extends TestCase
{
    public function testBasics(): void
    {
        $album = new Entity\Album('Communion');
        self::assertNull($album->getId());
        self::assertInstanceOf(DateTimeImmutable::class, $album->getTimeCreated());
        self::assertSame('Communion', $album->getName());
        self::assertNull($album->getArtist());
        self::assertInstanceOf(Collection::class, $album->getSongs());
        self::assertCount(0, $album->getSongs());
    }

    public function testCanAddAndRemoveSong(): void
    {
        $album = new Entity\Album('Communion');
        $song  = $this
            ->getMockBuilder(Entity\Song::class)
            ->disableOriginalConstructor()
            ->getMock();
        $album->addSong($song);
        self::assertCount(1, $album->getSongs());
        self::assertSame($song, $album->getSongs()->first());
        $album->removeSong($song);
        self::assertCount(0, $album->getSongs());
    }

    public function testSetArtist(): void
    {
        $album   = new Entity\Album('Communion');
        $artistA = $this
            ->getMockBuilder(Entity\Artist::class)
            ->disableOriginalConstructor()
            ->getMock();
        $artistB = clone $artistA;
        $album->setArtist($artistA);
        self::assertSame($artistA, $album->getArtist());
        $album->setArtist($artistB);
        self::assertSame($artistB, $album->getArtist());
        $album->setArtist(null);
        self::assertNull($album->getArtist());
    }

    public function testSetName(): void
    {
        $album = new Entity\Album('Communion');
        $album->setName('Something else');
        self::assertSame('Something else', $album->getName());
    }
}
