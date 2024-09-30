<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Persist;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanPersist(): void
    {
        $album = new Entity\Album('Communion');

        self::assertNull($album->getId());
        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        self::assertIsInt($album->getId());
        self::assertSame('Communion', $album->getName());
    }

    public function testCascadingPersistWorks(): void
    {
        $artistType = $this->_entityManager->getRepository(Entity\Artist\Type::class)->find(2);
        $album      = new Entity\Album('Life thru a Lens');
        $artist     = new Entity\Artist('Robbie Williams', $artistType);
        $album->setArtist($artist);
        self::assertNull($album->getId());
        self::assertNull($artist->getId());
        self::assertSame($artist, $album->getArtist());
        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        $id         = $album->getId();
        $albumFound = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
        self::assertSame($album, $albumFound);
        self::assertInstanceOf(Entity\Artist::class, $album->getArtist());

        self::assertSame($artist, $albumFound->getArtist());
        self::assertIsInt($albumFound->getArtist()->getId());
    }
}
