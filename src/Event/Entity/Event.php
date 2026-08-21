<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\EventRepository;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un événement proposé aux membres : sortie, match, atelier, repas.
 *
 * LES DATES SONT DE VRAIES DATES, pas les libellés « 15 Mai 2026 » et
 * « 9h00 - 16h00 » que montre la maquette. Ces libellés se recomposent au
 * moment de l'affichage, alors que l'inverse est impossible : sans date
 * exploitable, l'écran calendrier ne peut pas placer l'événement dans une
 * case, ni le site trier par date ou masquer ce qui est passé.
 *
 * Le lieu, en revanche, reste une chaîne libre (« Autrans, 38880 ») : c'est
 * ainsi que la maquette l'écrit, et le découper en ville et code postal
 * supposerait une saisie structurée que le formulaire de création ne demande
 * pas.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\Index(name: 'idx_event_starts_at', columns: ['starts_at'])]
#[ORM\HasLifecycleCallbacks]
class Event
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 200, unique: true)]
    private string $slug;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EventCategory $category = null;

    /**
     * Organisateur. Nullable pour l'instant : les événements de démonstration
     * viennent de la maquette et n'ont pas d'auteur ; ceux créés par
     * l'assistant en auront un.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $organizer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /**
     * Nombre de participants affiché sur la carte.
     *
     * Recopié plutôt que compté, comme les notes des activités : la maquette
     * annonce des volumes, et compter à chaque carte coûterait une requête de
     * plus par vignette. La valeur sera recalculée quand les inscriptions
     * existeront.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $participantsCount = 0;

    /**
     * Événement privé : il n'apparaît que dans l'onglet « Événements privés ».
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $private = false;

    /** Rang d'affichage : l'ordre des cartes est fixé par la maquette. */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __construct()
    {
        $this->startsAt = new \DateTimeImmutable();
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCategory(): ?EventCategory
    {
        return $this->category;
    }

    public function setCategory(?EventCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function setOrganizer(?User $organizer): static
    {
        $this->organizer = $organizer;

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

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getParticipantsCount(): int
    {
        return $this->participantsCount;
    }

    public function setParticipantsCount(int $participantsCount): static
    {
        $this->participantsCount = $participantsCount;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): static
    {
        $this->private = $private;

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
