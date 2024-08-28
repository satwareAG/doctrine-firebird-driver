<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Integration\Doctrine\EntityManager\Detach;

use Kafoso\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\Entity;

/**
 * @runTestsInSeparateProcesses
 */
class AlbumTestCase extends AbstractIntegrationTestCase
{
    public function testCanDetatch()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albumA = new AttributeEntity\Album("Foo");
        } else {
            $albumA = new Entity\Album("Foo");
        }

        $this->assertNull($albumA->getId());
        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $id = $albumA->getId();
        $this->assertSame("Foo", $albumA->getName());
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albumB = $this->_entityManager->getRepository(AttributeEntity\Album::class)->find($id);
        } else {
            $albumB = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
        }

        $this->assertSame($albumB, $albumA);
        $this->_entityManager->detach($albumA);
        $albumA->setName("Bar");
        $this->assertSame("Bar", $albumA->getName());
        $this->_entityManager->flush();
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $albumC = $this->_entityManager->getRepository(AttributeEntity\Album::class)->find($id);
        } else {
            $albumC = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
        }

        $this->assertSame("Foo", $albumC->getName());
        $this->assertNotSame($albumA, $albumC);
    }
}
