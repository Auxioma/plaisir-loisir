<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\GroupRepository;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un groupe : une communauté qui se retrouve autour d'un centre d'intérêt.
 *
 * LE NOM DE TABLE EST ENTRE GUILLEMETS. « group » est un mot réservé du SQL
 * (GROUP BY) : sans les guillemets, PostgreSQL refuse la moindre requête sur
 * cette table. La classe s'appelle Group parce que c'est le terme du produit ;
 * seule la table est protégée.
 *
 * Le nombre de membres est recopié plutôt que compté, comme les notes des
 * activités et les participants des événements : la maquette affiche des
 * volumes (5 246 membres) et compter à chaque carte coûterait une requête par
 * vignette.
 */
#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
#[ORM\HasLifecycleCallbacks]
class Group
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 200, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $membersCount = 0;

    /** Pastille de la carte : « Nouveau ». */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $badge = null;

    /**
     * Créateur du groupe. Nullable : les groupes de démonstration viennent de
     * la maquette et n'ont pas d'auteur ; ceux créés par l'assistant en auront.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    /** Rang d'affichage : l'ordre des seize cartes est fixé par la maquette. */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /**
     * @var Collection<int, GroupAlbum>
     */
    #[ORM\OneToMany(targetEntity: GroupAlbum::class, mappedBy: 'group', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $albums;

    public function __construct()
    {
        $this->albums = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getMembersCount(): int
    {
        return $this->membersCount;
    }

    public function setMembersCount(int $membersCount): static
    {
        $this->membersCount = $membersCount;

        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

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

    /**
     * @return Collection<int, GroupAlbum>
     */
    public function getAlbums(): Collection
    {
        return $this->albums;
    }

    public function addAlbum(GroupAlbum $album): static
    {
        if (!$this->albums->contains($album)) {
            $this->albums->add($album);
            $album->setGroup($this);
        }

        return $this;
    }
}
