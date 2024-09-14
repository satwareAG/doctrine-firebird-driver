<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Doctrine\Common\Collections\Collection;
use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\Entity;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity;

/**
 * @ runTestsInSeparateProcesses
 */
class FindTest extends AbstractIntegrationTestCase
{
    public function testFindAlbum()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = $this->_entityManager->getRepository(AttributeEntity\Album::class)->find(1);
            $this->assertInstanceOf(AttributeEntity\Album::class, $album);
        } else {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->find(1);
            $this->assertInstanceOf(Entity\Album::class, $album);
        }

        $this->assertSame(1, $album->getId());
        $this->assertSame("...Baby One More Time", $album->getName());
        $this->assertSame("2017-01-01 15:00:00", $album->getTimeCreated()->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(Collection::class, $album->getSongs());
        $this->assertSame(2, $album->getSongs()->count());
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(AttributeEntity\Song::class, $album->getSongs()->get(0));
        } else {

            $this->assertInstanceOf(Entity\Song::class, $album->getSongs()->get(0));
        }
        $this->assertSame(1, $album->getSongs()->get(0)->getId());
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(AttributeEntity\Song::class, $album->getSongs()->get(1));
        } else {

            $this->assertInstanceOf(Entity\Song::class, $album->getSongs()->get(1));
        }
        $this->assertSame(2, $album->getSongs()->get(1)->getId());
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(AttributeEntity\Artist::class, $album->getArtist());
        } else {

            $this->assertInstanceOf(Entity\Artist::class, $album->getArtist());
        }
        $this->assertSame(2, $album->getArtist()->getId());
    }

    public function testFindAlbumReturnsNullOnMismatch()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = $this->_entityManager->getRepository(AttributeEntity\Album::class)->find(0);
        } else {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->find(0);
        }
        $this->assertNull($album);
    }

    public function testFindArtist()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $artist = $this->_entityManager->getRepository(AttributeEntity\Artist::class)->find(2);
            $this->assertInstanceOf(AttributeEntity\Artist::class, $artist);
        } else {
            $artist = $this->_entityManager->getRepository(Entity\Artist::class)->find(2);
            $this->assertInstanceOf(Entity\Artist::class, $artist);
        }

        $this->assertSame(2, $artist->getId());
        $this->assertSame(1, $artist->getAlbums()->count());
        $this->assertSame("Britney Spears", $artist->getName());
        $this->assertSame(2, $artist->getType()->getId());
    }

    public function testFindArtistType()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $type = $this->_entityManager->getRepository(AttributeEntity\Artist\Type::class)->find(2);
            $this->assertInstanceOf(AttributeEntity\Artist\Type::class, $type);
        } else {
            $type = $this->_entityManager->getRepository(Entity\Artist\Type::class)->find(2);
            $this->assertInstanceOf(Entity\Artist\Type::class, $type);
        }
        $this->assertSame(2, $type->getId());
        $this->assertSame("Solo", $type->getName());
        $this->assertGreaterThan(0, $type->getArtists()->count());
    }

    public function testFindGenre()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $genre = $this->_entityManager->getRepository(AttributeEntity\Genre::class)->find(3);
            $this->assertInstanceOf(AttributeEntity\Genre::class, $genre);

        } else {
            $genre = $this->_entityManager->getRepository(Entity\Genre::class)->find(3);
            $this->assertInstanceOf(Entity\Genre::class, $genre);

        }
        $this->assertSame(3, $genre->getId());
        $this->assertSame("Pop", $genre->getName());
        $this->assertSame(2, $genre->getSongs()->count());
    }

    public function testFindSong()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $song = $this->_entityManager->getRepository(AttributeEntity\Song::class)->find(1);
            $this->assertInstanceOf(AttributeEntity\Song::class, $song);

        } else {
            $song = $this->_entityManager->getRepository(Entity\Song::class)->find(1);
            $this->assertInstanceOf(Entity\Song::class, $song);

        }
        $this->assertSame(1, $song->getId());
        $this->assertSame("...Baby One More Time", $song->getName());
        $this->assertSame("2017-01-01 15:00:00", $song->getTimeCreated()->format('Y-m-d H:i:s'));
        $this->assertSame(1, $song->getAlbums()->count());
        $this->assertSame(3, $song->getGenre()->getId());
    }
}
