<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Detach;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

/**
 *
 */
class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanDetatch()
    {
        $albumA = new Entity\Album("Foo");
        $this->assertNull($albumA->getId());
        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $id = $albumA->getId();
        $this->assertGreaterThan(0, $id);

        $this->assertSame("Foo", $albumA->getName());
        $albumB = $this->_entityManager->getRepository(Entity\Album::class)->find($id);

        $this->assertSame($albumB, $albumA);
        $this->_entityManager->detach($albumA);
        $albumA->setName("Bar");
        $this->assertSame("Bar", $albumA->getName());
        $this->_entityManager->flush();
        $albumC = $this->_entityManager->getRepository(Entity\Album::class)->find($id);

        $this->assertSame("Foo", $albumC->getName());
        $this->assertNotSame($albumA, $albumC);
    }
}
