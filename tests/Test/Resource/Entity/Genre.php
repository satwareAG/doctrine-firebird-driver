<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'GENRE')]
#[ORM\Entity]
class Genre
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string|null $name = null;

    #[ORM\OneToMany(targetEntity: 'Song', mappedBy: 'genre')]
    private Collection $songs;

    public function __construct(string $name)
    {
        $this->setName($name);
        $this->songs = new ArrayCollection();
    }

    public function addSong(Song $song): self
    {
        if ($this->songs->contains($song) === false) {
            $this->songs->add($song);
            if ($this->getId() !== $song->getGenre()->getId()) {
                $song->setGenre($this);
            }
        }

        return $this;
    }

    public function removeSong(Song $song): self
    {
        if ($this->songs->contains($song)) {
            $this->songs->removeElement($song);
        }

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection                       Song[] */
    public function getSongs(): Collection
    {
        return $this->songs;
    }
}
