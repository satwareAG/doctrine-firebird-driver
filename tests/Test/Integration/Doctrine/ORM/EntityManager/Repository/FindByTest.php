<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class FindByTest extends AbstractIntegrationTestCase
{
    public function testFindByAlbum(): void
    {
        $albums = $this->_entityManager->getRepository(Entity\Album::class)->findBy(['id' => 1]);

        self::assertCount(1, $albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertSame(1, $albums[0]->getId());
    }
}
