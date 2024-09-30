<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Test\Resource\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class SongTest extends TestCase
{
    public function testBasics(): void
    {
        $genre = $this
            ->getMockBuilder(Entity\Genre::class)
            ->disableOriginalConstructor()
            ->getMock();
        $song  = new Entity\Song('Foo', $genre);
        self::assertNull($song->getId());
        self::assertInstanceOf(Collection::class, $song->getAlbums());
        self::assertCount(0, $song->getAlbums());
        self::assertNull($song->getArtist());
        self::assertSame($genre, $song->getGenre());
        self::assertSame('Foo', $song->getName());
        self::assertInstanceOf(DateTimeImmutable::class, $song->getTimeCreated());
    }

    public function testAddAndRemoveAlbum(): void
    {
        $genre  = $this
            ->getMockBuilder(Entity\Genre::class)
            ->disableOriginalConstructor()
            ->getMock();
        $song   = new Entity\Song('Foo', $genre);
        $albumA = $this
            ->getMockBuilder(Entity\Album::class)
            ->disableOriginalConstructor()
            ->getMock();
        $albumB = clone $albumA;
        $song->removeAlbum($albumA);
        self::assertCount(0, $song->getAlbums());
        $song->addAlbum($albumA);
        $song->addAlbum($albumA);
        self::assertCount(1, $song->getAlbums());
        $song->addAlbum($albumB);
        self::assertCount(2, $song->getAlbums());
        $song->removeAlbum($albumA);
        $song->removeAlbum($albumA);
        self::assertCount(1, $song->getAlbums());
        $song->removeAlbum($albumB);
        self::assertCount(0, $song->getAlbums());
    }

    public function testSetArtistWorks(): void
    {
        $genre  = $this
            ->getMockBuilder(Entity\Genre::class)
            ->disableOriginalConstructor()
            ->getMock();
        $song   = new Entity\Song('Foo', $genre);
        $artist = $this
            ->getMockBuilder(Entity\Artist::class)
            ->disableOriginalConstructor()
            ->getMock();
        $song->setArtist($artist);
        self::assertSame($artist, $song->getArtist());
        $song->setArtist(null);
        self::assertNull($song->getArtist());
    }

    public function testSetGenreWorks(): void
    {
        $genreA = $this
            ->getMockBuilder(Entity\Genre::class)
            ->disableOriginalConstructor()
            ->getMock();
        $genreB = clone $genreA;
        $song   = new Entity\Song('Foo', $genreA);
        self::assertSame($genreA, $song->getGenre());
        $song->setGenre($genreB);
        self::assertNotSame($genreA, $song->getGenre());
        self::assertSame($genreB, $song->getGenre());
        $song->setGenre($genreA);
        self::assertNotSame($genreB, $song->getGenre());
        self::assertSame($genreA, $song->getGenre());
    }

    public function testSetNameWorks(): void
    {
        $genre = $this
            ->getMockBuilder(Entity\Genre::class)
            ->disableOriginalConstructor()
            ->getMock();
        $song  = new Entity\Song('Foo', $genre);
        $song->setName('Bar');
        self::assertSame('Bar', $song->getName());
    }
}
