<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Detach;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class AlbumTest extends AbstractIntegrationTestCase
{
    public function testCanDetatch(): void
    {
        $albumA = new Entity\Album('Foo');
        self::assertNull($albumA->getId());
        $this->_entityManager->persist($albumA);
        $this->_entityManager->flush();
        $id = $albumA->getId();
        self::assertGreaterThan(0, $id);

        self::assertSame('Foo', $albumA->getName());
        $albumB = $this->_entityManager->getRepository(Entity\Album::class)->find($id);

        self::assertSame($albumB, $albumA);
        $this->_entityManager->detach($albumA);
        $albumA->setName('Bar');
        self::assertSame('Bar', $albumA->getName());
        $this->_entityManager->flush();
        $albumC = $this->_entityManager->getRepository(Entity\Album::class)->find($id);

        self::assertSame('Foo', $albumC->getName());
        self::assertNotSame($albumA, $albumC);
    }
}
