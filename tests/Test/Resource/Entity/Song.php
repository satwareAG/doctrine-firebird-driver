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

#[ORM\Table(name: 'SONG')]
#[ORM\Entity]
class Song
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int|null $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface|null $timeCreated = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string|null $name = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool|null $tophit = null;

    #[ORM\JoinTable(name: 'Album_SongMap')]
    #[ORM\JoinColumn(name: 'song_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'album_id', referencedColumnName: 'id', unique: true)]
    #[ORM\ManyToMany(targetEntity: 'Album')]
    private Collection $albums;

    #[ORM\ManyToOne(targetEntity: 'Genre', inversedBy: 'songs')]
    private $genre;

    #[ORM\ManyToOne(targetEntity: 'Artist', inversedBy: 'albums')]
    private $artist;

    public function __construct(string $name, Genre $genre)
    {
        $this->timeCreated = new DateTime();
        $this->setName($name);
        $this->setGenre($genre);
        $this->albums = new ArrayCollection();
    }

    public function addAlbum(Album $album): self
    {
        if ($this->albums->contains($album) === false) {
            $this->albums->add($album);
            $album->addSong($this);
        }

        return $this;
    }

    public function removeAlbum(Album $album): self
    {
        if ($this->albums->contains($album)) {
            $this->albums->removeElement($album);
            $album->removeSong($this);
        }

        return $this;
    }

    public function setArtist(Artist|null $artist = null): self
    {
        $this->artist = $artist;

        return $this;
    }

    public function setGenre(Genre $genre): self
    {
        if ($this->genre) {
            $this->genre->removeSong($this);
        }

        $this->genre = $genre;
        $this->genre->addSong($this);

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAlbums(): Collection
    {
        return $this->albums;
    }

    public function getArtist(): Artist|null
    {
        return $this->artist;
    }

    public function getGenre(): Genre
    {
        return $this->genre;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTimeCreated(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromMutable($this->timeCreated);
    }

    public function isTophit(): bool|null
    {
        return $this->tophit;
    }

    public function setTophit(bool|null $tophit): Song
    {
        $this->tophit = $tophit;

        return $this;
    }
}
