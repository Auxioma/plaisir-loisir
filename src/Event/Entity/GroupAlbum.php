<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\GroupAlbumRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Album photos d'un groupe, tel que l'onglet « Photos » le présente.
 *
 * Le nombre de photos est stocké et non compté : les photos elles-mêmes n'ont
 * pas encore d'entité — la maquette n'en montre que la vignette de couverture
 * et un décompte. Quand le dépôt de photos existera, ce compteur sera
 * recalculé, comme les autres volumes du site.
 *
 * Rappel du cadre pose par le client le 27/07 : vingt-cinq photos au maximum
 * par album. La contrainte s'appliquera au dépôt, pas ici.
 */
#[ORM\Entity(repositoryClass: GroupAlbumRepository::class)]
#[ORM\Table(name: 'group_album')]
#[ORM\HasLifecycleCallbacks]
class GroupAlbum
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(inversedBy: 'albums')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Group $group = null;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $location = null;

    /** Photo de couverture de l'album. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $photosCount = 0;

    /**
     * Date du dernier ajout, affichée « Mis à jour le 28 Juill. 2026 ».
     *
     * Une vraie date et non le libellé : c'est elle qui permettra de trier les
     * albums du plus récent au plus ancien.
     */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastPhotoAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getPhotosCount(): int
    {
        return $this->photosCount;
    }

    public function setPhotosCount(int $photosCount): static
    {
        $this->photosCount = $photosCount;

        return $this;
    }

    public function getLastPhotoAt(): ?\DateTimeImmutable
    {
        return $this->lastPhotoAt;
    }

    public function setLastPhotoAt(?\DateTimeImmutable $lastPhotoAt): static
    {
        $this->lastPhotoAt = $lastPhotoAt;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
