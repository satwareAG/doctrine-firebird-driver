<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Update;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanUpdate(): void
    {
        $album = new Entity\Album('Highway to Hell');

        self::assertNull($album->getId());
        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        self::assertIsInt($album->getId());
        self::assertNull($album->getArtist());
        $albumId     = $album->getId();
        $foundAlbumA = $this->_entityManager->getRepository(Entity\Album::class)->find($albumId);
        self::assertInstanceOf(Entity\Album::class, $foundAlbumA);

        self::assertSame($album, $foundAlbumA); // Object is already loaded
        $artist = $this->_entityManager->getRepository(Entity\Artist::class)->find(2);

        $foundAlbumA->setArtist($artist);
        $this->_entityManager->flush();
        $foundAlbumB = $this->_entityManager->getRepository(Entity\Album::class)->find($albumId);

        self::assertSame($foundAlbumA, $foundAlbumB); // Object is already loaded
        self::assertInstanceOf(Entity\Artist::class, $foundAlbumB->getArtist());

        self::assertSame($artist, $foundAlbumB->getArtist()); // Object is already loaded
        self::assertSame(2, $foundAlbumB->getArtist()->getId());
    }
}
