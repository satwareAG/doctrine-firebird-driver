<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity\Artist;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity\Artist;

#[ORM\Table(name: 'ARTIST_TYPE')]
#[ORM\Entity]
class Type
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    #[ORM\OneToMany(targetEntity: 'Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity\Artist', mappedBy: 'type')]
    private $artists;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->setName($name);
        $this->artists = new ArrayCollection;
    }

    /**
     * @return self
     */
    public function addArtist(Artist $artist)
    {
        if (false == $this->artists->contains($artist)) {
            $this->artists->add($artist);
        }
        return $this;
    }

    /**
     * @return self
     */
    public function removeArtist(Artist $artist)
    {
        if ($this->artists->contains($artist)) {
            $this->artists->removeElement($artist);
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
     * @return Collection                       Artist[]
     */
    public function getArtists()
    {
        return $this->artists;
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
}
