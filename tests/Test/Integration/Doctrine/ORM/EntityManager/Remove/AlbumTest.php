<?php
namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Remove;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

/**
 *
 */
class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanRemove()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = new Entity\Album("Some album " . __FUNCTION__);
        } else {
            $album = new Entity\Album("Some album " . __FUNCTION__);
        }
        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        $this->assertIsInt($album->getId());
        $id = $album->getId();
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
        } else {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
        }
        $this->assertIsInt($album->getId());
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->assertInstanceOf(Entity\Album::class, $album);
        } else {
            $this->assertInstanceOf(Entity\Album::class, $album);
        }
        $this->_entityManager->remove($album);
        $this->_entityManager->flush();
        $this->assertNotNull($album);
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
        } else {
            $album = $this->_entityManager->getRepository(Entity\Album::class)->find($id);
        }
        $this->assertNull($album);
    }

    public function testCascaingRemoveWorks()
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $subclass = new Entity\Cases\CascadingRemove\Subclass;
        } else {
            $subclass = new Entity\Cases\CascadingRemove\Subclass;

        }
        $this->_entityManager->persist($subclass);
        $this->_entityManager->flush();
        $this->assertIsInt($subclass->getId());
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $cascadingRemove = new Entity\Cases\CascadingRemove($subclass);
        } else {
            $cascadingRemove = new Entity\Cases\CascadingRemove($subclass);

        }
        $this->_entityManager->persist($cascadingRemove);
        $this->_entityManager->flush();
        $this->assertIsInt($cascadingRemove->getId());
        $cascadingRemoveId = $cascadingRemove->getId();
        $subclassId = $cascadingRemove->getSubclass()->getId();
        $this->_entityManager->remove($cascadingRemove);
        $this->_entityManager->flush();
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $cascadingRemove = $this->_entityManager->getRepository(Entity\Cases\CascadingRemove::class)->find($cascadingRemoveId);
        } else {
            $cascadingRemove = $this->_entityManager->getRepository(Entity\Cases\CascadingRemove::class)->find($cascadingRemoveId);

        }
        $this->assertNull($cascadingRemove);
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $subclass = $this->_entityManager->getRepository(Entity\Cases\CascadingRemove\Subclass::class)->find($subclassId);
        } else {
            $subclass = $this->_entityManager->getRepository(Entity\Cases\CascadingRemove\Subclass::class)->find($subclassId);
        }

        $this->assertNull($subclass);
    }
}
