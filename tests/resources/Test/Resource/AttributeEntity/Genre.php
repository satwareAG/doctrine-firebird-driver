<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'GENRE')]
#[ORM\Entity]
class Genre
{
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'GENRE_D2IS')]
    private ?int $id = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(targetEntity: 'Song', mappedBy: 'genre')]
    private \Doctrine\Common\Collections\Collection $songs;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->setName($name);
        $this->songs = new ArrayCollection;
    }

    /**
     * @return self
     */
    public function addSong(Song $song)
    {
        if (false == $this->songs->contains($song)) {
            $this->songs->add($song);
            if ($this->getId() !== $song->getGenre()->getId()) {
                $song->setGenre($this);
            }
        }
        return $this;
    }

    /**
     * @return self
     */
    public function removeSong(Song $song)
    {
        if ($this->songs->contains($song)) {
            $this->songs->removeElement($song);
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
     * @return Collection                       Song[]
     */
    public function getSongs()
    {
        return $this->songs;
    }
}
