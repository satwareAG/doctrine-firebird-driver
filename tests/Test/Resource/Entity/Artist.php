<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Artist\Type;

#[ORM\Table(name: 'ARTIST')]
#[ORM\Entity]
class Artist
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string|null $name = null;

    #[ORM\ManyToOne(targetEntity: Type::class, inversedBy: 'artists')]
    private Type|null $type = null;

    #[ORM\OneToMany(targetEntity: 'Album', mappedBy: 'artist')]
    private Collection $albums;

    public function __construct(string $name, Artist\Type $type)
    {
        $this->setName($name);
        $this->setType($type);
        $this->albums = new ArrayCollection();
    }

    public function addAlbum(Album $album): self
    {
        if ($this->albums->contains($album) === false) {
            $this->albums->add($album);
        }

        return $this;
    }

    public function removeAlbum(Album $album): self
    {
        if ($this->albums->contains($album)) {
            $this->albums->removeElement($album);
        }

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setType(Artist\Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    /** @return Collection                       Album[] */
    public function getAlbums(): Collection
    {
        return $this->albums;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): Artist\Type
    {
        return $this->type;
    }
}
