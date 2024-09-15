<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class FindByTest extends AbstractIntegrationTestCase
{
    /**
     *
     */
    public function testFindByAlbum()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albums = $this->_entityManager->getRepository(Entity\Album::class)->findBy([
                "id" => 1,
            ]);
        } else {
            $albums = $this->_entityManager->getRepository(Entity\Album::class)->findBy([
                "id" => 1,
            ]);

        }
        $this->assertCount(1, $albums);
        $this->assertArrayHasKey(0, $albums);
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(Entity\Album::class, $albums[0]);
        } else {
            $this->assertInstanceOf(Entity\Album::class, $albums[0]);
        }

        $this->assertSame(1, $albums[0]->getId());
    }
}
