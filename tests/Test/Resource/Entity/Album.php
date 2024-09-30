<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Resource\Entity;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'ALBUM')]
#[ORM\Entity]
class Album
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int|null $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface|null $timeCreated = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string|null $name = null;

    #[ORM\ManyToOne(targetEntity: 'Artist', cascade: ['persist'], inversedBy: 'albums')]
    private $artist;

    #[ORM\JoinTable(name: 'Album_SongMap')]
    #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'song_id', referencedColumnName: 'id', unique: true)]
    #[ORM\ManyToMany(targetEntity: 'Song')]
    private Collection $songs;

    public function __construct(string $name)
    {
        $this->timeCreated = new DateTime();
        $this->setName($name);
        $this->songs = new ArrayCollection();
    }

    public function addSong(Song $song): self
    {
        if ($this->songs->contains($song) === false) {
            $this->songs->add($song);
            $song->addAlbum($this);
        }

        return $this;
    }

    public function removeSong(Song $song): self
    {
        if ($this->songs->contains($song)) {
            $this->songs->removeElement($song);
            $song->removeAlbum($this);
        }

        return $this;
    }

    public function setArtist(Artist|null $artist = null): self
    {
        $previousArtist = $this->artist;
        $this->artist   = $artist;
        if ($artist instanceof Artist) {
            $artist->addAlbum($this);
        } elseif ($previousArtist) {
            $previousArtist->removeAlbum($this);
        }

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getArtist(): Artist|null
    {
        return $this->artist;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSongs(): Collection
    {
        return $this->songs;
    }

    public function getTimeCreated(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromMutable($this->timeCreated);
    }
}
