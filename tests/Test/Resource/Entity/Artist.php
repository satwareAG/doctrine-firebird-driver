<?php
namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'ARTIST')]
#[ORM\Entity]
class Artist
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: \Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Artist\Type::class, inversedBy: 'artists')]
    private ?\Satag\DoctrineFirebirdDriver\Test\Resource\Entity\Artist\Type $type = null;

    #[ORM\OneToMany(targetEntity: 'Album', mappedBy: 'artist')]
    private \Doctrine\Common\Collections\Collection $albums;

    /**
     * @param string $name
     */
    public function __construct($name, Artist\Type $type)
    {
        $this->setName($name);
        $this->setType($type);
        $this->albums = new ArrayCollection;
    }

    /**
     * @return self
     */
    public function addAlbum(Album $album)
    {
        if (false == $this->albums->contains($album)) {
            $this->albums->add($album);
        }
        return $this;
    }

    /**
     * @return self
     */
    public function removeAlbum(Album $album)
    {
        if ($this->albums->contains($album)) {
            $this->albums->removeElement($album);
        }
        return $this;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return self
     */
    public function setType(Artist\Type $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Collection                       Album[]
     */
    public function getAlbums()
    {
        return $this->albums;
    }

    /**
     * @return null|int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Artist\Type
     */
    public function getType()
    {
        return $this->type;
    }
}
