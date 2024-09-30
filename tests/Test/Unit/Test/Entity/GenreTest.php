<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Unit\Test\Resource\Entity;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

class GenreTest extends TestCase
{
    public function testBasics(): void
    {
        $genre = new Entity\Genre('Foo');
        self::assertNull($genre->getId());
        self::assertInstanceOf(Collection::class, $genre->getSongs());
        self::assertCount(0, $genre->getSongs());
        self::assertSame('Foo', $genre->getName());
    }

    public function testAddAndRemoveSongWorks(): void
    {
        $genre = new Entity\Genre('Foo');
        $song  = $this
            ->getMockBuilder(Entity\Song::class)
            ->disableOriginalConstructor()
            ->getMock();
        $song
            ->method('getGenre')
            ->willReturn($genre);
        $genre->addSong($song);
        self::assertCount(1, $genre->getSongs());
        self::assertSame($song, $genre->getSongs()->first());
        self::assertSame($genre, $genre->getSongs()->first()->getGenre());
        $genre->removeSong($song);
        self::assertCount(0, $genre->getSongs());
    }

    public function testSetNameWorks(): void
    {
        $genre = new Entity\Genre('Foo');
        $genre->setName('Bar');
        self::assertSame('Bar', $genre->getName());
    }
}
