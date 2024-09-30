<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Artist;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Artist;

#[ORM\Table(name: 'ARTIST_TYPE')]
#[ORM\Entity]
class Type
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string|null $name = null;

    /** @var Collection<int, Artist> */
    #[ORM\OneToMany(targetEntity: Artist::class, mappedBy: 'type')]
    private Collection $artists;

    public function __construct(string $name)
    {
        $this->setName($name);
        $this->artists = new ArrayCollection();
    }

    public function addArtist(Artist $artist): self
    {
        if ($this->artists->contains($artist) === false) {
            $this->artists->add($artist);
        }

        return $this;
    }

    public function removeArtist(Artist $artist): self
    {
        if ($this->artists->contains($artist)) {
            $this->artists->removeElement($artist);
        }

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @return Collection                       Artist[] */
    public function getArtists(): Collection
    {
        return $this->artists;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
