<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Doctrine\Common\Collections\Collection;
use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class FindTest extends AbstractIntegrationTestCase
{
    public function testFindAlbum(): void
    {
        $album = $this->_entityManager->getRepository(Entity\Album::class)->find(1);
        self::assertInstanceOf(Entity\Album::class, $album);
        self::assertSame(1, $album->getId());
        self::assertSame('...Baby One More Time', $album->getName());
        self::assertSame('2017-01-01 15:00:00', $album->getTimeCreated()->format('Y-m-d H:i:s'));

        self::assertInstanceOf(Collection::class, $album->getSongs());
        self::assertCount(2, $album->getSongs());
        self::assertInstanceOf(Entity\Song::class, $album->getSongs()->get(0));

        self::assertSame(1, $album->getSongs()->get(0)->getId());
        self::assertInstanceOf(Entity\Song::class, $album->getSongs()->get(1));

        self::assertSame(2, $album->getSongs()->get(1)->getId());
        self::assertInstanceOf(Entity\Artist::class, $album->getArtist());

        self::assertSame(2, $album->getArtist()->getId());
    }

    public function testFindAlbumReturnsNullOnMismatch(): void
    {
        $album = $this->_entityManager->getRepository(Entity\Album::class)->find(0);

        self::assertNull($album);
    }

    public function testFindArtist(): void
    {
        $artist = $this->_entityManager->getRepository(Entity\Artist::class)->find(2);
        self::assertInstanceOf(Entity\Artist::class, $artist);
        self::assertSame(2, $artist->getId());
        self::assertCount(1, $artist->getAlbums());
        self::assertSame('Britney Spears', $artist->getName());
        self::assertSame(2, $artist->getType()->getId());
    }

    public function testFindArtistType(): void
    {
        $type = $this->_entityManager->getRepository(Entity\Artist\Type::class)->find(2);
        self::assertInstanceOf(Entity\Artist\Type::class, $type);
        self::assertSame(2, $type->getId());
        self::assertSame('Solo', $type->getName());
        self::assertGreaterThan(0, $type->getArtists()->count());
    }

    public function testFindGenre(): void
    {
        $genre = $this->_entityManager->getRepository(Entity\Genre::class)->find(3);
        self::assertInstanceOf(Entity\Genre::class, $genre);
        self::assertSame(3, $genre->getId());
        self::assertSame('Pop', $genre->getName());
        self::assertCount(2, $genre->getSongs());
    }

    public function testFindSong(): void
    {
        $song = $this->_entityManager->getRepository(Entity\Song::class)->find(1);
        self::assertInstanceOf(Entity\Song::class, $song);
        self::assertSame(1, $song->getId());
        self::assertSame('...Baby One More Time', $song->getName());
        self::assertSame('2017-01-01 15:00:00', $song->getTimeCreated()->format('Y-m-d H:i:s'));
        self::assertCount(1, $song->getAlbums());
        self::assertSame(3, $song->getGenre()->getId());
    }
}
