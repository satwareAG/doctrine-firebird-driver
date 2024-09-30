<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Remove;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanRemove(): void
    {
        $album = new Entity\Album('Some album ' . __FUNCTION__);

        $this->_entityManager->persist($album);
        $this->_entityManager->flush();
        self::assertIsInt($album->getId());
        $id    = $album->getId();
        $album = $this->_entityManager->getRepository(Entity\Album::class)->find($id);

        self::assertIsInt($album->getId());
        self::assertInstanceOf(Entity\Album::class, $album);

        $this->_entityManager->remove($album);
        $this->_entityManager->flush();
        self::assertNotNull($album);
        $album = $this->_entityManager->getRepository(Entity\Album::class)->find($id);

        self::assertNull($album);
    }

    public function testCascaingRemoveWorks(): void
    {
        $subclass = new Entity\Cases\CascadingRemove\Subclass();

        $this->_entityManager->persist($subclass);
        $this->_entityManager->flush();
        self::assertIsInt($subclass->getId());
        $cascadingRemove = new Entity\Cases\CascadingRemove($subclass);

        $this->_entityManager->persist($cascadingRemove);
        $this->_entityManager->flush();
        self::assertIsInt($cascadingRemove->getId());
        $cascadingRemoveId = $cascadingRemove->getId();
        $subclassId        = $cascadingRemove->getSubclass()->getId();
        $this->_entityManager->remove($cascadingRemove);
        $this->_entityManager->flush();
        $cascadingRemove = $this->_entityManager->getRepository(Entity\Cases\CascadingRemove::class)->find($cascadingRemoveId);

        self::assertNull($cascadingRemove);
        $subclass = $this->_entityManager->getRepository(Entity\Cases\CascadingRemove\Subclass::class)->find($subclassId);

        self::assertNull($subclass);
    }
}
