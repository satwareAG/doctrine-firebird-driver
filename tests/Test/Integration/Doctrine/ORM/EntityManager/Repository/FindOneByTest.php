<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Integration\Doctrine\ORM\EntityManager\Repository;

use Satag\DoctrineFirebirdDriver\Test\Integration\ReadOnlyIntegrationTestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class FindOneByTest extends ReadOnlyIntegrationTestCase
{
    public function testFindOneByAlbum(): void
    {
        $album = $this->_entityManager->getRepository(Entity\Album::class)->findOneBy(['id' => 1]);
        self::assertInstanceOf(Entity\Album::class, $album);
        self::assertSame(1, $album->getId());
    }
}
