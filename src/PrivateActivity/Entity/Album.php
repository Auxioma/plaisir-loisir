<?php

declare(strict_types=1);

namespace App\PrivateActivity\Entity;

use App\PrivateActivity\Repository\AlbumRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Album photo d'une activité privée (§4 de docs/corrections-client-2026-07-27.md).
 *
 * Une activité gratuite (entre particuliers) possède au plus un album — créé
 * à la première photo déposée, pas à la création de l'activité : la plupart
 * des activités n'en recevront jamais (voir AlbumService::getOrCreate()).
 */
#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Album
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\OneToOne(targetEntity: PrivateActivity::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?PrivateActivity $privateActivity = null;

    /**
     * @var Collection<int, Photo>
     */
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'album', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $photos;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
    }

    public function getPrivateActivity(): ?PrivateActivity
    {
        return $this->privateActivity;
    }

    public function setPrivateActivity(?PrivateActivity $privateActivity): static
    {
        $this->privateActivity = $privateActivity;

        return $this;
    }

    /**
     * @return Collection<int, Photo>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setAlbum($this);
        }

        return $this;
    }
}
