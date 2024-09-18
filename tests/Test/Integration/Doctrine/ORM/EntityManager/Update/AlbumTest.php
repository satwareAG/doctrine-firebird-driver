<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Update;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

/**
 * 
 */
class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanUpdate()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = new Entity\Album("Highway to Hell");
        } else {
            $album = new Entity\Album("Highway to Hell");

        }
        $this->assertNull($album->getId());
        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        $this->assertIsInt($album->getId());
        $this->assertNull($album->getArtist());
        $albumId = $album->getId();
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $foundAlbumA = $this->_entityManager->getRepository(Entity\Album::class)->find($albumId);
            $this->assertInstanceOf(Entity\Album::class, $foundAlbumA);
        } else {
            $foundAlbumA = $this->_entityManager->getRepository(Entity\Album::class)->find($albumId);
            $this->assertInstanceOf(Entity\Album::class, $foundAlbumA);
        }
        $this->assertSame($album, $foundAlbumA); // Object is already loaded
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $artist = $this->_entityManager->getRepository(Entity\Artist::class)->find(2);
        } else {

            $artist = $this->_entityManager->getRepository(Entity\Artist::class)->find(2);
        }
        $foundAlbumA->setArtist($artist);
        $this->_entityManager->flush();
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $foundAlbumB = $this->_entityManager->getRepository(Entity\Album::class)->find($albumId);
        } else {
            $foundAlbumB = $this->_entityManager->getRepository(Entity\Album::class)->find($albumId);
        }
        $this->assertSame($foundAlbumA, $foundAlbumB); // Object is already loaded
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(Entity\Artist::class, $foundAlbumB->getArtist());
        } else {
            $this->assertInstanceOf(Entity\Artist::class, $foundAlbumB->getArtist());
        }
        $this->assertSame($artist, $foundAlbumB->getArtist()); // Object is already loaded
        $this->assertSame(2, $foundAlbumB->getArtist()->getId());
    }
}