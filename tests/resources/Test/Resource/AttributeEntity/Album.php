<?php
namespace Kafoso\DoctrineFirebirdDriver\Test\Resource\AttributeEntity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'ALBUM')]
#[ORM\Entity]
class Album
{
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timeCreated = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: 'Artist', cascade: ['persist'], inversedBy: 'albums')]
    private $artist = null;

    #[ORM\JoinTable(name: 'Album_SongMap')]
    #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'song_id', referencedColumnName: 'id', unique: true)]
    #[ORM\ManyToMany(targetEntity: 'Song')]
    private \Doctrine\Common\Collections\Collection $songs;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->timeCreated = new \DateTime;
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
            $song->addAlbum($this);
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
            $song->removeAlbum($this);
        }
        return $this;
    }

    /**
     * @param null|Artist $artist
     * @return self
     */
    public function setArtist(Artist $artist = null)
    {
        $previousArtist = $this->artist;
        $this->artist = $artist;
        if ($artist) {
            $artist->addAlbum($this);
        } elseif ($previousArtist) {
            $previousArtist->removeAlbum($this);
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
     * @return null|Artist
     */
    public function getArtist()
    {
        return $this->artist;
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
     * @return Collection
     */
    public function getSongs()
    {
        return $this->songs;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getTimeCreated()
    {
        return \DateTimeImmutable::createFromMutable($this->timeCreated);
    }
}
