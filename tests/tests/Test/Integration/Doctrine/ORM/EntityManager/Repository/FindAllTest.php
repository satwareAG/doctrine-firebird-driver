<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\Entity;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity;

class FindAllTest extends AbstractIntegrationTestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testFindByAlbum()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albums = $this->_entityManager->getRepository(AttributeEntity\Album::class)->findAll();
        } else {

            $albums = $this->_entityManager->getRepository(Entity\Album::class)->findAll();
        }
        $this->assertGreaterThan(2, $albums);
        $this->assertArrayHasKey(0, $albums);
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(AttributeEntity\Album::class, $albums[0]);
        } else {
            $this->assertInstanceOf(Entity\Album::class, $albums[0]);
        }
        $this->assertArrayHasKey(1, $albums);
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(AttributeEntity\Album::class, $albums[1]);
        } else {
            $this->assertInstanceOf(Entity\Album::class, $albums[1]);

        }
        $this->assertSame(1, $albums[0]->getId());
        $this->assertSame(2, $albums[1]->getId());
    }
}
