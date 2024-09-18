<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Persist;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

/**
 * 
 */
class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanPersist()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = new Entity\Album("Communion");
        } else {
            $album = new Entity\Album("Communion");
        }
        $this->assertNull($album->getId());
        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        $this->assertIsInt($album->getId());
        $this->assertSame("Communion", $album->getName());
    }

    public function testCascadingPersistWorks()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $artistType = $this->_entityManager->getRepository(Entity\Artist\Type::class)->find(2);
            $album = new Entity\Album("Life thru a Lens");
            $artist = new Entity\Artist("Robbie Williams", $artistType);
        } else {
            $artistType = $this->_entityManager->getRepository(Entity\Artist\Type::class)->find(2);
            $album = new Entity\Album("Life thru a Lens");
            $artist = new Entity\Artist("Robbie Williams", $artistType);
        }


        $album->setArtist($artist);
        $this->assertNull($album->getId());
        $this->assertNull($artist->getId());
        $this->assertSame($artist, $album->getArtist());
        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        $id = $album->getId();
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albumFound = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
            $this->assertSame($album, $albumFound);
            $this->assertInstanceOf(Entity\Artist::class, $album->getArtist());
        } else {
            $albumFound = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
            $this->assertSame($album, $albumFound);
            $this->assertInstanceOf(Entity\Artist::class, $album->getArtist());
        }
        $this->assertSame($artist, $albumFound->getArtist());
        $this->assertIsInt($albumFound->getArtist()->getId());
    }
}