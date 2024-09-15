<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

/**
 * 
 */
class FindOneByTest extends AbstractIntegrationTestCase
{
    public function testFindOneByAlbum()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->findOneBy([
                "id" => 1,
            ]);
            $this->assertInstanceOf(Entity\Album::class, $album);
        } else {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->findOneBy([
                "id" => 1,
            ]);
            $this->assertInstanceOf(Entity\Album::class, $album);
        }


        $this->assertSame(1, $album->getId());
    }
}
