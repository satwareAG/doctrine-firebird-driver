<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Satag\DoctrineFirebirdDriver\Test\Integration\AbstractIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class FindAllTest extends AbstractIntegrationTestCase
{
    public function testFindByAlbum(): void
    {
        $albums = $this->_entityManager->getRepository(Entity\Album::class)->findAll();

        self::assertGreaterThan(2, $albums);
        self::assertArrayHasKey(0, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[0]);

        self::assertArrayHasKey(1, $albums);
        self::assertInstanceOf(Entity\Album::class, $albums[1]);
        self::assertSame(1, $albums[0]->getId());
        self::assertSame(2, $albums[1]->getId());
    }
}
